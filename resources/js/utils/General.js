export default class General {
    /**
     * ハッシュキーを生成
     * @param {string} str ハッシュキーに変換する文字列
     * @return {string} ハッシュキー
     */
    hashKey(str) {
        return crypto.createHash('md5').update(str).digest('hex');
    }
}
