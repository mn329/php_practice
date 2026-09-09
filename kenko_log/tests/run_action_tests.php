<?php
/**
 * Kenko Log 動作テスト（No.1〜No.18 全項目）
 *
 * 実行:
 *   /Applications/MAMP/bin/php/php8.3.9/bin/php tests/run_action_tests.php
 *
 * オプション:
 *   --skip-ai  No.15（Gemini AI 分析）の実 API 呼び出しをスキップ
 *              ※ AI テストは外部 API を消費し、応答に数十秒かかる場合がある
 *
 * 前提:
 *   - MAMP 起動済み
 *   - env.php の DB 設定済み（No.15 は GEMINI_API_KEY も必要）
 *   - テストユーザー: test@example.com / password123
 */
declare(strict_types=1);

$appDir = dirname(__DIR__);
require_once $appDir . '/env.php';

$skipAi = in_array('--skip-ai', $argv ?? [], true);
$baseUrl = detectBaseUrl();
$results = [];

function detectBaseUrl(): string
{
    $candidates = [
        'http://localhost/ph31_2026/kenko_log/',
        'http://localhost:8888/ph31_2026/kenko_log/',
    ];
    foreach ($candidates as $url) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY => true,
            CURLOPT_TIMEOUT => 5,
        ]);
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code === 200) {
            return $url;
        }
    }
    return $candidates[0];
}

function http(string $method, string $url, ?string $cookieFile = null, ?string $body = null, int $timeout = 15): array
{
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_FOLLOWLOCATION => false,
    ];
    if ($cookieFile) {
        $opts[CURLOPT_COOKIEJAR] = $cookieFile;
        $opts[CURLOPT_COOKIEFILE] = $cookieFile;
    }
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = $body;
    }
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $headerSize = strpos((string) $raw, "\r\n\r\n");
    $headers = $headerSize !== false ? substr((string) $raw, 0, $headerSize) : '';
    $bodyText = $headerSize !== false ? substr((string) $raw, $headerSize + 4) : (string) $raw;

    return ['code' => $code, 'headers' => $headers, 'body' => $bodyText];
}

function record(string $no, string $name, bool $pass, string $detail = ''): void
{
    global $results;
    $results[] = ['no' => $no, 'name' => $name, 'pass' => $pass, 'detail' => $detail];
    $mark = $pass ? 'PASS' : 'FAIL';
    echo sprintf("[%s] %s %s%s\n", $mark, $no, $name, $detail ? " — $detail" : '');
}

function extractCsrf(string $html): string
{
    if (preg_match('/name="csrf_token" value="([^"]+)"/', $html, $m)) {
        return $m[1];
    }
    return '';
}

function login(string $baseUrl, string $cookieFile): bool
{
    $page = http('GET', $baseUrl . 'login/', $cookieFile);
    $csrf = extractCsrf($page['body']);
    if ($csrf === '') {
        return false;
    }
    $res = http('POST', $baseUrl . 'login/auth.php', $cookieFile, http_build_query([
        'csrf_token' => $csrf,
        'email' => 'test@example.com',
        'password' => 'password123',
    ]));
    $dash = http('GET', $baseUrl . 'dashboard/', $cookieFile);
    return $dash['code'] === 200 && str_contains($dash['body'], 'dashboard');
}

echo "=== Kenko Log 動作テスト ===\n";
echo "BASE_URL: $baseUrl\n\n";

// No.1 DB 接続とテーブル構築
try {
    $checkPdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET),
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $tables = $checkPdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $required = ['users', 'health_records', 'exercise_records', 'meal_records', 'sleep_records', 'ai_diagnosis_logs'];
    $missing = array_diff($required, $tables);
    record('No.1', 'DB 接続とテーブル構築',
        $missing === [],
        $missing === [] ? DB_NAME . ' に接続、全' . count($required) . 'テーブルあり' : '不足: ' . implode(', ', $missing)
    );
    $hasTestUser = (bool) $checkPdo->query("SELECT COUNT(*) FROM users WHERE email = 'test@example.com'")->fetchColumn();
    record('No.1b', 'テストユーザーの存在', $hasTestUser, 'test@example.com');
    $checkPdo = null;
} catch (Throwable $e) {
    record('No.1', 'DB 接続とテーブル構築', false, $e->getMessage());
    echo "\nDB に接続できないため終了します。env.php と MySQL の起動を確認してください。\n";
    exit(1);
}

// No.2 トップページのキャッチコピー
$res = http('GET', $baseUrl);
record('No.2', 'トップページのキャッチコピー',
    $res['code'] === 200 && str_contains($res['body'], 'かんたんに記録'),
    $res['code'] === 200 ? 'キャッチコピー表示' : "HTTP {$res['code']}"
);

// No.3 フッターのクレジット年表示
$year = date('Y');
record('No.3', 'フッターのクレジット年表示',
    str_contains($res['body'], "2020 - $year"),
    "2020 - $year"
);

// No.13 未ログイン時ナビ（先に確認）
$hasPublic = str_contains($res['body'], 'ログイン') && str_contains($res['body'], 'ユーザー登録');
$hasUserOnly = !str_contains($res['body'], 'マイページ') || str_contains($res['body'], 'dashboard');
record('No.13a', '未ログイン時ナビ（公開メニュー）', $hasPublic, 'ログイン・登録ボタン');

$cookie = sys_get_temp_dir() . '/kenko_action_test_' . getmypid() . '.txt';
@unlink($cookie);

if (!login($baseUrl, $cookie)) {
    record('LOGIN', 'テストユーザーログイン', false, 'test@example.com / password123 でログイン失敗');
    echo "\nログインに失敗したため、認証が必要なテストをスキップします。\n";
} else {
    record('LOGIN', 'テストユーザーログイン', true, 'dashboard 200');

    // No.13 ログイン後ナビ
    $dash = http('GET', $baseUrl . 'dashboard/', $cookie);
    record('No.13b', 'ログイン後ナビ（ユーザメニュー）',
        str_contains($dash['body'], 'ログアウト') || str_contains($dash['body'], 'dashboard'),
        'ログアウトまたはダッシュボードリンク'
    );

    // No.4 心拍数表示
    $health = http('GET', $baseUrl . 'health/', $cookie);
    $hasHeartRate = str_contains($health['body'], '心拍数(bpm)')
        && preg_match('/<td[^>]*>\s*\d+\s*<\/td>/', $health['body']) === 1
        && !str_contains($health['body'], 'TODO: 心拍数');
    record('No.4', '健康記録一覧の心拍数表示', $hasHeartRate, '数値セルあり');

    // No.6 編集画面
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET),
        DB_USER,
        DB_PASS
    );
    $healthId = (int) $pdo->query('SELECT id FROM health_records LIMIT 1')->fetchColumn();
    $edit = http('GET', $baseUrl . "health/edit.php?id=$healthId", $cookie);
    record('No.6', '健康記録の編集画面',
        str_contains($edit['body'], '健康記録を編集') && !str_contains($edit['body'], '該当する記録が見つかりません'),
        "id=$healthId"
    );

    // No.7 CSVダウンロード
    $csv = http('GET', $baseUrl . 'api/health/csv/', $cookie);
    $csvOk = $csv['code'] === 200
        && str_contains($csv['headers'], 'text/csv')
        && str_contains($csv['body'], 'recorded_at')
        && str_contains($csv['body'], 'heart_rate');
    record('No.7', '健康記録のCSVダウンロード', $csvOk,
        $csvOk ? strlen($csv['body']) . ' bytes' : "HTTP {$csv['code']}"
    );

    // No.5 健康記録追加
    $testDate = '2099-12-31';
    $pdo->exec("DELETE FROM health_records WHERE recorded_at = '$testDate'");
    $ins = http('POST', $baseUrl . 'health/insert.php', $cookie, http_build_query([
        'weight' => 61.5,
        'heart_rate' => 68,
        'systolic' => 115,
        'diastolic' => 75,
        'recorded_at' => $testDate,
    ]));
    $cnt = (int) $pdo->query("SELECT COUNT(*) FROM health_records WHERE recorded_at = '$testDate'")->fetchColumn();
    $pdo->exec("DELETE FROM health_records WHERE recorded_at = '$testDate'");
    record('No.5', '健康記録の追加', $cnt === 1, $cnt === 1 ? 'INSERT成功・削除済み' : "件数=$cnt");

    // No.8 アクティビティ種別
    $act = http('GET', $baseUrl . 'activity/', $cookie);
    record('No.8', 'アクティビティ一覧の種類表示',
        $act['code'] === 200
            && !str_contains($act['body'], 'Warning')
            && !str_contains($act['body'], "row['']"),
        'Warningなし'
    );

    // No.9 アクティビティ追加フォーム
    $add = http('GET', $baseUrl . 'activity/add.php', $cookie);
    record('No.9', 'アクティビティ記録の追加（method=post）',
        preg_match('/method\s*=\s*["\']post["\']/i', $add['body']) === 1,
        'activity/add.php'
    );

    // No.9 insert 動作
    $actDate = '2099-12-30';
    $pdo->exec("DELETE FROM exercise_records WHERE exercise_date = '$actDate'");
    $actIns = http('POST', $baseUrl . 'activity/insert.php', $cookie, http_build_query([
        'exercise_date' => $actDate,
        'exercise_type' => 'テスト走',
        'duration_minutes' => 30,
        'calories_burned' => 100,
        'distance_km' => 3,
        'memo' => 'test',
    ]));
    $actCnt = (int) $pdo->query("SELECT COUNT(*) FROM exercise_records WHERE exercise_date = '$actDate'")->fetchColumn();
    $pdo->exec("DELETE FROM exercise_records WHERE exercise_date = '$actDate'");
    record('No.9b', 'アクティビティ記録の登録処理', $actCnt === 1, $actCnt === 1 ? 'INSERT成功' : "件数=$actCnt");

    // No.10 食事種別
    $meal = http('GET', $baseUrl . 'meal/', $cookie);
    record('No.10', '食事記録一覧の種別表示',
        preg_match('/(朝食|昼食|夕食|間食)/u', $meal['body']) === 1
            && !str_contains($meal['body'], 'TODO: 食事の種別'),
        '日本語ラベル表示'
    );

    // No.11 睡眠削除（テスト用レコードを作成して削除）
    $userId = (int) $pdo->query('SELECT id FROM users LIMIT 1')->fetchColumn();
    $pdo->exec("DELETE FROM sleep_records WHERE sleep_date = '2099-12-29'");
    $pdo->prepare('INSERT INTO sleep_records (user_id, sleep_date, bedtime, wake_time, sleep_duration_minutes, sleep_quality, memo) VALUES (?,?,?,?,?,?,?)')
        ->execute([$userId, '2099-12-29', '2099-12-29 23:00:00', '2099-12-30 07:00:00', 480, 4, 'delete-test']);
    $sleepId = (int) $pdo->lastInsertId();
    $del = http('POST', $baseUrl . 'sleep/delete.php', $cookie, http_build_query(['id' => $sleepId]));
    $remain = (int) $pdo->query("SELECT COUNT(*) FROM sleep_records WHERE id = $sleepId")->fetchColumn();
    record('No.11', '睡眠データ削除',
        $del['code'] === 302 && $remain === 0,
        $remain === 0 ? "テストレコード id=$sleepId 削除成功" : '削除失敗'
    );

    // No.18 アクティビティグラフ API
    $api = http('GET', $baseUrl . 'api/activity/get/', $cookie);
    $apiData = json_decode($api['body'], true);
    record('No.18', 'アクティビティグラフ API',
        $api['code'] === 200 && is_array($apiData) && isset($apiData[0]['exercise_date']),
        is_array($apiData) ? count($apiData) . ' 件' : 'JSON不正'
    );

    // No.15 Gemini AI 分析
    if (!defined('GEMINI_API_KEY') || GEMINI_API_KEY === '') {
        record('No.15', 'Gemini AI 分析', false, 'GEMINI_API_KEY が未設定（env.php を確認）');
    } elseif ($skipAi) {
        echo "[SKIP] No.15 Gemini AI 分析 — --skip-ai 指定のためスキップ（キー設定は確認済み）\n";
    } else {
        echo "  ... No.15 実行中（Gemini API 呼び出し、数十秒かかる場合があります）\n";
        $aiBefore = (int) $pdo->query('SELECT COUNT(*) FROM ai_diagnosis_logs')->fetchColumn();
        $ai = http('GET', $baseUrl . 'api/health/ai/', $cookie, null, 90);
        $aiData = json_decode($ai['body'], true);
        $aiOk = $ai['code'] === 200
            && is_array($aiData)
            && !empty($aiData['advice']);
        $aiAfter = (int) $pdo->query('SELECT COUNT(*) FROM ai_diagnosis_logs')->fetchColumn();
        record('No.15', 'Gemini AI 分析（advice 取得）', $aiOk,
            $aiOk ? mb_substr((string) $aiData['advice'], 0, 40) . '…' : ('応答: ' . mb_substr($ai['body'], 0, 120))
        );
        record('No.15b', 'AI 診断結果の DB 保存', $aiAfter === $aiBefore + 1,
            "ai_diagnosis_logs: $aiBefore → $aiAfter 件"
        );
        // テストで増えた診断ログを削除して元に戻す
        if ($aiAfter === $aiBefore + 1) {
            $pdo->exec('DELETE FROM ai_diagnosis_logs WHERE id = (SELECT * FROM (SELECT MAX(id) FROM ai_diagnosis_logs) tmp)');
        }
    }

    // No.12 ログアウト（最後に実行）
    http('GET', $baseUrl . 'logout/', $cookie);
    $after = http('GET', $baseUrl . 'dashboard/', $cookie);
    record('No.12', 'ログアウト処理',
        $after['code'] === 302 && str_contains($after['headers'], 'login'),
        'ログアウト後 dashboard → login へリダイレクト'
    );
}

// No.14 ログイン失敗時の入力値復元
$cookie2 = sys_get_temp_dir() . '/kenko_login_restore_' . getmypid() . '.txt';
@unlink($cookie2);
$page = http('GET', $baseUrl . 'login/', $cookie2);
$csrf = extractCsrf($page['body']);
http('POST', $baseUrl . 'login/auth.php', $cookie2, http_build_query([
    'csrf_token' => $csrf,
    'email' => 'restore_test@example.com',
    'password' => 'wrongpassword',
]));
$again = http('GET', $baseUrl . 'login/', $cookie2);
record('No.14', 'ログイン失敗時の入力値復元',
    str_contains($again['body'], 'value="restore_test@example.com"'),
    'メールアドレス欄に復元'
);

// No.16 / No.17 ユーザー登録
$cookie3 = sys_get_temp_dir() . '/kenko_register_' . getmypid() . '.txt';
@unlink($cookie3);
$regPage = http('GET', $baseUrl . 'register/input.php', $cookie3);
$regCsrf = extractCsrf($regPage['body']);
$hasCsrf = $regCsrf !== '' && str_contains($regPage['body'], 'name="csrf_token"');
record('No.16', 'ユーザー登録のCSRFトークン', $hasCsrf, 'hidden にトークンあり');

$testEmail = 'action_test_' . time() . '@example.com';
$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET),
    DB_USER,
    DB_PASS
);
$pdo->exec("DELETE FROM users WHERE email = " . $pdo->quote($testEmail));
$reg = http('POST', $baseUrl . 'register/store.php', $cookie3, http_build_query([
    'csrf_token' => $regCsrf,
    'name' => 'ActionTest',
    'email' => $testEmail,
    'password' => 'password12345',
    'password_confirmation' => 'password12345',
]));
$user = $pdo->query("SELECT password_hash FROM users WHERE email = " . $pdo->quote($testEmail))->fetch(PDO::FETCH_ASSOC);
$hashOk = $user && str_starts_with($user['password_hash'], '$2y$');
record('No.17', 'ユーザー登録のパスワードハッシュ化', $hashOk, $hashOk ? 'bcryptハッシュ保存' : 'ハッシュ未保存');
$pdo->exec("DELETE FROM users WHERE email = " . $pdo->quote($testEmail));

// No.18 JS ファイル
$js = file_get_contents($appDir . '/js/activity_chart.js');
record('No.18b', 'activity_chart.js の URL 設定',
    str_contains($js, "api/activity/get/"),
    'js/activity_chart.js'
);

@unlink($cookie);
@unlink($cookie2);
@unlink($cookie3);

$pass = count(array_filter($results, fn($r) => $r['pass']));
$fail = count(array_filter($results, fn($r) => !$r['pass']));
echo "\n=== 結果: PASS $pass / FAIL $fail / 全" . count($results) . " ===\n";
exit($fail > 0 ? 1 : 0);
