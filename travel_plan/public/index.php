<?php
require __DIR__ . '/../src/bootstrap.php';

use App\PlanRepository;

$user = current_user();

if ($user) {
    // GET パラメータ：検索キーワードとページ番号
    $keyword = input($_GET, 'q');
    $page    = max(1, (int) ($_GET['page'] ?? 1));

    $repo       = new PlanRepository();
    $total      = $repo->count($user['id'], $keyword);
    $totalPages = max(1, (int) ceil($total / PER_PAGE));
    $page       = min($page, $totalPages);
    $plans      = $repo->search($user['id'], $keyword, PER_PAGE, ($page - 1) * PER_PAGE);
}

$pageTitle = $user ? 'プラン一覧' : null;
require __DIR__ . '/../src/templates/header.php';
?>

<?php if (!$user): ?>
    <!-- 未ログイン時のトップ -->
    <section class="hero">
        <h1>次の旅を、もっと楽しく。</h1>
        <p>日程・予算・持ち物・現地の天気を、ひとつのプランにまとめて管理できる旅行計画アプリです。</p>
        <div class="hero-actions">
            <a href="register.php" class="btn">無料ではじめる</a>
            <a href="login.php" class="btn btn-outline">ログイン</a>
        </div>
    </section>
    <section class="features">
        <div class="card feature"><span>🗓️</span><h3>日程づくり</h3><p>何日目の何時にどこへ行くかを整理。</p></div>
        <div class="card feature"><span>💰</span><h3>予算管理</h3><p>費用を入力すると予算との差を自動計算。</p></div>
        <div class="card feature"><span>🌤️</span><h3>現地の天気</h3><p>行き先の天気予報を自動で表示。</p></div>
        <div class="card feature"><span>🎒</span><h3>持ち物リスト</h3><p>準備したものにチェックして忘れ物防止。</p></div>
    </section>

<?php else: ?>
    <div class="page-head">
        <h1>わたしの旅行プラン</h1>
        <form action="index.php" method="get" class="search">
            <input type="search" name="q" value="<?= h($keyword) ?>" placeholder="タイトル・行き先で検索">
            <button type="submit" class="btn btn-small">検索</button>
        </form>
    </div>

    <?php if ($keyword !== ''): ?>
        <p class="muted">「<?= h($keyword) ?>」の検索結果：<?= $total ?> 件
            <a href="index.php" class="small">検索をクリア</a></p>
    <?php endif; ?>

    <?php if (!$plans): ?>
        <div class="card empty">
            <?php if ($keyword !== ''): ?>
                <p>該当するプランはありません。</p>
            <?php else: ?>
                <p>まだプランがありません。</p>
                <a href="plan_form.php" class="btn">＋ 最初の旅を計画する</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="plan-grid">
            <?php foreach ($plans as $plan): $status = trip_status($plan); ?>
                <a href="plan.php?id=<?= (int) $plan['id'] ?>" class="card plan-card">
                    <div class="plan-cover">
                        <?php if ($plan['cover_image']): ?>
                            <img src="<?= h(UPLOAD_URL . '/' . $plan['cover_image']) ?>" alt="">
                        <?php else: ?>
                            <div class="cover-placeholder">🧳</div>
                        <?php endif; ?>
                        <span class="badge badge-<?= h($status['class']) ?>"><?= h($status['label']) ?></span>
                    </div>
                    <div class="plan-body">
                        <h2><?= h($plan['title']) ?></h2>
                        <p class="muted">📍 <?= h($plan['destination']) ?></p>
                        <p class="small">
                            <?= h(jp_date($plan['start_date'])) ?> 〜 <?= h(jp_date($plan['end_date'])) ?>
                            ・<?= h(trip_length($plan)) ?>
                        </p>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="pagination">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <?php $query = http_build_query(['q' => $keyword, 'page' => $i]); ?>
                    <a href="index.php?<?= h($query) ?>" class="<?= $i === $page ? 'current' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/../src/templates/footer.php'; ?>
