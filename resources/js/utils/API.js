export default class API {
    /**
     * アクセストークンを取得する
     * @return {Promise<string>} アクセストークン
     * @return {Error} 失敗したときのステータスコード
     */
    async getAccessToken() {
        try {
            const response = await axios.get('/api/create-access-token');
            return response.data.access_token;
        } catch (error) {
            return error.response.status;
        }
    }

    /**
     * トークンの有効性を確認する
     * @param {string} token トークン
     * @return {Promise<boolean>} 有効性
     * @return {Error} 失敗したときのステータスコード
     */
    async checkToken(token) {
        try {
            const response = await axios.get(`/api/access-token/${token}/validity`);
            return response.data.valid;
        } catch (error) {
            return error.response.status;
        }
    }

    /**
     * 商品情報を取得する
     * @param {string} productId 商品ID
     * @return {Promise<object>} 商品情報
     * @return {Error} 失敗したときのステータスコード
     */
    async getProductInfo(productId) {
        try {
            const response = await axios.get(`/api/products/${productId}`);
            return response.data.data;
        } catch (error) {
            return error.response.status;
        }
    }
}
