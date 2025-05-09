export default class API {
    /**
     * アクセストークンを取得する
     * @return {Promise<string>} アクセストークン
     * @return {null} 失敗したときはnull
     */
    async getAccessToken() {
        const response = await axios.get('/api/create-access-token');
        return response.data.access_token ?? null;
    }

    /**
     * トークンの有効性を確認する
     * @param {string} token トークン
     * @return {Promise<boolean>} 有効性
     * @return {null} 失敗したときはnull
     */
    async checkToken(token) {
        const response = await axios.get(`/api/access-token/${token}/validity`);
        return response.data.valid ?? null;
    }

    /**
     * トークンの有効性を確認
     * @param {string} token トークン
     * @param {Cache} tokenValidityCache トークン有効性キャッシュ
     * @return {boolean} トークンが有効ならtrue、無効ならfalse
     */
    async validateTokenAfterError(token, tokenValidityCache, cacheKey) {
        if (!token) return false;
        try {
            const cachedValidity = await tokenValidityCache.get(cacheKey);
            if (cachedValidity !== undefined && cachedValidity !== null) {
                return cachedValidity;
            }
            const isValid = await this.checkToken(token);
            if (isValid) {
                await tokenValidityCache.set(cacheKey, true);
                return true;
            }
            return false;
        } catch (error) {
            return false;
        }
    }

    /**
     * 商品情報を取得する
     * @param {string} productId 商品ID
     * @return {Promise<object>} 商品情報
     * @return {null} 失敗したときはnull
     */
    async getProductInfo(productId) {
        const response = await axios.get(`/api/products/${productId}`);
        return response.data.data ?? null;
    }
}
