# Neo's PHP Micro Blog

PHP 製のオレオレ・マイクロ・ブログ。

- __[Example](http://35.197.103.64/)__


## 機能

- パスワード認証により、自分だけが投稿できる、オリジナルの簡易マイクロ・ブログ
- 投稿は月ごとに生成するテキストファイルに保存する


## 設定

`index.php` の `グローバル変数` 部分を自環境に合わせて変更する。

- `$PRIVATE_DIRECTORY_PATH`
    - クレデンシャルファイルや投稿ファイルを格納する「プライベートディレクトリ」のパス。PHP の配置先から見た相対パスで記載できる
    - ex. Apache サーバの `/var/www/html/` に `index.php` を配置した場合、`'../private'` と指定すれば、`/var/www/private/` ディレクトリ配下を参照するようになる
- `$CREDENTIAL_FILE_NAME`
    - 投稿用パスワードを書いた「クレデンシャルファイル」の名前を記す
    - `$PRIVATE_DIRECTORY_PATH` と結合して参照するので、デフォルトの記述のままでいけば `/var/www/private/credential.txt` を参照することになる
- `$POSTS_FILE_NAME_PREFIX`
    - 月ごとの投稿を記録した「投稿ファイル」の接頭辞を指定する
    - `$PRIVATE_DIRECTORY_PATH` と結合するので、デフォルトの記述のままでいけば `/var/www/private/posts-2019-01.txt` といったファイルが生成される
- `$PAGE_TITLE`
    - `title` 要素、および `h1` 要素で示されるページタイトル


## 導入方法

1. Apache サーバの `/var/www/html/` 配下などに `index.php` を配置する
2. 変数 `$PRIVATE_DIRECTORY_PATH` + `$CREDENTIAL_FILE_NAME` のパスに、投稿用パスワードを記した1行のテキストファイル (クレデンシャルファイル) を作る
3. `index.php`、プライベートディレクトリ、クレデンシャルファイルのパーミッションを適宜設定する
4. `index.php` にアクセスする


## 管理者投稿の方法 (ブラウザ経由)

URL に `credential` パラメータを指定してアクセスすると、投稿フォームが表示される。

- ex. `http://example.com/index.php?credential=MY_CREDENTIAL`

`credential` パラメータで指定した投稿用パスワードの整合性は、POST 投稿時に「クレデンシャルファイル」と突合して確認する。


## 管理者投稿の方法 (`curl` 経由)

`curl` でも投稿できる。投稿するテキストはスペースなどを適宜 `%` エンコード (`%20`) すれば良い。

```sh
$ curl -X POST http://example.com/index.php -d 'credential=MY_CREDENTIAL&text=Test%20Test'

# 「Test Test」と投稿できる
```


## Author

[Neo](http://neo.s21.xrea.com/)


## Links

- [Neo's World](http://neo.s21.xrea.com/)
- [Corredor](http://neos21.hatenablog.com/)
- [Murga](http://neos21.hatenablog.jp/)
- [El Mylar](http://neos21.hateblo.jp/)
- [Neo's GitHub Pages](https://neos21.github.io/)
- [GitHub - Neos21](https://github.com/Neos21/)
