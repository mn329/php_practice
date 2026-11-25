<?php
// 日程の削除（POST → 処理 → plan.php へリダイレクト）
require __DIR__ . '/../src/bootstrap.php';

use App\ItemRepository;

$user = require_login();
require_post();
$plan = require_own_plan($user);

(new ItemRepository())->delete((int) ($_POST['id'] ?? 0), (int) $plan['id']);

flash('success', '予定を削除しました。');
redirect('plan.php?id=' . (int) $plan['id']);
