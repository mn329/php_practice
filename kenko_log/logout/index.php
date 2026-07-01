<?php
require_once '../app.php';

// ログアウト処理
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}
session_destroy();
// ログアウト後はトップページへリダイレクト
header("Location: " . BASE_URL);
exit;