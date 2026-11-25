<?php
require __DIR__ . '/../src/bootstrap.php';

use App\Geocoder;
use App\ImageUploader;
use App\PlanRepository;

$user = require_login();
$repo = new PlanRepository();

// ---- POST：作成 / 更新処理 ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $id   = (int) ($_POST['id'] ?? 0);
    $plan = $id ? $repo->find($id, $user['id']) : null;
    if ($id && !$plan) {
        flash('error', 'プランが見つかりません。');
        redirect('index.php');
    }
    $back = $id ? "plan_form.php?id={$id}" : 'plan_form.php';

    $data = [
        'title'       => input($_POST, 'title'),
        'destination' => input($_POST, 'destination'),
        'start_date'  => input($_POST, 'start_date'),
        'end_date'    => input($_POST, 'end_date'),
        'budget'      => input($_POST, 'budget'),
        'memo'        => input($_POST, 'memo'),
    ];

    // 行き先の座標（候補を選ぶ・地図をクリックすると JavaScript が hidden に入れる）
    $lat = $_POST['lat'] ?? '';
    $lng = $_POST['lng'] ?? '';
    $hasPoint = is_numeric($lat) && is_numeric($lng) && abs((float) $lat) <= 90 && abs((float) $lng) <= 180;
    $country = preg_match('/^[A-Z]{2}$/', $_POST['country'] ?? '') ? $_POST['country'] : '';

    // バリデーション
    $errors = [];
    if ($data['title'] === '' || mb_strlen($data['title']) > 50) {
        $errors[] = 'タイトルは 1〜50 文字で入力してください。';
    }
    if ($data['destination'] === '' || mb_strlen($data['destination']) > 30) {
        $errors[] = '行き先は 1〜30 文字で入力してください。';
    }
    if (!is_valid_date($data['start_date']) || !is_valid_date($data['end_date'])) {
        $errors[] = '出発日と帰着日を正しく入力してください。';
    } elseif ($data['end_date'] < $data['start_date']) {
        $errors[] = '帰着日は出発日以降にしてください。';
    } elseif ((new DateTime($data['start_date']))->diff(new DateTime($data['end_date']))->days > 30) {
        $errors[] = '旅行期間は 31 日以内にしてください。';
    }
    if ($data['budget'] === '') {
        $data['budget'] = '0';
    }
    if (!ctype_digit($data['budget']) || (int) $data['budget'] > 100_000_000) {
        $errors[] = '予算は 0 以上の整数（円）で入力してください。';
    }
    if (mb_strlen($data['memo']) > 1000) {
        $errors[] = 'メモは 1000 文字以内で入力してください。';
    }

    // 表紙画像（入力エラーが無いときだけ保存する）
    $cover = $plan['cover_image'] ?? null;
    $newCover = null;
    if (!$errors && ImageUploader::hasFile($_FILES['cover'] ?? null)) {
        try {
            $newCover = ImageUploader::save($_FILES['cover']);
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    }

    if ($errors) {
        foreach ($errors as $e) {
            flash('error', $e);
        }
        set_old($data + ['lat' => $lat, 'lng' => $lng, 'country' => $country]);
        redirect($back);
    }

    // 画像の差し替え・削除
    if ($newCover) {
        ImageUploader::delete($cover);
        $cover = $newCover;
    } elseif (!empty($_POST['remove_cover'])) {
        ImageUploader::delete($cover);
        $cover = null;
    }

    $data['budget'] = (int) $data['budget'];
    $data['cover_image'] = $cover;

    // 座標が送られていなければ地名から調べる（行き先を変えていなければ今の座標のまま）
    if ($hasPoint) {
        [$data['dest_lat'], $data['dest_lng'], $data['dest_country']] = [(float) $lat, (float) $lng, $country];
    } elseif ($plan && $plan['destination'] === $data['destination'] && $plan['dest_lat'] !== null) {
        [$data['dest_lat'], $data['dest_lng'], $data['dest_country']] = [$plan['dest_lat'], $plan['dest_lng'], $plan['dest_country']];
    } else {
        $geo = (new Geocoder())->locate($data['destination']);
        $data['dest_lat']     = $geo['lat'] ?? null;
        $data['dest_lng']     = $geo['lng'] ?? null;
        $data['dest_country'] = $geo['country'] ?? '';
        if ($geo === null) {
            flash('error', '行き先の場所が見つかりませんでした。編集画面の地図をクリックすると位置を指定できます。');
        }
    }

    if ($plan) {
        $repo->update($id, $user['id'], $data);
        flash('success', 'プランを更新しました。');
    } else {
        $id = $repo->create($user['id'], $data);
        flash('success', 'プランを作成しました！日程を追加しましょう。');
    }
    redirect("plan.php?id={$id}");
}

// ---- GET：フォーム表示（?id= があれば編集） ----
$id   = (int) ($_GET['id'] ?? 0);
$plan = $id ? $repo->find($id, $user['id']) : null;
if ($id && !$plan) {
    flash('error', 'プランが見つかりません。');
    redirect('index.php');
}

$defaults = [
    'title'       => '',
    'destination' => '',
    'start_date'  => date('Y-m-d', strtotime('+7 days')),
    'end_date'    => date('Y-m-d', strtotime('+8 days')),
    'budget'      => '',
    'memo'        => '',
    'lat'         => '',
    'lng'         => '',
    'country'     => '',
];
$current = $plan ? array_intersect_key($plan, $defaults) + [
    'lat'     => (string) $plan['dest_lat'],
    'lng'     => (string) $plan['dest_lng'],
    'country' => $plan['dest_country'],
] : [];
// 優先順：エラーで戻ってきた値 > 既存のプラン > 初期値
$form = array_merge($defaults, $current, pull_old());
$useMap = true;

$pageTitle = $plan ? 'プランを編集' : '新しい旅を計画';
require __DIR__ . '/../src/templates/header.php';
?>
<section class="card form-card">
    <h1><?= h($pageTitle) ?></h1>
    <form action="plan_form.php" method="post" enctype="multipart/form-data" class="form">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $id ?>">
        <input type="hidden" name="MAX_FILE_SIZE" value="<?= ImageUploader::MAX_SIZE ?>">

        <label>
            タイトル <span class="req">必須</span>
            <input type="text" name="title" value="<?= h($form['title']) ?>" maxlength="50" placeholder="例：秋の京都 紅葉めぐり" required>
        </label>
        <label>
            行き先 <span class="req">必須</span>
            <input type="text" name="destination" value="<?= h($form['destination']) ?>" maxlength="30" placeholder="例：京都市 / 那覇市 / Kyoto" required data-suggest="city">
            <small class="muted">候補から選ぶと地図がその場所へ移動します。地図をクリックすると中心の位置を調整できます。</small>
        </label>
        <input type="hidden" name="lat" value="<?= h($form['lat']) ?>">
        <input type="hidden" name="lng" value="<?= h($form['lng']) ?>">
        <input type="hidden" name="country" value="<?= h($form['country']) ?>">
        <div id="dest-map" class="map map-small"></div>
        <p id="dest-map-note" class="small muted"></p>
        <div class="form-row">
            <label>
                出発日 <span class="req">必須</span>
                <input type="date" name="start_date" value="<?= h($form['start_date']) ?>" required>
            </label>
            <label>
                帰着日 <span class="req">必須</span>
                <input type="date" name="end_date" value="<?= h($form['end_date']) ?>" required>
            </label>
        </div>
        <label>
            予算（円）
            <input type="number" name="budget" value="<?= h((string) $form['budget']) ?>" min="0" step="1" placeholder="例：50000">
        </label>
        <label>
            メモ
            <textarea name="memo" rows="4" maxlength="1000" placeholder="旅の目的、同行者、予約番号など"><?= h($form['memo']) ?></textarea>
        </label>
        <label>
            表紙画像（jpg / png / gif / webp、2MB まで）
            <input type="file" name="cover" accept="image/jpeg,image/png,image/gif,image/webp">
        </label>
        <?php if (!empty($plan['cover_image'])): ?>
            <div class="current-cover">
                <img src="<?= h(UPLOAD_URL . '/' . $plan['cover_image']) ?>" alt="現在の表紙">
                <label class="checkbox"><input type="checkbox" name="remove_cover" value="1"> 表紙画像を削除する</label>
            </div>
        <?php endif; ?>

        <div class="form-actions">
            <a href="<?= $plan ? 'plan.php?id=' . (int) $id : 'index.php' ?>" class="btn btn-outline">キャンセル</a>
            <button type="submit" class="btn"><?= $plan ? '更新する' : '作成する' ?></button>
        </div>
    </form>
</section>
<script src="<?= h(asset('js/suggest.js')) ?>"></script>
<script>
// 行き先の位置を地図で確認・調整する
window.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('.form-card form');
    const input = form.destination;
    const note = document.getElementById('dest-map-note');
    const map = L.map('dest-map').setView([36.5, 137.5], 5); // 初期表示は日本全体
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(map);

    let marker = null;
    const setMarker = (lat, lng, zoom) => {
        if (marker) marker.setLatLng([lat, lng]);
        else marker = L.marker([lat, lng], { draggable: true }).addTo(map).on('dragend', (e) => {
            const p = e.target.getLatLng();
            savePoint(p.lat, p.lng);
        });
        map.flyTo([lat, lng], zoom ?? Math.max(map.getZoom(), 11), { duration: 0.8 });
    };
    const savePoint = (lat, lng) => {
        form.lat.value = lat.toFixed(6);
        form.lng.value = lng.toFixed(6);
        note.textContent = 'この位置を行き先の中心として保存します（天気・地図・おすすめスポットの基準になります）。';
    };

    // 編集時は保存済みの位置を表示
    if (form.lat.value !== '') {
        setMarker(Number(form.lat.value), Number(form.lng.value), 11);
        note.textContent = '保存済みの位置です。地図をクリックするかピンをドラッグすると調整できます。';
    } else {
        note.textContent = '行き先を候補から選ぶか、地図をクリックして位置を指定してください（指定しなければ地名から自動で調べます）。';
    }

    // 候補を選んだら地図を移動（suggest.js が発行するイベント）
    input.addEventListener('suggest:select', (e) => {
        const r = e.detail;
        if (r.approx) {
            // 都道府県は中心が山の中などになるため、保存時に県庁所在地で調べ直す
            map.flyTo([r.lat, r.lng], 8, { duration: 0.8 });
            if (marker) { marker.remove(); marker = null; }
            note.textContent = '都道府県を選んだので、県庁所在地を中心にします。別の場所にしたい場合は地図をクリックしてください。';
        } else {
            setMarker(r.lat, r.lng, 11);
            savePoint(r.lat, r.lng);
        }
    });
    // 手で書き換えたら位置はリセット（保存時に地名から調べる）
    input.addEventListener('input', () => {
        if (marker) { marker.remove(); marker = null; }
        note.textContent = '候補から選ぶか、地図をクリックして位置を指定してください（指定しなければ地名から自動で調べます）。';
    });

    map.on('click', (e) => {
        setMarker(e.latlng.lat, e.latlng.lng);
        savePoint(e.latlng.lat, e.latlng.lng);
    });
});
</script>
<?php require __DIR__ . '/../src/templates/footer.php'; ?>
