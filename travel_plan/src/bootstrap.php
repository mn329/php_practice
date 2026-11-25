<?php
// すべてのページの先頭で読み込む初期化ファイル

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    exit('vendor/ がありません。travel_plan フォルダで composer install を実行してください。');
}
require_once $autoload;

date_default_timezone_set('Asia/Tokyo');
\Carbon\Carbon::setLocale('ja');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 設定値
const DB_PATH     = __DIR__ . '/../data/travel.sqlite';
const SCHEMA_PATH = __DIR__ . '/../schema.sql';
const UPLOAD_DIR  = __DIR__ . '/../public/uploads';
const UPLOAD_URL  = 'uploads';
const PER_PAGE    = 6;
