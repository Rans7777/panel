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
}
