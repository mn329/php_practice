<?php
// 日程の追加（POST → 処理 → plan.php へリダイレクト）
require __DIR__ . '/../src/bootstrap.php';

use App\ItemRepository;
use App\PlaceLocator;
use Carbon\Carbon;

$user = require_login();
require_post();
$plan = require_own_plan($user);
$back = 'plan.php?id=' . (int) $plan['id'];

// 地図のおすすめスポットから追加した場合は地図の位置へ戻る（外部サイトへ飛ばされないよう形式をチェック）
if (input($_POST, 'return') === 'map') {
    $back .= '#map';
}

$dayCount = (int) Carbon::parse($plan['start_date'])->diffInDays(Carbon::parse($plan['end_date'])) + 1;

$data = [
    'day'   => (int) ($_POST['day'] ?? 0),
    'time'  => input($_POST, 'time'),
    'place' => input($_POST, 'place'),
    'memo'  => input($_POST, 'memo'),
    'cost'  => input($_POST, 'cost'),
];

$errors = [];
if ($data['day'] < 1 || $data['day'] > $dayCount) {
    $errors[] = '日付を選び直してください。';
}
if ($data['time'] !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $data['time'])) {
    $errors[] = '時刻の形式が正しくありません。';
}
if ($data['place'] === '' || mb_strlen($data['place']) > 50) {
    $errors[] = '場所・やることは 1〜50 文字で入力してください。';
}
if (mb_strlen($data['memo']) > 200) {
    $errors[] = 'メモは 200 文字以内で入力してください。';
}
if ($data['cost'] === '') {
    $data['cost'] = '0';
}
if (!ctype_digit($data['cost']) || (int) $data['cost'] > 10_000_000) {
    $errors[] = '費用は 0 以上の整数（円）で入力してください。';
}

if ($errors) {
    foreach ($errors as $e) {
        flash('error', $e);
    }
    redirect($back);
}

// 地図用の緯度・経度
// 入力候補やおすすめスポットから選んだ場合は送られてきた座標を使い、無ければ地名から調べる
$lat = $_POST['lat'] ?? '';
$lng = $_POST['lng'] ?? '';
if (is_numeric($lat) && is_numeric($lng) && abs((float) $lat) <= 90 && abs((float) $lng) <= 180) {
    $location = ['lat' => (float) $lat, 'lng' => (float) $lng];
} else {
    $center = plan_location($plan);
    $location = (new PlaceLocator())->locate($data['place'], $plan['destination'], $center);
}
$data['lat']  = $location['lat'] ?? null;
$data['lng']  = $location['lng'] ?? null;
$data['cost'] = (int) $data['cost'];

(new ItemRepository())->create((int) $plan['id'], $data);

flash('success', '「' . $data['place'] . '」を追加しました。' . ($location ? '' : '（地図上の場所は見つかりませんでした）'));
redirect($back);
