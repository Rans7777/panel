import md5 from "https://cdn.jsdelivr.net/npm/js-md5@0.8.3/src/md5.min.js";

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
