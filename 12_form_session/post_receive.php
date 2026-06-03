<?php

/**
 * 07_form_session/post_receive.php
 * POSTデータを受け取り、認証検証を行ってからリダイレクトする
 */

session_start();
// セッションを再生成
session_regenerate_id(true);

// テストユーザー情報 (本来はデータベース等で管理します)
const TEST_USER = [
    'name' => '東京 太郎',
    'email' => 'user@example.com',
    'password' => 'password123'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // デバッグ: POSTデータを表示して強制終了
    // var_dump($_POST);
    // exit;

    // TODO: POSTの email を取得
    $email = $_POST['email'] ?? '';
    // TODO: POSTの password を取得
    $password = $_POST['password'] ?? '';

    // TODO: 入力データの保持 (復元用): previous_post セッションに保存
    $_SESSION['previous_post'] = $_POST;

    // 認証検証
    if ($email === TEST_USER['email'] && $password === TEST_USER['password']) {
        // 認証成功
        $_SESSION['status'] = 'success';
        $_SESSION['authUser'] = TEST_USER;
        $_SESSION['message'] = 'ログインに成功しました。';

        // TODO: 成功時はパスワードを保持しない: unset() previous_post > password
        unset($_SESSION['previous_post']['password']);
    } else {
        // 認証失敗
        $_SESSION['status'] = 'error';
        $_SESSION['message'] = 'メールアドレスまたはパスワードが正しくありません。';
    }
}

// post_request.php にリダイレクト
header('Location: post_request.php');
exit;
