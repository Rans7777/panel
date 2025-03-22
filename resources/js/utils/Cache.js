/**
 * シンプルなキャッシュクラス
 * TTLベースでキャッシュアイテムを管理
 */
export default class Cache {
    /**
     * キャッシュを初期化
     * @param {number} ttl デフォルトのTTL（ミリ秒）
     * @param {boolean} useCompression 圧縮を使用するかどうか
     * @param {boolean} useLocalStorage localStorageを使用するかどうか
     * @param {string} storagePrefix localStorageのキー接頭辞
     */
    constructor(ttl = 60000, useCompression = false, useLocalStorage = false, storagePrefix = 'cache_') {
        this.cache = {};
        this.defaultTtl = ttl;
        this.useCompression = useCompression;
        this.useLocalStorage = useLocalStorage;
        this.storagePrefix = storagePrefix;
        if (this.useLocalStorage && typeof localStorage !== 'undefined') {
            this.#loadFromLocalStorage();
        }
    }

    #loadFromLocalStorage() {
        try {
            for (let i = 0; i < localStorage.length; i++) {
                const key = localStorage.key(i);
                if (key && key.startsWith(this.storagePrefix)) {
                    const originalKey = key.slice(this.storagePrefix.length);
                    const item = JSON.parse(localStorage.getItem(key));
                    if (item && Date.now() <= item.expires) {
                        this.cache[originalKey] = item;
                    } else {
                        localStorage.removeItem(key);
                    }
                }
            }
        } catch (error) {
            //pass
        }
    }

    #saveToLocalStorage(key, item) {
        try {
            if (this.useLocalStorage && typeof localStorage !== 'undefined') {
                localStorage.setItem(`${this.storagePrefix}${key}`, JSON.stringify(item));
            }
        } catch (error) {
            this.#clearOldLocalStorageItems();
        }
    }

    #clearOldLocalStorageItems() {
        try {
            if (typeof localStorage !== 'undefined') {
                const keysToRemove = [];
                for (let i = 0; i < localStorage.length; i++) {
                    const key = localStorage.key(i);
                    if (key && key.startsWith(this.storagePrefix)) {
                        keysToRemove.push(key);
                    }
                }
                keysToRemove.forEach(key => {
                    localStorage.removeItem(key);
                });
            }
        } catch (error) {
            //pass
        }
    }

    async #compress(data) {
        if (!this.useCompression) {
            return data;
        }
        const { zlibSync } = await import('fflate');
        const jsonString = JSON.stringify(data);
        const encoder = new TextEncoder();
        const uint8Array = encoder.encode(jsonString);
        const compressedArray = zlibSync(uint8Array);
        let binaryString = '';
        compressedArray.forEach(byte => {
            binaryString += String.fromCharCode(byte);
        });
        return btoa(binaryString);
    }

    async #decompress(compressedData) {
        const { unzlibSync } = await import('fflate');
        const binaryString = atob(compressedData);
        const uint8Array = new Uint8Array(binaryString.length);
        for (let i = 0; i < binaryString.length; i++) {
            uint8Array[i] = binaryString.charCodeAt(i);
        }
        const decompressedArray = unzlibSync(uint8Array);
        const decoder = new TextDecoder();
        const decompressedText = decoder.decode(decompressedArray);
        return JSON.parse(decompressedText);
    }

    /**
     * キーと値のペアをキャッシュに設定
     * @param {string} key キャッシュのキー
     * @param {any} value キャッシュする値
     * @param {number} ttl TTL（ミリ秒）、指定しない場合はデフォルト値
     * @return {Promise<any>} キャッシュした元の値
     */
    async set(key, value, ttl = this.defaultTtl) {
        const expires = Date.now() + ttl;
        const isCompressed = this.useCompression;
        let storedValue = value;
        if (isCompressed) {
            storedValue = await this.#compress(value);
        }
        const item = { 
            value: storedValue, 
            expires,
            compressed: isCompressed
        };
        this.cache[key] = item;
        if (this.useLocalStorage) {
            this.#saveToLocalStorage(key, item);
        }
        return value;
    }

    /**
     * キャッシュから値を取得
     * @param {string} key キャッシュのキー
     * @return {Promise<any|undefined>} キャッシュされた値、存在しない場合はundefined
     */
    async get(key) {
        const item = this.cache[key];
        if (!item) return undefined;
        if (Date.now() > item.expires) {
            delete this.cache[key];
            if (this.useLocalStorage) {
                this.del(key);
            }
            return undefined;
        }
        if (item.compressed) {
            return await this.#decompress(item.value);
        }
        return item.value;
    }

    /**
     * キャッシュからキーを削除
     * @param {string} key キャッシュのキー
     * @return {Promise<boolean>} 常にtrue
     */
    async del(key) {
        delete this.cache[key];
        if (this.useLocalStorage) {
            localStorage.removeItem(`${this.storagePrefix}${key}`);
        }
        return true;
    }

    /**
     * キャッシュを完全にクリア
     * @return {Promise<boolean>}
     */
    async clear() {
        this.cache = {};
        if (this.useLocalStorage && typeof localStorage !== 'undefined') {
            try {
                const keysToRemove = [];
                for (let i = 0; i < localStorage.length; i++) {
                    const key = localStorage.key(i);
                    if (key && key.startsWith(this.storagePrefix)) {
                        keysToRemove.push(key);
                    }
                }
                keysToRemove.forEach(key => {
                    localStorage.removeItem(key);
                });
            } catch (error) {
                return false;
            }
        }
        return true;
    }

    /**
     * キャッシュの存在を確認
     * @param {string} key キャッシュのキー
     * @return {boolean} キャッシュが存在する場合はtrue、存在しない場合はfalse
     */
    has(key) {
        const item = this.cache[key];
        if (!item) return false;
        if (Date.now() > item.expires) {
            this.del(key);
            return false;
        }
        return true;
    }
}
