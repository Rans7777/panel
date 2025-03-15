from fastapi import FastAPI
import uvicorn
import aiomysql
import json
import asyncio
import os
from dotenv import load_dotenv
from fastapi.responses import StreamingResponse
from fastapi.middleware.cors import CORSMiddleware

load_dotenv()
app = FastAPI()

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
    expose_headers=["*"],
)

async def get_products():
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

async def get_orders():
    query = "SELECT uuid, product_id, quantity, image, options, created_at FROM orders"
    async with aiomysql.connect(os.getenv("DB_HOST"), os.getenv("DB_USERNAME"), os.getenv("DB_PASSWORD"), os.getenv("DB_DATABASE"), int(os.getenv("DB_PORT"))) as connection:
        async with connection.cursor(aiomysql.DictCursor) as cursor:
            await cursor.execute(query)
            orders = await cursor.fetchall()
            return orders

@app.get("/api/products/stream")
async def stream_products() -> StreamingResponse:
    async def event_generator():
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

    return StreamingResponse(
        event_generator(),
        media_type="text/event-stream",
        headers={"Cache-Control": "no-cache", "Connection": "keep-alive"}
    )

@app.get("/api/orders/stream")
async def stream_orders() -> StreamingResponse:
    async def event_generator():
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

    return StreamingResponse(
        event_generator(),
        media_type="text/event-stream",
        headers={"Cache-Control": "no-cache", "Connection": "keep-alive"}
    )

if __name__ == "__main__":
    uvicorn.run(app, host="0.0.0.0", port=8000)
