/**
 * ブラウザ対応の圧縮機能付きキャッシュクラス
 * TTLベースでキャッシュアイテムを管理し、Compression Streams APIで圧縮する
 */
export default class Cache {
    /**
     * キャッシュを初期化
     * @param {number} ttl デフォルトのTTL（ミリ秒）
     * @param {boolean} useCompression 圧縮を使用するかどうか
     */
    constructor(ttl = 60000, useCompression = false) {
        this.cache = {};
        this.defaultTtl = ttl;
        this.useCompression = useCompression;
        this.compressionAvailable = this.checkCompressionAvailability();
    }

    checkCompressionAvailability() {
        return typeof CompressionStream !== 'undefined' && 
               typeof DecompressionStream !== 'undefined';
    }

    async compress(data) {
        if (!this.useCompression || !this.compressionAvailable) {
            return data;
        }
        try {
            const jsonString = JSON.stringify(data);
            const encoder = new TextEncoder();
            const uint8Array = encoder.encode(jsonString);
            const compressedStream = new Blob([uint8Array])
                .stream()
                .pipeThrough(new CompressionStream('gzip'));
            const compressedBlob = await new Response(compressedStream).blob();
            const compressedBuffer = await compressedBlob.arrayBuffer();
            const compressedArray = new Uint8Array(compressedBuffer);
            let binaryString = '';
            compressedArray.forEach(byte => {
                binaryString += String.fromCharCode(byte);
            });
            return btoa(binaryString);
        } catch (error) {
            return data;
        }
    }

    async decompress(compressedData) {
        if (!this.compressionAvailable) {
            return compressedData;
        }
        try {
            const binaryString = atob(compressedData);
            const uint8Array = new Uint8Array(binaryString.length);
            for (let i = 0; i < binaryString.length; i++) {
                uint8Array[i] = binaryString.charCodeAt(i);
            }
            const decompressedStream = new Blob([uint8Array])
                .stream()
                .pipeThrough(new DecompressionStream('gzip'));
            const decompressedBlob = await new Response(decompressedStream).blob();
            const decompressedText = await decompressedBlob.text();
            return JSON.parse(decompressedText);
        } catch (error) {
            return compressedData;
        }
    }

    /**
     * キーと値のペアをキャッシュに設定
     * @param {string} key キャッシュのキー
     * @param {any} value キャッシュする値
     * @param {number} ttl TTL（ミリ秒）、指定しない場合はデフォルト値
     * @returns {Promise<any>} キャッシュした元の値
     */
    async set(key, value, ttl = this.defaultTtl) {
        const expires = Date.now() + ttl;
        const isCompressed = this.useCompression && this.compressionAvailable;
        let storedValue = value;
        if (isCompressed) {
            storedValue = await this.compress(value);
        }
        this.cache[key] = { 
            value: storedValue, 
            expires,
            compressed: isCompressed
        };
        return value;
    }

    /**
     * キャッシュから値を取得
     * @param {string} key キャッシュのキー
     * @returns {Promise<any|undefined>} キャッシュされた値、存在しない場合はundefined
     */
    async get(key) {
        const item = this.cache[key];
        if (!item) return undefined;
        if (Date.now() > item.expires) {
            delete this.cache[key];
            return undefined;
        }
        if (item.compressed) {
            return await this.decompress(item.value);
        }
        return item.value;
    }

    /**
     * キャッシュからキーを削除
     * @param {string} key キャッシュのキー
     * @returns {Promise<boolean>} 常にtrue
     */
    async del(key) {
        delete this.cache[key];
        return true;
    }

    /**
     * キャッシュを完全にクリア
     * @returns {Promise<boolean>} 常にtrue
     */
    async clear() {
        this.cache = {};
        return true;
    }
}
