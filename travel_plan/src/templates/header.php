<?php
// 共通ヘッダー
// 読み込む前に $pageTitle を設定しておく
$user = current_user();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h(isset($pageTitle) ? $pageTitle . ' | TabiPlan' : 'TabiPlan') ?></title>
    <?php if (!empty($useMap)): ?>
        <!-- 地図ライブラリ Leaflet（無料・API キー不要） -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
        <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js" defer></script>
    <?php endif; ?>
    <link rel="stylesheet" href="<?= h(asset('css/style.css')) ?>">
</head>
<body>
<header class="site-header">
    <div class="container header-inner">
        <a href="index.php" class="logo">✈️ TabiPlan</a>
        <nav class="nav">
            <?php if ($user): ?>
                <span class="nav-user"><?= h($user['name']) ?> さん</span>
                <a href="index.php">プラン一覧</a>
                <a href="plan_form.php" class="btn btn-small">＋ 新しい旅</a>
                <form action="logout.php" method="post" class="inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="link-button">ログアウト</button>
                </form>
            <?php else: ?>
                <a href="login.php">ログイン</a>
                <a href="register.php" class="btn btn-small">新規登録</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<main class="container">
    <?php foreach (pull_flashes() as $flash): ?>
        <div class="flash flash-<?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
    <?php endforeach; ?>
