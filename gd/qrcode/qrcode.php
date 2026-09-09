<?php
require '../vendor/autoload.php';

// QRコード生成ライブラリの読み込み
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;

// QRコードのサイズ
$size = 300;
// QRコードのマージン
$margin = 10;

// URLを取得
$text = $_GET['url'] ?? '';
if (!$text) {
    http_response_code(400);
    echo 'URLが指定されていません。';
    exit;
}

// TODO: QRコード生成
$qrCode = new QrCode(
    data: $text,
    encoding: new Encoding('UTF-8'),
    size: $size,
    margin: $margin,
);

// TODO: PNGとして出力
$writer = new PngWriter();
$result = $writer->write($qrCode);

// ファイル保存（ドメイン名で保存。ホストが取れない場合はフォールバック）
$domain = parse_url($text, PHP_URL_HOST);
$filename = ($domain ?: 'qrcode') . '.png';
$result->saveToFile('../images/' . $filename);

// 画像出力（ブラウザ表示・ダウンロード用）
header('Content-Type: ' . $result->getMimeType());
echo $result->getString();

