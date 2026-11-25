<?php
// 周辺のおすすめスポットを JSON で返す（GET：?lat=緯度&lng=経度）
// plan.php の地図から JavaScript で呼び出し、地図上にピンとして表示する
require __DIR__ . '/../src/bootstrap.php';

use App\NearbySpots;

header('Content-Type: application/json; charset=UTF-8');

if (current_user() === null) {
    http_response_code(403);
    exit('[]');
}

$lat = $_GET['lat'] ?? '';
$lng = $_GET['lng'] ?? '';
if (!is_numeric($lat) || !is_numeric($lng) || abs((float) $lat) > 90 || abs((float) $lng) > 180) {
    http_response_code(400);
    exit('[]');
}

// 少し地図を動かしただけで API を呼び直さないよう、約 100m 単位に丸めてキャッシュを使い回す
$spots = (new NearbySpots())->search(round((float) $lat, 3), round((float) $lng, 3));

echo json_encode(array_map(fn($s) => [
    'name'        => $s['name'],
    'description' => $s['description'],
    'category'    => $s['category'],
    'lat'         => $s['lat'],
    'lng'         => $s['lng'],
    'thumbnail'   => $s['thumbnail'],
    'url'         => $s['url'],
], $spots), JSON_UNESCAPED_UNICODE);
