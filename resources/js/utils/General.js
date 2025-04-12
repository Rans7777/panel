export default class General {
    /**
     * ハッシュキーを生成
     * @param {string} str ハッシュキーに変換する文字列
     * @return {string} ハッシュキー
     */
    hashKey(str) {
        return window.md5(str);
    }

    /**
     * 価格を日本円形式にフォーマット
     * @param {number} price 価格
     * @return {string} フォーマットされた価格
     */
    static formatPrice(price) {
        return new Intl.NumberFormat('ja-JP', {
          style: 'currency',
          currency: 'JPY'
        }).format(price);
    }
}
