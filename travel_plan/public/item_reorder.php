<?php
// ドラッグ＆ドロップの並び替えを保存（JavaScript の fetch から POST され、JSON を返す）
require __DIR__ . '/../src/bootstrap.php';

use App\ItemRepository;
use App\PlanRepository;
use Carbon\Carbon;

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || current_user() === null) {
    http_response_code(403);
    exit(json_encode(['ok' => false]));
}
verify_csrf();

$plan = (new PlanRepository())->find((int) ($_POST['plan_id'] ?? 0), current_user()['id']);
if (!$plan) {
    http_response_code(404);
    exit(json_encode(['ok' => false]));
}

$dayCount = (int) Carbon::parse($plan['start_date'])->diffInDays(Carbon::parse($plan['end_date'])) + 1;

// days[日] = [予定 ID, ...] の形で受け取る
$days = [];
foreach ((array) ($_POST['days'] ?? []) as $day => $ids) {
    $day = (int) $day;
    if ($day < 1 || $day > $dayCount || !is_array($ids)) {
        http_response_code(400);
        exit(json_encode(['ok' => false]));
    }
    $days[$day] = array_map('intval', $ids);
}

// 他のプランの予定 ID が混ざっていても、UPDATE の条件に plan_id があるので変更されない
(new ItemRepository())->reorder((int) $plan['id'], $days);

echo json_encode(['ok' => true]);
