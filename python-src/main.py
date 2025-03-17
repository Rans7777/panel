from fastapi import FastAPI, Depends, HTTPException, Header
import uvicorn
import aiomysql
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
logger.add('app.log', enqueue=True, format="{level} {message}", level="INFO")

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
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
    expose_headers=["*"],
)

async def delete_token() -> None:
    while True:
        try:
            query = "DELETE FROM access_tokens WHERE created_at < %s"
            async with aiomysql.connect(os.getenv("DB_HOST"), os.getenv("DB_USERNAME"), os.getenv("DB_PASSWORD"), os.getenv("DB_DATABASE"), int(os.getenv("DB_PORT"))) as connection:
                async with connection.cursor(aiomysql.DictCursor) as cursor:
                    await cursor.execute(query, (datetime.now(timezone) - timedelta(minutes=5),))
                    if cursor.rowcount > 0:
                        logger.info(f"Deleted {cursor.rowcount} expired tokens")
        except aiomysql.Error as e:
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
    query = "SELECT * FROM access_tokens WHERE access_token = %s AND created_at >= %s"
    try:
        async with aiomysql.connect(os.getenv("DB_HOST"), os.getenv("DB_USERNAME"), os.getenv("DB_PASSWORD"), os.getenv("DB_DATABASE"), int(os.getenv("DB_PORT"))) as connection:
            async with connection.cursor(aiomysql.DictCursor) as cursor:
                await cursor.execute(query, (token, valid_time))
                result = await cursor.fetchone()
            if not result:
                raise HTTPException(status_code=401, detail="Invalid or expired token")
            return token
    except aiomysql.Error as e:
        logger.error(f"Database error in verify_token: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Database error: {str(e)}")

async def get_products() -> list[dict]:
    try:
        query = "SELECT name, description, price, stock, image, allergens, created_at FROM products"
        async with aiomysql.connect(os.getenv("DB_HOST"), os.getenv("DB_USERNAME"), os.getenv("DB_PASSWORD"), os.getenv("DB_DATABASE"), int(os.getenv("DB_PORT"))) as connection:
            async with connection.cursor(aiomysql.DictCursor) as cursor:
                await cursor.execute(query)
                products = await cursor.fetchall()
                for product in products:
                    if product['allergens'] and isinstance(product['allergens'], str):
                        try:
                            product['allergens'] = json.loads(product['allergens'])
                        except json.JSONDecodeError:
                            pass
                return products
    except aiomysql.Error as e:
        logger.error(f"Database error in get_products: {str(e)}")
        return []

async def get_orders() -> list[dict]:
    try:
        query = "SELECT uuid, product_id, quantity, image, options, created_at FROM orders"
        async with aiomysql.connect(os.getenv("DB_HOST"), os.getenv("DB_USERNAME"), os.getenv("DB_PASSWORD"), os.getenv("DB_DATABASE"), int(os.getenv("DB_PORT"))) as connection:
            async with connection.cursor(aiomysql.DictCursor) as cursor:
                await cursor.execute(query)
                orders = await cursor.fetchall()
                return orders
    except aiomysql.Error as e:
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
