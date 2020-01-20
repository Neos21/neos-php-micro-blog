<?php

/*!
 * Neo's PHP Twitter
 * 
 * Neo (@Neos21) http://neo.s21.xrea.com/
 */

// この場でタイムゾーンを変更する
date_default_timezone_set('Asia/Tokyo');


// グローバル変数 ($GLOBALS['変数名'] で参照)
// ======================================================================

// Private ディレクトリのパス (末尾スラッシュなし) : この配下に「クレデンシャルファイル」と「ツイートファイル」を配置する
$PRIVATE_DIRECTORY_PATH = '../private';
// クレデンシャルファイル : 投稿用パスワードが記載されたファイル名
$CREDENTIAL_FILE_NAME = 'credential.txt';
// ツイートファイル : ツイートを記録するファイルの接頭辞
$TWEETS_FILE_NAME_PREFIX = 'tweets-';
// タイトル
$PAGE_TITLE = "Neo's PHP Twitter";


// GET の場合 : ページ表示
// ======================================================================

if($_SERVER['REQUEST_METHOD'] === 'GET') {
  outputHtmlHeader();
  outputAdminForm();
  outputTweets();
  outputArchives();
  outputHtmlFooter();
  return;
}


// POST の場合
// ======================================================================

if($_SERVER['REQUEST_METHOD'] === 'POST') {
  if(!isValidPostParameters()) {
    return;
  }
  
  // ツイートをファイルに書き込む
  if(!writeTweet()) {
    return;
  }
  
  if(isGui()) {
    // ブラウザからの場合は POST 元に戻る
    $url = $_SERVER['HTTP_REFERER'];
    header('Location: ' . $url);
  }
  else {
    // そうでなければ JSON でレスポンスする
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(array('result' => 'Success'));
  }
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
  font-size: 1rem;
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
  <textarea name="tweet" autocomplete="off"></textarea>
  <input type="submit" value="!">
</form>
EOL;
  }
  else {
    echo '<h1>' . $GLOBALS['PAGE_TITLE'] . '</h1>';
  }
}

/** ツイートを表示する */
function outputTweets() {
  // 現在年月表示時で管理用パラメータがある場合は何も出力しない
  if( (!isset($_GET['view']) || empty($_GET['view'])) && (isset($_GET['credential']) && !empty($_GET['credential'])) ) {
    return;
  }
  
  $yearMonth = '';
  $tweetsFilePath = '';
  if(isset($_GET['view']) && preg_match('/^[0-9]{4}-[0-9]{2}$/', $_GET['view'])) {
    // 'YYYY-MM' のパラメータ指定があればその年月のファイルを取得する
    $yearMonth = $_GET['view'];
    $tweetsFilePath = getTweetsFilePath($_GET['view']);
  }
  else {
    // デフォルトは現在年月のファイル・存在しなければ作成する
    $yearMonth = getCurrentYearMonth();
    $tweetsFilePath = getCurrentTweetsFilePath();
  }
  
  if($tweetsFilePath === '') {
    echo '<p>File [' . $yearMonth . '] does not exist.</p>';
    return;
  }
  
  echo '<dl>';
  $tweetsFile = fopen($tweetsFilePath, 'r');  // 読取専用
  $isEmpty = true;  // 空ファイルかどうかの判定
  
  // 1行ずつ取り出す
  while(!feof($tweetsFile)) {
    $line = fgets($tweetsFile);
    if(trim($line) === '') {
      continue;
    }
    $lineArray = explode("\t", $line);
    $dateTime = $lineArray[0];
    $tweet    = $lineArray[1];
    echo '<dt><time>' . $dateTime . '</time></dt>';
    echo '<dd><span>' . $tweet    . '</span></dd>';
    $isEmpty = false;
  }
  fclose($tweetsFile);
  if($isEmpty) {
    echo '<dt><time>' . $yearMonth . '-00 00:00:00</time></dt>';
    echo '<dd><span>No Tweets</span></dd>';
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
  
  $logFilePaths = glob($GLOBALS['PRIVATE_DIRECTORY_PATH'] . '/' . $GLOBALS['TWEETS_FILE_NAME_PREFIX'] . '*.txt');
  echo '<ul>';
  foreach($logFilePaths as $logFilePath) {
    // ファイル名から年月部分を切り出す
    $yearMonth = preg_replace('/' . $GLOBALS['TWEETS_FILE_NAME_PREFIX'] . '/', '', basename($logFilePath, '.txt'));
    echo '<li><a href="' . basename(__FILE__) . '?view=' . $yearMonth . $credentialParam . '">' . $yearMonth . '</a></li>';
  }
  echo '<li><a href="' . basename(__FILE__) . preg_replace('/^\&/', '?', $credentialParam) . '">Top</a></li>';
  echo '</ul>';
}

/** GUI (ブラウザ) からのリクエストかどうかを判定する */
function isGui() {
  return isset($_POST['is_gui']) && !empty($_POST['is_gui']);
}

/** エラーレスポンスを返す */
function responseError(string $errorMessage) {
  if(isGui()) {
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

/** POST パラメータをチェックする */
function isValidPostParameters() {
  if(!isset($_POST['credential']) || trim($_POST['credential']) === '') {
    responseError('No Credential');
    return false;
  }
  if(!isset($_POST['tweet']) || trim($_POST['tweet']) === '') {
    responseError('No Tweet');
    return false;
  }
  
  // ファイルの1行目にパスワードが記されている
  $credentialFile = fopen($GLOBALS['PRIVATE_DIRECTORY_PATH'] . '/' . $GLOBALS['CREDENTIAL_FILE_NAME'], 'r');
  // 改行コードを除去する
  $credential = trim(fgets($credentialFile));
  fclose($credentialFile);
  // パスワードチェック
  if(trim($_POST['credential']) !== $credential) {
    responseError('Invalid Credential');
    return false;
  }
  
  return true;
}

/** ツイートをファイルに書き込む */
function writeTweet() {
  // ツイートをトリム・エスケープする
  $tweet = htmlspecialchars(trim($_POST['tweet']), ENT_QUOTES, 'UTF-8');
  // 改行コードを br 要素に変換する
  $tweet = preg_replace("/\r\n|\r|\n/", '<br>', $tweet);
  // URL をリンクに変換する
  $tweet = preg_replace('/((?:https?|ftp):\/\/[-_.!~*\'()a-zA-Z0-9;\/?:@&=+$,%#]+)/', '<a href="$1">$1</a>', $tweet);
  
  // 日時を取得する
  $currentDateTime = date('Y-m-d H:i:s');
  // 現在年月のファイルパスを取得する
  $tweetsFilePath = getCurrentTweetsFilePath();
  // ファイルの1行目に追記する
  $originalTweets = file_get_contents($tweetsFilePath);
  $tweets = $currentDateTime . "\t" . $tweet . "\n" . $originalTweets;
  $result = file_put_contents($tweetsFilePath, $tweets);
  if(!$result) {
    responseError('Failed to write tweets file');
  }
  return $result;
}

/** 第1引数で指定された年月のツイートファイルが存在すればパスを返す */
function getTweetsFilePath(string $yearMonth) {
  $tweetsFilePath = $GLOBALS['PRIVATE_DIRECTORY_PATH'] . '/' . $GLOBALS['TWEETS_FILE_NAME_PREFIX'] . $yearMonth . '.txt';
  return file_exists($tweetsFilePath) ? $tweetsFilePath : '';
}

/** 現在年月のツイートファイルのパスを返す・存在しない場合は作成する */
function getCurrentTweetsFilePath() {
  $tweetsFilePath = $GLOBALS['PRIVATE_DIRECTORY_PATH'] . '/' . $GLOBALS['TWEETS_FILE_NAME_PREFIX'] . getCurrentYearMonth() . '.txt';
  if(!file_exists($tweetsFilePath)) {
    touch($tweetsFilePath);
  }
  return $tweetsFilePath;
}

?>
