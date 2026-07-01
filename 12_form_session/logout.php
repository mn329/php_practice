<?php

/**
 * 07_form_session/logout.php
 * セッションを破棄してログアウトする
 */

// セッションの開始
session_start();

// セッション変数の破棄
if (isset($_SESSION['authUser'])) {
    unset($_SESSION['authUser']);
    unset($_SESSION['message']);
    unset($_SESSION['status']);
    unset($_SESSION['previous_post']);
}

// ログインページへリダイレクト
header('Location: post_request.php');
exit;
