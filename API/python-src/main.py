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
from fastapi.responses import StreamingResponse
from fastapi.middleware.cors import CORSMiddleware
from datetime import datetime, timedelta
from typing import Optional, Dict
from loguru import logger

def load_config():
    """
    YAML形式の構成ファイル「config.yml」を読み込み、その内容を辞書として返します。
    
    ファイルの内容はyaml.safe_loadを用いてパースされます。
    """
    config_path = os.path.join('config.yml')
    with open(config_path, 'r') as f:
        return yaml.safe_load(f)

config = load_config()
logger.add('app.log', enqueue=True, level="INFO")

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

app = FastAPI()
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

async def get_products() -> list[dict]:
    try:
        db_connection = config["DB_CONNECTION"]
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
            pool = await get_db_connection()
            async with pool.acquire() as conn:
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
    except Exception as e:
        logger.error(f"Database error in get_products: {str(e)}")
        return []

async def get_orders() -> list[dict]:
    """
    データベースから注文情報を非同期に取得する。
    
    この関数は、設定ファイルのDB接続種別に応じてSQLiteまたは他のデータベースから注文データを取得する。
    各注文の作成日時（created_at）が存在する場合は、ISO 8601形式（"%Y-%m-%dT%H:%M:%SZ"）にフォーマットする。
    エラー発生時はエラーログを出力し、空のリストを返す。
    
    戻り値:
        list[dict]: 各注文情報を保持する辞書のリスト。エラー時は空のリストを返す。
    """
    try:
        db_connection = config["DB_CONNECTION"]
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
            pool = await get_db_connection()
            async with pool.acquire() as conn:
                async with conn.cursor(asyncmy.cursors.DictCursor) as cursor:
                    await cursor.execute(query)
                    orders = await cursor.fetchall()
                    for order in orders:
                        if order['created_at']:
                            order['created_at'] = order['created_at'].strftime("%Y-%m-%dT%H:%M:%SZ")
                    return orders
    except Exception as e:
        logger.error(f"Database error in get_orders: {str(e)}")
        return []

class GzipStreamingResponse(StreamingResponse):
    def __init__(self, content, status_code: int = 200, headers: Dict[str, str] = None, media_type: str = None):
        """
        Gzip圧縮対応のストリーミングレスポンスを初期化する。
        
        基本レスポンスを生成した後、HTTPヘッダーにGzip圧縮およびチャンク転送を有効にする設定を追加します。
        具体的には、「Content-Encoding」を"gzip"、「Transfer-Encoding」を"chunked"、「X-Accel-Buffering」を"no"に設定します。
        """
        super().__init__(content, status_code, headers, media_type)
        self.headers["Content-Encoding"] = "gzip"
        self.headers["Transfer-Encoding"] = "chunked"
        self.headers["X-Accel-Buffering"] = "no"

    async def stream_response(self, send):
        """
        Gzipで圧縮したレスポンスをストリーミング送信します.
        
        この非同期メソッドは、HTTPレスポンス開始メッセージを送信後、self.body_iteratorから取得した各チャンクをgzip圧縮して順次送信します。各チャンク送信後はバッファをリセットし、すべてのチャンクが処理された後、ストリームの完了を示す空のボディを送信します。
        """
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

@app.get("/api/products/stream")
async def stream_products(request: Request, token: str = Depends(verify_token)) -> StreamingResponse:
    """
    商品のストリーミングレスポンスを生成します。
    
    この非同期関数は、クライアントのリクエストヘッダーに基づきGzip圧縮の有無を判定し、Gzipが要求された場合はGzipStreamingResponseを返します。イベントジェネレーターは、接続時の確認メッセージ、定期的な商品のデータ配信、切断警告、及びエラー発生時の通知イベントを生成し、最大接続時間や警告時間に従って接続を管理します。
    """
    logger.debug("Starting: Product stream broadcast")
    accept_encoding = request.headers.get("Accept-Encoding", "")
    use_gzip = "gzip" in accept_encoding

    async def event_generator():
        """
            製品ストリームのSSEイベントを生成する非同期ジェネレータ。
        
            この関数は、接続確認、定期的な製品データの送信、切断警告、および接続終了イベントを
            SSE形式のメッセージとしてクライアントに送信します。接続開始時には接続確認メッセージを送出し、
            最大300秒の接続期間内で3秒ごとに製品データを取得・送信します。240秒を超えると切断警告を
            発行し、接続期間が終了すると終了イベントを返します。製品データの取得中にエラーが発生した場合は、
            エラーメッセージを送出します。
            """
        try:
            yield f"event: connected\ndata: {json.dumps({'message': 'Connected to products stream'}, ensure_ascii=False)}\n\n"

            start_time = asyncio.get_event_loop().time()
            max_duration = 300
            disconnect_warning_time = 240

            while True:
                current_time = asyncio.get_event_loop().time()
                elapsed_time = current_time - start_time

                if elapsed_time >= max_duration:
                    yield f"event: close\ndata: {json.dumps({'message': f'Connection closed after {max_duration} seconds'}, ensure_ascii=False)}\n\n"
                    break

                if elapsed_time >= disconnect_warning_time and elapsed_time < max_duration:
                    remaining = int(max_duration - elapsed_time)
                    yield f"event: disconnect_warning\ndata: {json.dumps({'message': f'Connection will close in {remaining} seconds'}, ensure_ascii=False)}\n\n"

                try:
                    products = await get_products()
                    data = json.dumps(products, default=str, ensure_ascii=False)
                    yield f"event: products\ndata: {data}\n\n"
                except Exception as e:
                    logger.error(f"Error fetching products: {str(e)}")
                    yield f"event: error\ndata: {json.dumps({'message': 'Error fetching products'}, ensure_ascii=False)}\n\n"

                await asyncio.sleep(3)
        except ConnectionResetError:
            logger.warning("Connection reset by client")
        except Exception as e:
            logger.error(f"Error in product stream: {str(e)}")
            yield f"event: error\ndata: {json.dumps({'message': 'Error in product stream'}, ensure_ascii=False)}\n\n"

    headers = {
        "Content-Type": "text/event-stream",
        "Cache-Control": "no-cache, no-transform",
        "Connection": "keep-alive",
        "X-Accel-Buffering": "no",
        "Transfer-Encoding": "chunked"
    }

    if use_gzip:
        logger.debug("Using Gzip compression")
        headers["Content-Encoding"] = "gzip"
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

@app.get("/api/orders/stream")
async def stream_orders(request: Request, token: str = Depends(verify_token)) -> StreamingResponse:
    """
    サーバー送信イベント(SSE)形式で注文情報のストリーミング配信を行います。
    
    この非同期関数は、接続開始時に初期メッセージを送信し、定期的に最新の注文情報を取得してストリームに送信します。
    接続時間が一定の閾値に達すると、切断警告および接続終了のイベントを送信します。
    また、リクエストヘッダに「gzip」が含まれている場合は、Gzip圧縮を適用したレスポンスを返します。
    認証トークンは依存性注入により検証され、有効な場合にのみストリームが開始されます。
    """
    logger.debug("Starting: Order stream broadcast")
    accept_encoding = request.headers.get("Accept-Encoding", "")
    use_gzip = "gzip" in accept_encoding

    async def event_generator():
        """
        サーバー送信イベント（SSE）用のメッセージを非同期に生成する関数です。
        
        初回に接続確立メッセージを送出し、その後定期的に注文データを取得してSSEフォーマットのメッセージを生成します。
        接続時間が300秒に達するとクローズイベントを送出し、240秒以降は接続終了までの残秒数を含む警告イベントを送出します。
        注文データの取得に失敗した場合はエラーメッセージを返し、接続リセットやその他例外発生時にも適切なエラー処理を行います。
        """
        try:
            yield f"event: connected\ndata: {json.dumps({'message': 'Connected to orders stream'}, ensure_ascii=False)}\n\n"

            start_time = asyncio.get_event_loop().time()
            max_duration = 300
            disconnect_warning_time = 240

            while True:
                current_time = asyncio.get_event_loop().time()
                elapsed_time = current_time - start_time

                if elapsed_time >= max_duration:
                    yield f"event: close\ndata: {json.dumps({'message': f'Connection closed after {max_duration} seconds'}, ensure_ascii=False)}\n\n"
                    break

                if elapsed_time >= disconnect_warning_time and elapsed_time < max_duration:
                    remaining = int(max_duration - elapsed_time)
                    yield f"event: disconnect_warning\ndata: {json.dumps({'message': f'Connection will close in {remaining} seconds'}, ensure_ascii=False)}\n\n"

                try:
                    orders = await get_orders()
                    data = json.dumps(orders, default=str, ensure_ascii=False)
                    yield f"event: orders\ndata: {data}\n\n"
                except Exception as e:
                    logger.error(f"Error fetching orders: {str(e)}")
                    yield f"event: error\ndata: {json.dumps({'message': 'Error fetching orders'}, ensure_ascii=False)}\n\n"

                await asyncio.sleep(5)
        except ConnectionResetError:
            logger.warning("Connection reset by client")
        except Exception as e:
            logger.error(f"Error in order stream: {str(e)}")
            yield f"event: error\ndata: {json.dumps({'message': 'Error in order stream'}, ensure_ascii=False)}\n\n"

    headers = {
        "Content-Type": "text/event-stream",
        "Cache-Control": "no-cache, no-transform",
        "Connection": "keep-alive",
        "X-Accel-Buffering": "no",
        "Transfer-Encoding": "chunked"
    }

    if use_gzip:
        logger.debug("Using Gzip compression")
        headers["Content-Encoding"] = "gzip"
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

if __name__ == "__main__":
    uvicorn.run(app, host="0.0.0.0", port=int(config.get("APP_PORT", 8080)))
