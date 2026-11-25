<?php
require __DIR__ . '/../src/bootstrap.php';

use App\ChecklistRepository;
use App\ItemRepository;
use App\NearbySpots;
use App\PackingAdvisor;
use App\PlanRepository;
use App\RouteOptimizer;
use App\Weather;
use Carbon\Carbon;

$user = require_login();

// GET パラメータでプランを指定（自分のプランだけ表示できる）
$id   = (int) ($_GET['id'] ?? 0);
$plan = (new PlanRepository())->find($id, $user['id']);
if (!$plan) {
    flash('error', 'プランが見つかりません。');
    redirect('index.php');
}

$itemRepo  = new ItemRepository();
$itemsByDay = $itemRepo->groupedByDay($id);
$totalCost = $itemRepo->totalCost($id);
$checklist = (new ChecklistRepository())->listByPlan($id);
$doneCount = count(array_filter($checklist, fn($c) => (int) $c['is_done'] === 1));
$location  = plan_location($plan); // 行き先の座標（天気・地図・おすすめスポットの基準）
$weather   = $location
    ? (new Weather())->forecast($location['lat'], $location['lng'])
    : ['error' => "「{$plan['destination']}」の場所が見つかりませんでした。編集画面の地図で位置を指定すると、天気と地図が表示されます。"];
$status    = trip_status($plan);

// 何日目 => 日付 の対応表
$start   = Carbon::parse($plan['start_date']);
$dayCount = (int) $start->diffInDays(Carbon::parse($plan['end_date'])) + 1;
$days = [];
for ($d = 1; $d <= $dayCount; $d++) {
    $days[$d] = $start->copy()->addDays($d - 1)->format('Y-m-d');
}

// 予算
$budget    = (int) $plan['budget'];
$remaining = $budget - $totalCost;
$usedRate  = $budget > 0 ? min(100, (int) round($totalCost / $budget * 100)) : 0;

// 1 日ごとの移動距離（直線）と、地図のピンを立てられる予定の数
$dayDistance = [];
$dayPoints = [];
foreach ($itemsByDay as $day => $items) {
    $dayDistance[$day] = RouteOptimizer::totalDistance($items);
    $dayPoints[$day] = count(array_filter($items, fn($i) => $i['lat'] !== null));
}

// 持ち物のおすすめ
$packing = (new PackingAdvisor())->suggest($plan, $weather['days'] ?? [], $location['country'] ?? '', $itemsByDay, $checklist);

// 地図に渡すデータ（ピンを立てるスポット）。$numbers は日程とピンに同じ番号を表示するため
$pins = [];
$numbers = [];
$no = 0;
foreach ($itemsByDay as $day => $items) {
    foreach ($items as $item) {
        $numbers[$item['id']] = ++$no;
        if ($item['lat'] !== null) {
            $pins[] = [
                'no'    => $no,
                'day'   => (int) $day,
                'lat'   => (float) $item['lat'],
                'lng'   => (float) $item['lng'],
                'label' => "{$day}日目 " . ($item['time'] !== '' ? $item['time'] . ' ' : '') . $item['place'],
            ];
        }
    }
}
$mapCenter = $location;

$pageTitle = $plan['title'];
$useMap = true;
require __DIR__ . '/../src/templates/header.php';
?>

<!-- プラン概要 -->
<section class="plan-hero card">
    <div class="plan-hero-cover">
        <?php if ($plan['cover_image']): ?>
            <img src="<?= h(UPLOAD_URL . '/' . $plan['cover_image']) ?>" alt="">
        <?php else: ?>
            <div class="cover-placeholder">🧳</div>
        <?php endif; ?>
    </div>
    <div class="plan-hero-body">
        <span class="badge badge-<?= h($status['class']) ?>"><?= h($status['label']) ?></span>
        <h1><?= h($plan['title']) ?></h1>
        <p class="muted">📍 <?= h($plan['destination']) ?>
            ・<?= h(jp_date($plan['start_date'])) ?> 〜 <?= h(jp_date($plan['end_date'])) ?>
            （<?= h(trip_length($plan)) ?>）</p>
        <?php if ($plan['memo'] !== ''): ?>
            <p class="memo"><?= nl2br(h($plan['memo'])) ?></p>
        <?php endif; ?>
        <div class="hero-buttons">
            <a href="plan_form.php?id=<?= (int) $id ?>" class="btn btn-small btn-outline">✏️ 編集</a>
            <form action="plan_delete.php" method="post" class="inline" onsubmit="return confirm('このプランを削除しますか？（元に戻せません）');">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $id ?>">
                <button type="submit" class="btn btn-small btn-danger">🗑 削除</button>
            </form>
        </div>
    </div>
</section>

<div class="plan-layout">
    <!-- 左：日程 -->
    <div class="plan-main">
        <section class="card">
            <h2>🗓️ 日程</h2>
            <p class="small muted">⠿ をドラッグすると順番や日を入れ替えられます。</p>
            <p id="reorder-message" class="small text-danger" hidden></p>
            <?php foreach ($days as $d => $date): ?>
                <div class="day">
                    <div class="day-head">
                        <h3><span class="day-no"><?= $d ?>日目</span> <?= h(jp_date($date)) ?></h3>
                        <?php if (($dayPoints[$d] ?? 0) >= 2): ?>
                            <span class="small muted">移動 約 <?= h(number_format($dayDistance[$d], 1)) ?> km（直線）</span>
                        <?php endif; ?>
                        <?php if (($dayPoints[$d] ?? 0) >= 3): ?>
                            <form action="item_optimize.php" method="post" class="inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="plan_id" value="<?= (int) $id ?>">
                                <input type="hidden" name="day" value="<?= $d ?>">
                                <button type="submit" class="btn btn-small btn-outline" title="最初の予定を出発地点にして、移動距離が短くなる順に並べ替えます">🔀 最短ルート順</button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <!-- 予定が無い日もドロップ先になるよう ul は常に出す -->
                    <ul class="timeline" data-day="<?= $d ?>">
                        <?php foreach ($itemsByDay[$d] ?? [] as $item): ?>
                            <li data-id="<?= (int) $item['id'] ?>">
                                <span class="handle" title="ドラッグして並び替え" aria-hidden="true">⠿</span>
                                <span class="num <?= $item['lat'] === null ? 'no-pin' : '' ?>" title="<?= $item['lat'] === null ? '地図上の場所が見つかりませんでした' : '地図のピン番号' ?>"><?= $numbers[$item['id']] ?></span>
                                <span class="time"><?= h($item['time'] !== '' ? $item['time'] : '--:--') ?></span>
                                <div class="timeline-body">
                                    <strong><?= h($item['place']) ?></strong>
                                    <?php if ((int) $item['cost'] > 0): ?>
                                        <span class="cost"><?= h(yen((int) $item['cost'])) ?></span>
                                    <?php endif; ?>
                                    <?php if ($item['memo'] !== ''): ?>
                                        <p class="small muted"><?= nl2br(h($item['memo'])) ?></p>
                                    <?php endif; ?>
                                </div>
                                <form action="item_delete.php" method="post" class="inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                    <input type="hidden" name="plan_id" value="<?= (int) $id ?>">
                                    <button type="submit" class="icon-button" title="削除" aria-label="削除">×</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if (empty($itemsByDay[$d])): ?>
                        <p class="muted small empty-day">予定はまだありません。</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <h3 class="sub-heading">予定を追加</h3>
            <form action="item_save.php" method="post" class="form item-form">
                <?= csrf_field() ?>
                <input type="hidden" name="plan_id" value="<?= (int) $id ?>">
                <div class="form-row">
                    <label>
                        日
                        <select name="day">
                            <?php foreach ($days as $d => $date): ?>
                                <option value="<?= $d ?>"><?= $d ?>日目（<?= h(jp_date($date)) ?>）</option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        時刻
                        <input type="time" name="time">
                    </label>
                </div>
                <label>
                    場所・やること <span class="req">必須</span>
                    <input type="text" name="place" maxlength="50" placeholder="例：清水寺" required
                           data-suggest="spot"
                           data-lat="<?= h((string) ($mapCenter['lat'] ?? '')) ?>"
                           data-lng="<?= h((string) ($mapCenter['lng'] ?? '')) ?>">
                </label>
                <input type="hidden" name="lat" value="">
                <input type="hidden" name="lng" value="">
                <div class="form-row">
                    <label>
                        費用（円）
                        <input type="number" name="cost" min="0" step="1" placeholder="0">
                    </label>
                    <label>
                        メモ
                        <input type="text" name="memo" maxlength="200" placeholder="例：拝観料 400円">
                    </label>
                </div>
                <button type="submit" class="btn">＋ 追加</button>
                <p class="small muted">観光地や店名を入力すると候補が出ます。地図にも自動でピンが立ちます。</p>
            </form>
        </section>

        <!-- 地図 -->
        <section class="card">
            <div class="section-head">
                <h2>🗺️ 地図</h2>
                <?php if ($mapCenter): ?>
                    <button type="button" id="spots-toggle" class="btn btn-small btn-outline" aria-pressed="false">📍 おすすめスポットを表示</button>
                <?php endif; ?>
            </div>
            <?php if ($mapCenter): ?>
                <div id="spot-filters" class="chips" hidden>
                    <button type="button" class="chip" data-cat="">すべて <span class="count"></span></button>
                    <?php foreach (NearbySpots::CATEGORIES as $key => $c): ?>
                        <button type="button" class="chip" data-cat="<?= h($key) ?>"><?= h($c['label']) ?> <span class="count"></span></button>
                    <?php endforeach; ?>
                </div>
                <div class="map-wrap">
                    <div id="map" class="map"></div>
                    <button type="button" id="spots-here" class="btn btn-small map-float" hidden>🔍 この周辺を探す</button>
                </div>
                <p id="spots-status" class="small muted">
                    <?= $pins ? '' : '日程に観光地を追加すると、ここにピンが表示されます。' ?>
                </p>
                <ul id="spot-list" class="spot-list" hidden></ul>
            <?php else: ?>
                <p class="muted small">行き先の場所が見つからないため地図を表示できません。<a href="plan_form.php?id=<?= (int) $id ?>">編集画面</a>の地図で位置を指定してください。</p>
            <?php endif; ?>
        </section>
    </div>

    <!-- 右：予算・天気・持ち物 -->
    <aside class="plan-side">
        <section class="card">
            <h2>💰 予算</h2>
            <p class="budget-total"><?= h(yen($totalCost)) ?> <span class="muted small">/ <?= $budget > 0 ? h(yen($budget)) : '予算未設定' ?></span></p>
            <?php if ($budget > 0): ?>
                <div class="progress <?= $remaining < 0 ? 'over' : '' ?>"><span style="width: <?= $usedRate ?>%"></span></div>
                <p class="small <?= $remaining < 0 ? 'text-danger' : 'muted' ?>">
                    <?= $remaining >= 0 ? '残り ' . h(yen($remaining)) : h(yen(-$remaining)) . ' オーバー！' ?>
                </p>
            <?php endif; ?>
        </section>

        <section class="card">
            <h2>🌤️ 現地の天気</h2>
            <?php if (isset($weather['error'])): ?>
                <p class="small muted"><?= h($weather['error']) ?></p>
            <?php else: ?>
                <p class="small muted"><?= h($plan['destination']) ?> の 16 日間予報</p>
                <?php
                $lastForecast = end($weather['days'])['date'];
                if ($plan['start_date'] > $lastForecast): ?>
                    <p class="small notice">出発日が近づくと（約 2 週間前から）旅行中の天気が表示されます。</p>
                <?php endif; ?>
                <ul class="weather-list">
                    <?php foreach ($weather['days'] as $w):
                        $inTrip = $w['date'] >= $plan['start_date'] && $w['date'] <= $plan['end_date']; ?>
                        <li class="<?= $inTrip ? 'in-trip' : '' ?>">
                            <span class="w-date"><?= h(jp_date($w['date'])) ?></span>
                            <span class="w-icon" title="<?= h($w['label']) ?>"><?= $w['icon'] ?></span>
                            <span class="w-temp"><?= h((string) round($w['max'])) ?>° / <?= h((string) round($w['min'])) ?>°</span>
                            <span class="w-rain">☂ <?= h((string) $w['rain']) ?>%</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="card">
            <h2>🎒 持ち物 <span class="small muted"><?= $doneCount ?> / <?= count($checklist) ?></span></h2>
            <?php if ($checklist): ?>
                <ul class="checklist">
                    <?php foreach ($checklist as $c): ?>
                        <li class="<?= $c['is_done'] ? 'done' : '' ?>">
                            <form action="checklist.php" method="post" class="inline grow">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                <input type="hidden" name="plan_id" value="<?= (int) $id ?>">
                                <button type="submit" class="check-button">
                                    <span class="box"><?= $c['is_done'] ? '✓' : '' ?></span><?= h($c['label']) ?>
                                </button>
                            </form>
                            <form action="checklist.php" method="post" class="inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                <input type="hidden" name="plan_id" value="<?= (int) $id ?>">
                                <button type="submit" class="icon-button" title="削除" aria-label="削除">×</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <form action="checklist.php" method="post" class="add-row">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="plan_id" value="<?= (int) $id ?>">
                <input type="text" name="label" maxlength="30" placeholder="例：充電器" required>
                <button type="submit" class="btn btn-small">追加</button>
            </form>
            <?php if ($packing): ?>
                <div class="packing">
                    <p class="small"><strong>💡 おすすめの持ち物</strong></p>
                    <?php foreach ($packing as $p): ?>
                        <form action="checklist.php" method="post" class="inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="plan_id" value="<?= (int) $id ?>">
                            <input type="hidden" name="label" value="<?= h($p['label']) ?>">
                            <button type="submit" class="chip" title="<?= h($p['reason']) ?>">＋ <?= h($p['label']) ?></button>
                        </form>
                    <?php endforeach; ?>
                    <ul class="packing-reasons small muted">
                        <?php foreach (array_unique(array_column($packing, 'reason')) as $reason): ?>
                            <li><?= h($reason) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <?php if (!$checklist): ?>
                <form action="checklist.php" method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="preset">
                    <input type="hidden" name="plan_id" value="<?= (int) $id ?>">
                    <button type="submit" class="link-button small">定番の持ち物をまとめて追加</button>
                </form>
            <?php endif; ?>
        </section>
    </aside>
</div>

<?php if ($mapCenter): ?>
<!-- 地図用のデータを PHP から JavaScript へ渡す -->
<script id="map-data" type="application/json"><?= json_encode([
    'center'      => $mapCenter + ['name' => $plan['destination']],
    'pins'        => $pins,
    'planId'      => $id,
    'csrfToken'   => csrf_token(),
    'days'        => array_map(fn($d, $date) => ['day' => $d, 'label' => "{$d}日目（" . jp_date($date) . '）'], array_keys($days), $days),
    'addedPlaces' => array_column(array_merge([], ...array_values($itemsByDay)), 'place'),
    'categories'  => array_map(fn($c) => $c['label'], NearbySpots::CATEGORIES),
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
<script src="<?= h(asset('js/plan-map.js')) ?>" defer></script>
<?php endif; ?>

<!-- ドラッグ＆ドロップ（SortableJS）と入力候補 -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.6/Sortable.min.js"></script>
<script src="<?= h(asset('js/suggest.js')) ?>"></script>
<script>
// 並び替えたら item_reorder.php に保存して再読み込み（地図の番号や距離も更新される）
const saveOrder = async () => {
    const body = new FormData();
    body.append('csrf_token', <?= json_encode(csrf_token()) ?>);
    body.append('plan_id', <?= (int) $id ?>);
    document.querySelectorAll('.timeline[data-day]').forEach((ul) => {
        ul.querySelectorAll('li[data-id]').forEach((li) => {
            body.append(`days[${ul.dataset.day}][]`, li.dataset.id);
        });
    });
    try {
        const res = await fetch('item_reorder.php', { method: 'POST', body });
        if (!res.ok) throw new Error();
        location.reload();
    } catch {
        const msg = document.getElementById('reorder-message');
        msg.textContent = '並び替えを保存できませんでした。ページを再読み込みしてやり直してください。';
        msg.hidden = false;
    }
};

document.querySelectorAll('.timeline[data-day]').forEach((ul) => {
    new Sortable(ul, {
        group: 'items',      // 別の日へも移動できる
        handle: '.handle',
        animation: 150,
        ghostClass: 'dragging',
        onEnd: (e) => {
            if (e.from !== e.to || e.oldIndex !== e.newIndex) saveOrder();
        },
    });
});
</script>

<?php require __DIR__ . '/../src/templates/footer.php'; ?>
