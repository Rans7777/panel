import md5 from "js-md5";

export default class General {
    /**
     * ハッシュキーを生成
     * @param {string} str ハッシュキーに変換する文字列
     * @return {string} ハッシュキー
     */
    hashKey(str) {
        return md5(str);
    }
}
