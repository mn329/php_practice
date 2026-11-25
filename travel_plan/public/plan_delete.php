<?php
// プランの削除（POST → 処理 → 一覧へリダイレクト）
require __DIR__ . '/../src/bootstrap.php';

use App\ImageUploader;
use App\PlanRepository;

$user = require_login();
require_post();

$repo = new PlanRepository();
$plan = $repo->find((int) ($_POST['id'] ?? 0), $user['id']);
if (!$plan) {
    flash('error', 'プランが見つかりません。');
    redirect('index.php');
}

// 日程・持ち物は外部キーの ON DELETE CASCADE で一緒に削除される
$repo->delete((int) $plan['id'], $user['id']);
ImageUploader::delete($plan['cover_image']);

flash('success', '「' . $plan['title'] . '」を削除しました。');
redirect('index.php');
