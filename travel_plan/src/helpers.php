<?php
// 共通で使う関数（composer.json の autoload.files で読み込み）

/**
 * HTML エスケープ
 */
function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * リダイレクトして処理を終了（PRG パターン用）
 */
function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

/**
 * 次の画面で 1 回だけ表示するメッセージを登録
 */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/**
 * フラッシュメッセージを取り出して削除
 */
function pull_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

/**
 * バリデーションエラー時に入力値を保持
 */
function set_old(array $input): void
{
    $_SESSION['old'] = $input;
}

/**
 * 保持していた入力値を取り出す（1 回で消える）
 */
function pull_old(): array
{
    $old = $_SESSION['old'] ?? [];
    unset($_SESSION['old']);
    return $old;
}

/**
 * CSRF トークン
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(400);
        exit('不正なリクエストです。ページを再読み込みしてやり直してください。');
    }
}

/**
 * POST 以外のアクセスを拒否
 */
function require_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('index.php');
    }
    verify_csrf();
}

/**
 * ログイン中のユーザー（未ログインなら null）
 */
function current_user(): ?array
{
    static $checked = false;
    if (!$checked && isset($_SESSION['user'])) {
        $checked = true;
        // DB を作り直した後などで、セッションのユーザーが存在しなければログアウト扱いにする
        if ((new \App\UserRepository())->find((int) $_SESSION['user']['id']) === null) {
            unset($_SESSION['user']);
        }
    }
    return $_SESSION['user'] ?? null;
}

/**
 * ログイン必須ページで呼ぶ
 */
function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        flash('error', 'ログインしてください。');
        redirect('login.php');
    }
    return $user;
}

/**
 * POST された plan_id が自分のプランか確認して返す（違えば一覧へ戻す）
 */
function require_own_plan(array $user): array
{
    $plan = (new \App\PlanRepository())->find((int) ($_POST['plan_id'] ?? 0), $user['id']);
    if (!$plan) {
        flash('error', 'プランが見つかりません。');
        redirect('index.php');
    }
    return $plan;
}

/**
 * プランの行き先の座標 ['lat', 'lng', 'country']（見つからなければ null）
 * 座標が未保存のプランは地名から調べて保存する
 */
function plan_location(array $plan): ?array
{
    if ($plan['dest_lat'] !== null) {
        return ['lat' => (float) $plan['dest_lat'], 'lng' => (float) $plan['dest_lng'], 'country' => $plan['dest_country']];
    }
    $geo = (new \App\Geocoder())->locate($plan['destination']);
    if ($geo === null) {
        return null;
    }
    (new \App\PlanRepository())->setLocation((int) $plan['id'], $geo['lat'], $geo['lng'], $geo['country']);
    return ['lat' => $geo['lat'], 'lng' => $geo['lng'], 'country' => $geo['country']];
}

/**
 * CSS・JS の URL に更新日時を付ける（ファイルを書き換えたときにブラウザのキャッシュを使わせない）
 */
function asset(string $path): string
{
    $file = __DIR__ . '/../public/' . $path;
    return $path . (is_file($file) ? '?v=' . filemtime($file) : '');
}

/**
 * 金額表示
 */
function yen(int $amount): string
{
    return '¥' . number_format($amount);
}

/**
 * フォームから受け取った文字列を整える
 */
function input(array $source, string $key): string
{
    $value = $source[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

/**
 * 旅行の状態（Carbon で日付計算）
 * 戻り値: ['class' => CSS 用, 'label' => 表示文字列]
 */
function trip_status(array $plan): array
{
    $today = \Carbon\Carbon::today();
    $start = \Carbon\Carbon::parse($plan['start_date']);
    $end   = \Carbon\Carbon::parse($plan['end_date']);

    if ($today->lt($start)) {
        $days = (int) $today->diffInDays($start);
        return ['class' => 'upcoming', 'label' => $days === 1 ? '明日出発！' : "出発まであと {$days} 日"];
    }
    if ($today->lte($end)) {
        return ['class' => 'ongoing', 'label' => '旅行中'];
    }
    return ['class' => 'done', 'label' => '思い出 (' . $end->diffForHumans() . ')'];
}

/**
 * 「2泊3日」の形式
 */
function trip_length(array $plan): string
{
    $nights = (int) \Carbon\Carbon::parse($plan['start_date'])->diffInDays(\Carbon\Carbon::parse($plan['end_date']));
    return $nights === 0 ? '日帰り' : "{$nights}泊" . ($nights + 1) . '日';
}

/**
 * 日付を「12月20日(日)」の形式で
 */
function jp_date(string $date): string
{
    return \Carbon\Carbon::parse($date)->isoFormat('M月D日(ddd)');
}

/**
 * 外部 API から JSON を取得して配列で返す（失敗時は null）
 * 天気（Open-Meteo）と地図（Nominatim）で共通利用
 */
function fetch_json(string $url): ?array
{
    $context = stream_context_create([
        'http' => [
            'timeout'       => 5,
            'ignore_errors' => true,
            // Nominatim の利用規約でアプリを識別できる User-Agent が必須
            'header'        => "User-Agent: TabiPlan-student-app/1.0\r\nAccept-Language: ja\r\n",
        ],
    ]);
    $body = @file_get_contents($url, false, $context);
    if ($body === false) {
        return null;
    }
    $data = json_decode($body, true);
    return is_array($data) ? $data : null;
}

/**
 * 日付文字列 (Y-m-d) として正しいか
 */
function is_valid_date(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d !== false && $d->format('Y-m-d') === $date;
}
