<?php

/*!
 * Neo's PHP Micro Blog
 * 
 * Neo (@Neos21) http://neo.s21.xrea.com/
 */

// この場でタイムゾーンを変更する
date_default_timezone_set('Asia/Tokyo');


// グローバル変数 ($GLOBALS['変数名'] で参照)
// ======================================================================

// Private ディレクトリのパス (末尾スラッシュなし) : この配下に「クレデンシャルファイル」と「投稿ファイル」を配置する
$PRIVATE_DIRECTORY_PATH = '../private';
// クレデンシャルファイル : 投稿用パスワードが記載されたファイル名
$CREDENTIAL_FILE_NAME = 'credential.txt';
// 投稿ファイル : 投稿を記録するファイルの接頭辞
$POSTS_FILE_NAME_PREFIX = 'posts-';
// title 要素に指定するタイトル
$PAGE_TITLE = '&#65279;';
// 見出しに指定するタイトル
$HEADLINE_TITLE = 'Do Well';
// 見出しに指定するリンク URL
$HEADLINE_URL = 'http://neo.s21.xrea.com/';


// POST か、GET かつ投$稿パラメータが付いている場合
// ======================================================================

if($_SERVER['REQUEST_METHOD'] === 'POST' || ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['mode']) && $_GET['mode'] === 'post')) {
  if(!isValidPostParameters()) {
    return;
  }
  
  // 投稿をファイルに書き込む
  if(!writePost()) {
    return;
  }
  
  if(isGui()) {
    // ブラウザから POST メソッドを使った場合は POST 元に戻る
    $url = $_SERVER['HTTP_REFERER'];
    header('Location: ' . $url);
  }
  else if($_SERVER['REQUEST_METHOD'] === 'GET') {
    // GET メソッドの場合はページを表示する
    outputHtmlHeader();
    outputAdminForm();
    outputPosts();
    outputArchives();
    outputHtmlFooter();
  }
  else {
    // そうでなければ JSON でレスポンスする
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(array('result' => 'Success'));
  }
  
  return;
}


// その他の GET の場合 : ページ表示
// ======================================================================

if($_SERVER['REQUEST_METHOD'] === 'GET') {
  outputHtmlHeader();
  outputAdminForm();
  outputPosts();
  outputArchives();
  outputHtmlFooter();
  return;
}


// 関数
// ======================================================================

/** HTML ヘッダを出力する */
function outputHtmlHeader() {
  echo <<<EOL
<!DOCTYPE html>
<html lang="ja">
  <head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=Edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$GLOBALS['PAGE_TITLE']}</title>
    <style>

@font-face {
  font-family: "Yu Gothic";
  src: local("Yu Gothic Medium"), local("YuGothic-Medium");
}

@font-face {
  font-family: "Yu Gothic";
  src: local("Yu Gothic Bold"), local("YuGothic-Bold");
  font-weight: bold;
}

*,
::before,
::after {
  box-sizing: border-box;
}

html {
  font-family: -apple-system, BlinkMacSystemFont, "Helvetica Neue", Helvetica, YuGothic, "Yu Gothic", "Hiragino Sans", "Hiragino Kaku Gothic ProN", Meiryo, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji";
  font-size: 16px;
  text-decoration-skip-ink: none;
  -webkit-text-size-adjust: 100%;
  -webkit-text-decoration-skip: objects;
  word-break: break-all;
  line-height: 1.3;
  background: #000;
  overflow-x: hidden;
  overflow-y: scroll;
  -webkit-overflow-scrolling: touch;
  cursor: default;
}

html,
a {
  color: #0d0;
}

h1 {
  font-size: 1rem;
}

h1 a {
  text-decoration: none;
}

h1 a:hover {
  text-decoration: underline;
}

textarea,
input {
  margin: 0;
  border: 1px solid #0c0;
  border-radius: 0;
  padding: .25rem .5rem;
  color: inherit;
  font-size: 16px;
  font-family: inherit;
  background: transparent;
  vertical-align: top;
  outline: none;
}

form {
  font-size: 0;
}

textarea {
  width: calc(100% - 2.5rem);
  height: 7rem;
  resize: none;
}

input {
  margin-left: -1px;
  width: calc(2.5rem + 1px);
  height: 7rem;
}

dl {
  border-top: 1px solid #0c0;
}

dt {
  padding: .5rem 0 0;
}

dd {
  margin: 0;
  border-bottom: 1px solid #0c0;
  padding: .5rem 0;
}

@media (min-width: 576px) {
  dl {
    display: flex;
    flex-wrap: wrap;
  }
  
  dt {
    margin: 0;
    border-bottom: 1px solid #0c0;
    padding: .5rem 0;
    width: 12rem;
  }
  
  dd {
    width: calc(100% - 12rem);
  }
}

    </style>
  </head>
  <body>
EOL;
}

/** HTML フッタを出力する */
function outputHtmlFooter() {
  echo <<<EOL
  </body>
</html>
EOL;
}

/** クレデンシャル値があればフォームを表示・そうでなければタイトルを表示する */
function outputAdminForm() {
  if(isset($_GET['credential']) && !empty($_GET['credential'])) {
    $credential = $_GET['credential'];
    echo <<<EOL
<form action="index.php" method="POST" autocomplete="off">
  <input type="hidden" name="credential" value="$credential">
  <input type="hidden" name="is_gui" value="true">
  <textarea name="text" autocomplete="off"></textarea>
  <input type="submit" value="!">
</form>
EOL;
  }
  else {
    echo '<h1>';
    if(!empty($GLOBALS['HEADLINE_URL'])) {
      echo '<a href="' . $GLOBALS['HEADLINE_URL'] . '">';
    }
    echo $GLOBALS['HEADLINE_TITLE'];
    if(!empty($GLOBALS['HEADLINE_URL'])) {
      echo '</a>';
    }
    echo '</h1>';
  }
}

/** 投稿を表示する */
function outputPosts() {
  // 表示対象年月の指定がなければ何も出力しない
  if(!isset($_GET['view']) || empty($_GET['view'])) {
    return;
  }
  
  $yearMonth = '';
  $postsFilePath = '';
  
  // 'YYYY-MM' のパラメータ指定があればその年月のファイルを取得する
  if(isset($_GET['view']) && preg_match('/^[0-9]{4}-[0-9]{2}$/', $_GET['view'])) {
    $yearMonth = $_GET['view'];
    $postsFilePath = getPostsFilePath($_GET['view']);
  }
  
  if($postsFilePath === '') {
    echo '<p>File does not exist.</p>';
    return;
  }
  
  echo '<dl>';
  $postsFile = fopen($postsFilePath, 'r');  // 読取専用
  $isEmpty = true;  // 空ファイルかどうかの判定
  
  // 1行ずつ取り出す
  while(!feof($postsFile)) {
    $line = fgets($postsFile);
    if(trim($line) === '') {
      continue;
    }
    $lineArray = explode("\t", $line);
    $dateTime = $lineArray[0];
    $post     = $lineArray[1];
    echo '<dt><time>' . $dateTime . '</time></dt>';
    echo '<dd><span>' . $post     . '</span></dd>';
    $isEmpty = false;
  }
  fclose($postsFile);
  if($isEmpty) {
    echo '<dt><time>' . $yearMonth . '-00 00:00:00</time></dt>';
    echo '<dd><span>No Posts</span></dd>';
  }
  echo '</dl>';
}

/** 過去ログのリンクを表示する */
function outputArchives() {
  // 管理用パラメータがあればそれをリンクに引き継ぐ
  $credentialParam = '';
  if(isset($_GET['credential']) && !empty(trim($_GET['credential']))) {
    $credentialParam = '&credential=' . trim($_GET['credential']);
  }
  
  $logFilePaths = glob($GLOBALS['PRIVATE_DIRECTORY_PATH'] . '/' . $GLOBALS['POSTS_FILE_NAME_PREFIX'] . '*.txt');
  echo '<ul>';
  foreach($logFilePaths as $logFilePath) {
    // ファイル名から年月部分を切り出す
    $yearMonth = preg_replace('/' . $GLOBALS['POSTS_FILE_NAME_PREFIX'] . '/', '', basename($logFilePath, '.txt'));
    echo '<li><a href="' . basename(__FILE__) . '?view=' . $yearMonth . $credentialParam . '">' . $yearMonth . '</a></li>';
  }
  echo '<li><a href="' . basename(__FILE__) . preg_replace('/^\&/', '?', $credentialParam) . '">Top</a></li>';
  echo '</ul>';
}

/** GUI (ブラウザ) からのリクエストかどうかを判定する */
function isGui() {
  return getPostOrGetParameter('is_gui') !== '';  // パラメータが空文字でなければ (何かあれば) GUI からとする
}

/** エラーレスポンスを返す */
function responseError(string $errorMessage) {
  if(isGui() || ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['mode']) && $_GET['mode'] === 'post')) {
    outputHtmlHeader();
    echo '<h1>Error : ' . $errorMessage . '</h1>';
    echo '<p><a href="javascript:history.back();">Back</a></p>';
    outputHtmlFooter();
  }
  else {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(array('error' => $errorMessage));
  }
}

/** 現在の年月を 'YYYY-MM' 形式で返す */
function getCurrentYearMonth() {
  return date('Y-m');
}

/** POST か GET から指定のパラメータを取得する (POST 優先・trim 済の値を返す) */
function getPostOrGetParameter(string $parameterName) {
  if(isset($_POST[$parameterName]) && trim($_POST[$parameterName]) !== '') {
    return trim($_POST[$parameterName]);
  }
  if(isset($_GET[$parameterName]) && trim($_GET[$parameterName]) !== '') {
    return trim($_GET[$parameterName]);
  }
  return '';
}

/** POST パラメータをチェックする */
function isValidPostParameters() {
  if(getPostOrGetParameter('credential') === '') {
    responseError('No Credential');
    return false;
  }
  if(getPostOrGetParameter('text') === '') {
    responseError('No Text');
    return false;
  }
  
  // ファイルの1行目にパスワードが記されている
  $credentialFile = fopen($GLOBALS['PRIVATE_DIRECTORY_PATH'] . '/' . $GLOBALS['CREDENTIAL_FILE_NAME'], 'r');
  // 改行コードを除去する
  $credential = trim(fgets($credentialFile));
  fclose($credentialFile);
  // パスワードチェック
  if(getPostOrGetParameter('credential') !== $credential) {
    responseError('Invalid Credential');
    return false;
  }
  
  return true;
}

/** 投稿をファイルに書き込む */
function writePost() {
  // 投稿をトリム・エスケープする
  $text = htmlspecialchars(getPostOrGetParameter('text'), ENT_QUOTES, 'UTF-8');
  // 改行コードを br 要素に変換する
  $text = preg_replace("/\r\n|\r|\n/", '<br>', $text);
  // URL をリンクに変換する
  $text = preg_replace('/((?:https?|ftp):\/\/[-_.!~*\'()a-zA-Z0-9;\/?:@&=+$,%#]+)/', '<a href="$1">$1</a>', $text);
  
  // 日時を取得する
  $currentDateTime = date('Y-m-d H:i:s');
  // 現在年月のファイルパスを取得する
  $postsFilePath = getCurrentPostsFilePath();
  // ファイルの中身を取得する
  $originalPosts = file_get_contents($postsFilePath);
  // ファイルの中身が空でなければ重複投稿チェックを行う
  if(!empty(trim($originalPosts))) {
    $firstLine = explode("\n", $originalPosts)[0];
    $firstLinePost = explode("\t", $firstLine)[1];
    if($text === $firstLinePost) {
      responseError('This post is already posted');
      return false;
    }
  }
  
  // ファイルの1行目に追記する
  $posts = $currentDateTime . "\t" . $text . "\n" . $originalPosts;
  $result = file_put_contents($postsFilePath, $posts);
  if(!$result) {
    responseError('Failed to write posts file');
    return false;
  }
  return $result;
}

/** 第1引数で指定された年月の投稿ファイルが存在すればパスを返す */
function getPostsFilePath(string $yearMonth) {
  $postsFilePath = $GLOBALS['PRIVATE_DIRECTORY_PATH'] . '/' . $GLOBALS['POSTS_FILE_NAME_PREFIX'] . $yearMonth . '.txt';
  return file_exists($postsFilePath) ? $postsFilePath : '';
}

/** 現在年月の投稿ファイルのパスを返す・存在しない場合は作成する */
function getCurrentPostsFilePath() {
  $postsFilePath = $GLOBALS['PRIVATE_DIRECTORY_PATH'] . '/' . $GLOBALS['POSTS_FILE_NAME_PREFIX'] . getCurrentYearMonth() . '.txt';
  if(!file_exists($postsFilePath)) {
    touch($postsFilePath);
  }
  return $postsFilePath;
}

?>
