<?php
// 入力候補を JSON で返す（GET：?type=city|spot&q=キーワード&lat=&lng=）
// JavaScript から呼び出す。外部 API を直接呼ばずにサーバー経由にすることで、結果をキャッシュできる
require __DIR__ . '/../src/bootstrap.php';

use App\PlaceSuggester;

header('Content-Type: application/json; charset=UTF-8');

if (current_user() === null) {
    http_response_code(403);
    exit('[]');
}

$type  = ($_GET['type'] ?? '') === 'spot' ? 'spot' : 'city';
$query = input($_GET, 'q');
$lat   = is_numeric($_GET['lat'] ?? null) ? (float) $_GET['lat'] : null;
$lng   = is_numeric($_GET['lng'] ?? null) ? (float) $_GET['lng'] : null;

if ($query === '' || mb_strlen($query) > 50) {
    exit('[]');
}

// 同じキーワードは 10 分間セッションにキャッシュ
$cacheKey = 'suggest_v2_' . md5("{$type}|{$query}|{$lat}|{$lng}");
$cached = $_SESSION[$cacheKey] ?? null;
if ($cached && $cached['expires'] > time()) {
    exit(json_encode($cached['data'], JSON_UNESCAPED_UNICODE));
}

$suggester = new PlaceSuggester();
$data = $type === 'spot' ? $suggester->spots($query, $lat, $lng) : $suggester->cities($query);

$_SESSION[$cacheKey] = ['expires' => time() + 600, 'data' => $data];
echo json_encode($data, JSON_UNESCAPED_UNICODE);
