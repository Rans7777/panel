from fastapi import FastAPI, Depends, HTTPException, Header
import uvicorn
import asyncmy
import aiosqlite
import json
import asyncio
import os
import pytz
from dotenv import load_dotenv
from fastapi.responses import StreamingResponse
from fastapi.middleware.cors import CORSMiddleware
from datetime import datetime, timedelta
from typing import Optional
from contextlib import asynccontextmanager
from loguru import logger

load_dotenv()
logger.add('app.log', enqueue=True, level="INFO")

@asynccontextmanager
async def lifespan(app: FastAPI):
    token_task = asyncio.create_task(delete_token())
    yield
    token_task.cancel()
    try:
        await token_task
    except asyncio.CancelledError:
        pass

app = FastAPI(lifespan=lifespan)
timezone = pytz.timezone(os.getenv('APP_TIMEZONE'))

app.add_middleware(
    CORSMiddleware,
    allow_origins=[os.getenv("APP_URL")],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
    expose_headers=["*"],
)

async def get_db_connection():
    db_connection = os.getenv("DB_CONNECTION", "mysql")
    if db_connection == "sqlite":
        db_path = os.getenv("DB_DATABASE")
        if not db_path:
            home_dir = os.path.expanduser("~")
            db_path = f"{home_dir}/database/database.sqlite"
            logger.info(f"Using default Laravel SQLite path: {db_path}")
        is_absolute = os.path.isabs(db_path)
        if not is_absolute:
            logger.error("SQLite database path must be absolute")
            raise ValueError("SQLite database path must be absolute")
        return await aiosqlite.connect(db_path)
    else:
        return await asyncmy.connect(
            host=os.getenv("DB_HOST"),
            user=os.getenv("DB_USERNAME"),
            password=os.getenv("DB_PASSWORD"),
            db=os.getenv("DB_DATABASE"),
            port=int(os.getenv("DB_PORT"))
        )

async def delete_token() -> None:
    while True:
        try:
            db_connection = os.getenv("DB_CONNECTION", "mysql")
            expiry_time = datetime.now(timezone) - timedelta(minutes=5)
            if db_connection == "sqlite":
                conn = await get_db_connection()
                try:
                    cursor = await conn.execute("DELETE FROM access_tokens WHERE created_at < ?", (expiry_time.strftime("%Y-%m-%d %H:%M:%S"),))
                    await conn.commit()
                    rows_affected = cursor.rowcount
                    if rows_affected > 0:
                        logger.info(f"Deleted {rows_affected} expired tokens")
                finally:
                    await conn.close()
            else:
                conn = await get_db_connection()
                try:
                    async with conn.cursor() as cursor:
                        await cursor.execute("DELETE FROM access_tokens WHERE created_at < %s", (expiry_time,))
                    await conn.commit()
                finally:
                    conn.close()
        except Exception as e:
            logger.error(f"Database error in delete_token: {str(e)}")
        try:
            await asyncio.sleep(300)
        except asyncio.CancelledError:
            break

async def verify_token(authorization: Optional[str] = Header(None)) -> str:
    if authorization is None:
        raise HTTPException(status_code=401, detail="Authorization header missing")
    token = authorization.replace("Bearer ", "") if authorization.startswith("Bearer ") else authorization
    current_time = datetime.now(timezone)
    valid_time = current_time - timedelta(minutes=5)
    db_connection = os.getenv("DB_CONNECTION", "mysql")
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
            conn = await get_db_connection()
            try:
                async with conn.cursor(asyncmy.cursors.DictCursor) as cursor:
                    await cursor.execute("SELECT * FROM access_tokens WHERE access_token = %s AND created_at >= %s", (token, valid_time))
                    result = await cursor.fetchone()
                    if not result:
                        raise HTTPException(status_code=401, detail="Invalid or expired token")
                    return token
            finally:
                conn.close()
    except Exception as e:
        logger.error(f"Database error in verify_token: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Database error: {str(e)}")

async def get_products() -> list[dict]:
    try:
        db_connection = os.getenv("DB_CONNECTION", "mysql")
        query = "SELECT name, description, price, stock, image, allergens, created_at FROM products"
        if db_connection == "sqlite":
            conn = await get_db_connection()
            try:
                conn.row_factory = aiosqlite.Row
                cursor = await conn.execute(query)
                rows = await cursor.fetchall()
                products = [dict(row) for row in rows]
                for product in products:
                    if product['allergens'] and isinstance(product['allergens'], str):
                        try:
                            product['allergens'] = json.loads(product['allergens'])
                        except json.JSONDecodeError:
                            pass
                    if product['created_at']:
                        product['created_at'] = product['created_at'].strftime("%Y-%m-%dT%H:%M:%SZ")
                return products
            finally:
                await conn.close()
        else:
            conn = await get_db_connection()
            try:
                async with conn.cursor(asyncmy.cursors.DictCursor) as cursor:
                    await cursor.execute(query)
                    products = await cursor.fetchall()
                    for product in products:
                        if product['allergens'] and isinstance(product['allergens'], str):
                            try:
                                product['allergens'] = json.loads(product['allergens'])
                            except json.JSONDecodeError:
                                pass
                        if product['created_at']:
                            product['created_at'] = product['created_at'].strftime("%Y-%m-%dT%H:%M:%SZ")
                    return products
            finally:
                conn.close()
    except Exception as e:
        logger.error(f"Database error in get_products: {str(e)}")
        return []

async def get_orders() -> list[dict]:
    try:
        db_connection = os.getenv("DB_CONNECTION", "mysql")
        query = "SELECT uuid, product_id, quantity, image, options, created_at FROM orders"
        if db_connection == "sqlite":
            conn = await get_db_connection()
            try:
                conn.row_factory = aiosqlite.Row
                cursor = await conn.execute(query)
                rows = await cursor.fetchall()
                orders = [dict(row) for row in rows]
                for order in orders:
                    if order['created_at']:
                        order['created_at'] = order['created_at'].strftime("%Y-%m-%dT%H:%M:%SZ")
                return orders
            finally:
                await conn.close()
        else:
            conn = await get_db_connection()
            try:
                cursor = await conn.cursor(asyncmy.cursors.DictCursor)
                await cursor.execute(query)
                orders = await cursor.fetchall()
                for order in orders:
                    if order['created_at']:
                        order['created_at'] = order['created_at'].strftime("%Y-%m-%dT%H:%M:%SZ")
                return orders
            finally:
                conn.close()
    except Exception as e:
        logger.error(f"Database error in get_orders: {str(e)}")
        return []

@app.get("/api/products/stream")
async def stream_products(token: str = Depends(verify_token)) -> StreamingResponse:
    async def event_generator():
        try:
            start_time = asyncio.get_event_loop().time()
            max_duration = 300
            disconnect_warning_time = 240
            while True:
                current_time = asyncio.get_event_loop().time()
                elapsed_time = current_time - start_time
                if elapsed_time >= max_duration:
                    yield f"event: close\ndata: {json.dumps({'message': f'Connection closed after {max_duration} seconds'})}\n\n"
                    break
                if elapsed_time >= disconnect_warning_time and elapsed_time < max_duration:
                    remaining = int(max_duration - elapsed_time)
                    yield f"event: disconnect_warning\ndata: {json.dumps({'message': f'Connection will close in {remaining} seconds'})}\n\n"
                products = await get_products()
                data = json.dumps(products, default=str)
                yield f"event: products\ndata: {data}\n\n"
                await asyncio.sleep(5)
        except ConnectionResetError:
            pass

    return StreamingResponse(
        event_generator(),
        media_type="text/event-stream",
        headers={"Cache-Control": "no-cache", "Connection": "keep-alive"}
    )

@app.get("/api/orders/stream")
async def stream_orders(token: str = Depends(verify_token)) -> StreamingResponse:
    async def event_generator():
        try:
            start_time = asyncio.get_event_loop().time()
            max_duration = 300
            disconnect_warning_time = 240
            while True:
                current_time = asyncio.get_event_loop().time()
                elapsed_time = current_time - start_time
                if elapsed_time >= max_duration:
                    yield f"event: close\ndata: {json.dumps({'message': f'Connection closed after {max_duration} seconds'})}\n\n"
                    break
                if elapsed_time >= disconnect_warning_time and elapsed_time < max_duration:
                    remaining = int(max_duration - elapsed_time)
                    yield f"event: disconnect_warning\ndata: {json.dumps({'message': f'Connection will close in {remaining} seconds'})}\n\n"
                orders = await get_orders()
                data = json.dumps(orders, default=str)
                yield f"event: orders\ndata: {data}\n\n"
                await asyncio.sleep(5)
        except ConnectionResetError:
            pass

    return StreamingResponse(
        event_generator(),
        media_type="text/event-stream",
        headers={"Cache-Control": "no-cache", "Connection": "keep-alive"}
    )

if __name__ == "__main__":
    uvicorn.run(app, host="0.0.0.0", port=8000)
