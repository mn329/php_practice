<?php
// 1 日の予定を移動距離が短い順に並び替える（POST → 処理 → plan.php へリダイレクト）
require __DIR__ . '/../src/bootstrap.php';

use App\ItemRepository;
use App\RouteOptimizer;

$user = require_login();
require_post();
$plan = require_own_plan($user);
$day  = (int) ($_POST['day'] ?? 0);

$repo   = new ItemRepository();
$items  = $repo->listByDay((int) $plan['id'], $day);
$before = RouteOptimizer::totalDistance($items);

$sorted = RouteOptimizer::optimize($items);
$after  = RouteOptimizer::totalDistance($sorted);
$repo->saveOrder((int) $plan['id'], $day, array_column($sorted, 'id'));

if ($after + 0.05 < $before) {
    flash('success', sprintf(
        '%d日目を最短ルート順に並び替えました（約 %.1f km → %.1f km）。時刻は必要に応じて修正してください。',
        $day, $before, $after
    ));
} else {
    flash('success', "{$day}日目はすでに効率のよい順番です。");
}
redirect('plan.php?id=' . (int) $plan['id']);
