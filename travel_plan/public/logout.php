<?php
require __DIR__ . '/../src/bootstrap.php';

require_post();

// セッションの中身を空にして、セッション ID も新しく作り直す
$_SESSION = [];
session_regenerate_id(true);

flash('success', 'ログアウトしました。');
redirect('login.php');
