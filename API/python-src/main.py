from fastapi import FastAPI, Depends, HTTPException, Header, Request
import uvicorn
import asyncmy
import aiosqlite
import json
import asyncio
import os
import pytz
import yaml
import gzip
import io
import uuid
from fastapi.responses import StreamingResponse
from fastapi.middleware.cors import CORSMiddleware
from datetime import datetime, timedelta
from typing import Optional, Dict, List, Any, Callable, Awaitable
from loguru import logger
import threading
from dataclasses import dataclass
from contextlib import asynccontextmanager

def load_config():
    config_path = os.path.join('config.yml')
    with open(config_path, 'r') as f:
        return yaml.safe_load(f)

config = load_config()
debug_mode = config.get("DEBUG", False)
log_level = "DEBUG" if debug_mode else "INFO"
logger.add('app.log', enqueue=True, level=log_level)

db_pool = None

async def init_db_pool():
    global db_pool
    db_connection = config.get("DB_CONNECTION", "mysql")
    if db_connection == "sqlite":
        db_path = config.get("DB_DATABASE")
        if not db_path:
            home_dir = os.path.expanduser("~")
            db_path = f"{home_dir}/database/database.sqlite"
            logger.info(f"Using default Laravel SQLite path: {db_path}")
        is_absolute = os.path.isabs(db_path)
        if not is_absolute:
            logger.error("SQLite database path must be absolute")
            raise ValueError("SQLite database path must be absolute")
        db_pool = await aiosqlite.connect(db_path)
    else:
        db_pool = await asyncmy.create_pool(
            host=config["DB_HOST"],
            user=config["DB_USERNAME"],
            password=config["DB_PASSWORD"],
            db=config["DB_DATABASE"],
            port=int(config["DB_PORT"]),
            minsize=1,
            maxsize=10,
            pool_recycle=3
        )

async def close_db_pool():
    global db_pool
    if db_pool:
        if isinstance(db_pool, aiosqlite.Connection):
            await db_pool.close()
        else:
            db_pool.close()
            await db_pool.wait_closed()
        db_pool = None

class EventManager:
    def __init__(self, buffer_size=100):
        self.product_subscribers = {}
        self.order_subscribers = {}
        self.buffer_size = buffer_size
        self.lock = threading.RLock()

    def set_buffer_size(self, size):
        with self.lock:
            self.buffer_size = size

    def subscribe_products(self, client_id):
        with self.lock:
            queue = asyncio.Queue(maxsize=self.buffer_size)
            self.product_subscribers[client_id] = queue
            return queue

    def subscribe_orders(self, client_id):
        with self.lock:
            queue = asyncio.Queue(maxsize=self.buffer_size)
            self.order_subscribers[client_id] = queue
            return queue

    def unsubscribe_products(self, client_id):
        with self.lock:
            if client_id in self.product_subscribers:
                del self.product_subscribers[client_id]

    def unsubscribe_orders(self, client_id):
        with self.lock:
            if client_id in self.order_subscribers:
                del self.order_subscribers[client_id]

    def publish_products(self, products):
        with self.lock:
            for queue in self.product_subscribers.values():
                try:
                    queue.put_nowait(products)
                except asyncio.QueueFull:
                    pass

    def publish_orders(self, orders):
        with self.lock:
            for queue in self.order_subscribers.values():
                try:
                    queue.put_nowait(orders)
                except asyncio.QueueFull:
                    pass

event_manager = EventManager()

@dataclass
class Product:
    name: Optional[str]
    description: Optional[str]
    price: float
    stock: int
    image: Optional[str]
    allergens: Any
    created_at: datetime
    def to_dict(self):
        return {
            "name": self.name,
            "description": self.description,
            "price": self.price,
            "stock": self.stock,
            "image": self.image,
            "allergens": self.allergens,
            "created_at": self.created_at.strftime("%Y-%m-%dT%H:%M:%SZ") if self.created_at else None
        }

@dataclass
class Order:
    uuid: str
    product_id: int
    quantity: int
    image: Optional[str]
    options: Any
    created_at: datetime
    def to_dict(self):
        return {
            "uuid": self.uuid,
            "product_id": self.product_id,
            "quantity": self.quantity,
            "image": self.image,
            "options": self.options,
            "created_at": self.created_at.strftime("%Y-%m-%dT%H:%M:%SZ") if self.created_at else None
        }

@asynccontextmanager
async def lifespan(app: FastAPI):
    await init_db_pool()
    start_event_publishers()
    yield
    await close_db_pool()

app = FastAPI(lifespan=lifespan)
timezone = pytz.timezone(config.get('APP_TIMEZONE', 'UTC'))

app.add_middleware(
    CORSMiddleware,
    allow_origins=[config.get("APP_URL", "*")],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
    expose_headers=["*"],
)

async def get_db_connection():
    if not db_pool:
        await init_db_pool()
    return db_pool

async def verify_token(authorization: Optional[str] = Header(None)) -> str:
    if authorization is None:
        raise HTTPException(status_code=401, detail="Authorization header missing")
    token = authorization.replace("Bearer ", "") if authorization.startswith("Bearer ") else authorization
    current_time = datetime.now(timezone)
    valid_time = current_time - timedelta(minutes=5)
    db_connection = config["DB_CONNECTION"]
    try:
        if db_connection == "sqlite":
            conn = await get_db_connection()
            try:
                conn.row_factory = aiosqlite.Row
                valid_time_str = valid_time.strftime("%Y-%m-%d %H:%M:%S")
                cursor = await conn.execute("SELECT id, access_token, created_at FROM access_tokens WHERE access_token = ? AND created_at >= ?", (token, valid_time_str))
                result = await cursor.fetchone()
                if not result:
                    raise HTTPException(status_code=401, detail="Invalid or expired token")
                return token
            finally:
                await conn.close()
        else:
            pool = await get_db_connection()
            async with pool.acquire() as conn:
                async with conn.cursor(asyncmy.cursors.DictCursor) as cursor:
                    await cursor.execute("SELECT * FROM access_tokens WHERE access_token = %s AND created_at >= %s", (token, valid_time))
                    result = await cursor.fetchone()
                    if not result:
                        raise HTTPException(status_code=401, detail="Invalid or expired token")
                    return token
    except Exception as e:
        logger.error(f"Database error in verify_token: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Database error: {str(e)}")

async def get_last_update_time(table_name: str) -> datetime:
    try:
        db_connection = config["DB_CONNECTION"]
        query = f"SELECT MAX(updated_at) FROM {table_name}"
        if db_connection == "sqlite":
            conn = await get_db_connection()
            try:
                conn.row_factory = aiosqlite.Row
                cursor = await conn.execute(query)
                result = await cursor.fetchone()
                return result[0] if result and result[0] else datetime.now(timezone) - timedelta(hours=1)
            finally:
                await conn.close()
        else:
            pool = await get_db_connection()
            async with pool.acquire() as conn:
                async with conn.cursor(asyncmy.cursors.DictCursor) as cursor:
                    await cursor.execute(query)
                    result = await cursor.fetchone()
                    return result['MAX(updated_at)'] if result and result['MAX(updated_at)'] else datetime.now(timezone) - timedelta(hours=1)
    except Exception as e:
        logger.error(f"Error getting last update time for {table_name}: {str(e)}")
        return datetime.now(timezone) - timedelta(hours=1)

async def get_products() -> List[Product]:
    try:
        db_connection = config["DB_CONNECTION"]
        query = "SELECT name, description, price, stock, image, allergens, created_at FROM products"
        if db_connection == "sqlite":
            conn = await get_db_connection()
            try:
                conn.row_factory = aiosqlite.Row
                cursor = await conn.execute(query)
                rows = await cursor.fetchall()
                products = []
                for row in rows:
                    allergens = row['allergens']
                    if allergens and isinstance(allergens, str):
                        try:
                            allergens = json.loads(allergens)
                        except json.JSONDecodeError:
                            pass

                    product = Product(
                        name=row['name'],
                        description=row['description'],
                        price=row['price'],
                        stock=row['stock'],
                        image=row['image'],
                        allergens=allergens,
                        created_at=row['created_at']
                    )
                    products.append(product)
                return products
            finally:
                await conn.close()
        else:
            pool = await get_db_connection()
            async with pool.acquire() as conn:
                async with conn.cursor(asyncmy.cursors.DictCursor) as cursor:
                    await cursor.execute(query)
                    rows = await cursor.fetchall()
                    products = []
                    for row in rows:
                        allergens = row['allergens']
                        if allergens and isinstance(allergens, str):
                            try:
                                allergens = json.loads(allergens)
                            except json.JSONDecodeError:
                                pass

                        product = Product(
                            name=row['name'],
                            description=row['description'],
                            price=row['price'],
                            stock=row['stock'],
                            image=row['image'],
                            allergens=allergens,
                            created_at=row['created_at']
                        )
                        products.append(product)
                    return products
    except Exception as e:
        logger.error(f"Database error in get_products: {str(e)}")
        return []

async def get_products_since(since: datetime) -> List[Product]:
    try:
        db_connection = config["DB_CONNECTION"]
        query = "SELECT name, description, price, stock, image, allergens, created_at FROM products WHERE updated_at > ?"
        
        if db_connection == "sqlite":
            conn = await get_db_connection()
            try:
                conn.row_factory = aiosqlite.Row
                cursor = await conn.execute(query, (since,))
                rows = await cursor.fetchall()
                products = []
                for row in rows:
                    allergens = row['allergens']
                    if allergens and isinstance(allergens, str):
                        try:
                            allergens = json.loads(allergens)
                        except json.JSONDecodeError:
                            pass

                    product = Product(
                        name=row['name'],
                        description=row['description'],
                        price=row['price'],
                        stock=row['stock'],
                        image=row['image'],
                        allergens=allergens,
                        created_at=row['created_at']
                    )
                    products.append(product)
                return products
            finally:
                await conn.close()
        else:
            mysql_query = "SELECT name, description, price, stock, image, allergens, created_at FROM products WHERE updated_at > %s"
            pool = await get_db_connection()
            async with pool.acquire() as conn:
                async with conn.cursor(asyncmy.cursors.DictCursor) as cursor:
                    await cursor.execute(mysql_query, (since,))
                    rows = await cursor.fetchall()
                    products = []
                    for row in rows:
                        allergens = row['allergens']
                        if allergens and isinstance(allergens, str):
                            try:
                                allergens = json.loads(allergens)
                            except json.JSONDecodeError:
                                pass

                        product = Product(
                            name=row['name'],
                            description=row['description'],
                            price=row['price'],
                            stock=row['stock'],
                            image=row['image'],
                            allergens=allergens,
                            created_at=row['created_at']
                        )
                        products.append(product)
                    return products
    except Exception as e:
        logger.error(f"Database error in get_products_since: {str(e)}")
        return []

async def get_orders() -> List[Order]:
    try:
        db_connection = config["DB_CONNECTION"]
        query = "SELECT uuid, product_id, quantity, image, options, created_at FROM orders"
        if db_connection == "sqlite":
            conn = await get_db_connection()
            try:
                conn.row_factory = aiosqlite.Row
                cursor = await conn.execute(query)
                rows = await cursor.fetchall()
                orders = []
                for row in rows:
                    options = row['options']
                    if options and isinstance(options, str):
                        try:
                            options = json.loads(options)
                        except json.JSONDecodeError:
                            pass

                    order = Order(
                        uuid=row['uuid'],
                        product_id=row['product_id'],
                        quantity=row['quantity'],
                        image=row['image'],
                        options=options,
                        created_at=row['created_at']
                    )
                    orders.append(order)
                return orders
            finally:
                await conn.close()
        else:
            pool = await get_db_connection()
            async with pool.acquire() as conn:
                async with conn.cursor(asyncmy.cursors.DictCursor) as cursor:
                    await cursor.execute(query)
                    rows = await cursor.fetchall()
                    orders = []
                    for row in rows:
                        options = row['options']
                        if options and isinstance(options, str):
                            try:
                                options = json.loads(options)
                            except json.JSONDecodeError:
                                pass

                        order = Order(
                            uuid=row['uuid'],
                            product_id=row['product_id'],
                            quantity=row['quantity'],
                            image=row['image'],
                            options=options,
                            created_at=row['created_at']
                        )
                        orders.append(order)
                    return orders
    except Exception as e:
        logger.error(f"Database error in get_orders: {str(e)}")
        return []

async def get_orders_since(since: datetime) -> List[Order]:
    try:
        db_connection = config["DB_CONNECTION"]
        query = "SELECT uuid, product_id, quantity, image, options, created_at FROM orders WHERE updated_at > ?"

        if db_connection == "sqlite":
            conn = await get_db_connection()
            try:
                conn.row_factory = aiosqlite.Row
                cursor = await conn.execute(query, (since,))
                rows = await cursor.fetchall()
                orders = []
                for row in rows:
                    options = row['options']
                    if options and isinstance(options, str):
                        try:
                            options = json.loads(options)
                        except json.JSONDecodeError:
                            pass
                    
                    order = Order(
                        uuid=row['uuid'],
                        product_id=row['product_id'],
                        quantity=row['quantity'],
                        image=row['image'],
                        options=options,
                        created_at=row['created_at']
                    )
                    orders.append(order)
                return orders
            finally:
                await conn.close()
        else:
            mysql_query = "SELECT uuid, product_id, quantity, image, options, created_at FROM orders WHERE updated_at > %s"
            pool = await get_db_connection()
            async with pool.acquire() as conn:
                async with conn.cursor(asyncmy.cursors.DictCursor) as cursor:
                    await cursor.execute(mysql_query, (since,))
                    rows = await cursor.fetchall()
                    orders = []
                    for row in rows:
                        options = row['options']
                        if options and isinstance(options, str):
                            try:
                                options = json.loads(options)
                            except json.JSONDecodeError:
                                pass

                        order = Order(
                            uuid=row['uuid'],
                            product_id=row['product_id'],
                            quantity=row['quantity'],
                            image=row['image'],
                            options=options,
                            created_at=row['created_at']
                        )
                        orders.append(order)
                    return orders
    except Exception as e:
        logger.error(f"Database error in get_orders_since: {str(e)}")
        return []

def start_event_publishers():
    product_interval = config.get("PRODUCT_POLL_INTERVAL", 5)
    order_interval = config.get("ORDER_POLL_INTERVAL", 3)

    async def product_publisher():
        products = await get_products()
        if products:
            event_manager.publish_products([p.to_dict() for p in products])

        last_product_update = await get_last_update_time("products")

        while True:
            await asyncio.sleep(product_interval)
            updated_products = await get_products_since(last_product_update)
            
            if updated_products:
                all_products = await get_products()
                if all_products:
                    event_manager.publish_products([p.to_dict() for p in all_products])
                    last_product_update = datetime.now(timezone)
    
    async def order_publisher():
        orders = await get_orders()
        if orders:
            event_manager.publish_orders([o.to_dict() for o in orders])

        last_order_update = await get_last_update_time("orders")

        while True:
            await asyncio.sleep(order_interval)
            updated_orders = await get_orders_since(last_order_update)

            if updated_orders:
                all_orders = await get_orders()
                if all_orders:
                    event_manager.publish_orders([o.to_dict() for o in all_orders])
                    last_order_update = datetime.now(timezone)

    asyncio.create_task(product_publisher())
    asyncio.create_task(order_publisher())

class GzipStreamingResponse(StreamingResponse):
    def __init__(self, content, status_code: int = 200, headers: Dict[str, str] = None, media_type: str = None):
        super().__init__(content, status_code, headers, media_type)
        self.headers["Content-Encoding"] = "gzip"
        self.headers["Transfer-Encoding"] = "chunked"
        self.headers["X-Accel-Buffering"] = "no"

    async def stream_response(self, send):
        await send({
            "type": "http.response.start",
            "status": self.status_code,
            "headers": [
                [k.lower().encode(), v.encode()] for k, v in self.headers.items()
            ]
        })

        fileobj = io.BytesIO()
        gz = gzip.GzipFile(fileobj=fileobj, mode='wb')
        async for chunk in self.body_iterator:
            gz.write(chunk.encode() if isinstance(chunk, str) else chunk)
            gz.flush()
            await send({
                "type": "http.response.body",
                "body": fileobj.getvalue(),
                "more_body": True
            })
            fileobj.seek(0)
            fileobj.truncate()
        gz.close()

        await send({
            "type": "http.response.body",
            "body": b"",
            "more_body": False
        })

async def create_stream(request: Request, stream_type: str, data_fetcher: Callable[[], Awaitable[Any]], event_queue: asyncio.Queue) -> StreamingResponse:
    logger.debug(f"Starting: {stream_type.capitalize()} stream broadcast")
    accept_encoding = request.headers.get("Accept-Encoding", "")
    use_gzip = "gzip" in accept_encoding

    async def event_generator():
        try:
            yield f"event: connected\ndata: {json.dumps({'message': f'Connected to {stream_type} stream'}, ensure_ascii=False)}\n\n"

            start_time = asyncio.get_event_loop().time()
            max_duration = 300
            disconnect_warning_time = 240
            warning_sent = False

            initial_data = await data_fetcher()
            if initial_data:
                json_data = json.dumps(initial_data, default=str, ensure_ascii=False)
                yield f"event: {stream_type}\ndata: {json_data}\n\n"

            while True:
                current_time = asyncio.get_event_loop().time()
                elapsed_time = current_time - start_time

                if elapsed_time >= max_duration:
                    yield f"event: close\ndata: {json.dumps({'message': f'Connection closed after {max_duration} seconds'}, ensure_ascii=False)}\n\n"
                    break

                if elapsed_time >= disconnect_warning_time and elapsed_time < max_duration and not warning_sent:
                    warning_sent = True
                    countdown = 60
                    for i in range(countdown, 0, -1):
                        yield f"event: disconnect_warning\ndata: {json.dumps({'message': f'Connection will close in {i} seconds'}, ensure_ascii=False)}\n\n"
                        await asyncio.sleep(1)
                    
                    yield f"event: close\ndata: {json.dumps({'message': 'Connection closed'}, ensure_ascii=False)}\n\n"
                    break

                try:
                    try:
                        data = await asyncio.wait_for(event_queue.get(), timeout=0.1)
                        json_data = json.dumps(data, default=str, ensure_ascii=False)
                        yield f"event: {stream_type}\ndata: {json_data}\n\n"
                    except asyncio.TimeoutError:
                        pass
                except Exception as e:
                    logger.error(f"Error in {stream_type} stream: {str(e)}")
                    yield f"event: error\ndata: {json.dumps({'message': f'Error in {stream_type} stream'}, ensure_ascii=False)}\n\n"

        except ConnectionResetError:
            logger.warning("Connection reset by client")
        except Exception as e:
            logger.error(f"Error in {stream_type} stream: {str(e)}")
            yield f"event: error\ndata: {json.dumps({'message': f'Error in {stream_type} stream'}, ensure_ascii=False)}\n\n"

    headers = {
        "Content-Type": "text/event-stream",
        "Cache-Control": "no-cache, no-transform",
        "Connection": "keep-alive",
        "X-Accel-Buffering": "no",
        "Transfer-Encoding": "chunked"
    }

    if use_gzip:
        logger.debug("Using Gzip compression")
        return GzipStreamingResponse(
            event_generator(),
            media_type="text/event-stream",
            headers=headers
        )
    else:
        return StreamingResponse(
            event_generator(),
            media_type="text/event-stream",
            headers=headers
        )

@app.get("/api/products/stream")
async def stream_products(request: Request, token: str = Depends(verify_token)) -> StreamingResponse:
    client_id = str(uuid.uuid4())
    product_queue = event_manager.subscribe_products(client_id)

    async def product_fetcher():
        products = await get_products()
        return [p.to_dict() for p in products]

    try:
        return await create_stream(request, "products", product_fetcher, product_queue)
    finally:
        event_manager.unsubscribe_products(client_id)

@app.get("/api/orders/stream")
async def stream_orders(request: Request, token: str = Depends(verify_token)) -> StreamingResponse:
    client_id = str(uuid.uuid4())
    order_queue = event_manager.subscribe_orders(client_id)

    async def order_fetcher():
        orders = await get_orders()
        return [o.to_dict() for o in orders]

    try:
        return await create_stream(request, "orders", order_fetcher, order_queue)
    finally:
        event_manager.unsubscribe_orders(client_id)

if __name__ == "__main__":
    uvicorn.run(app, host="0.0.0.0", port=int(config.get("APP_PORT", 8080)), log_level=log_level.lower())
