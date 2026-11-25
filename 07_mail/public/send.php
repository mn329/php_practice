<?php
// セッション開始
session_start();

// Contactクラス読み込み
require __DIR__ . '/../src/Contact.php';

// POSTデータ受け取り
$name  = $_POST['name']  ?? '';
$email = $_POST['email'] ?? '';
$body  = $_POST['body']  ?? '';

// セッションデータ保存（エラー時にフォームへ入力を戻すため）
$_SESSION['name']  = $name;
$_SESSION['email'] = $email;
$_SESSION['body']  = $body;

// Contactクラスのインスタンス化
$contact = new Contact();

// バリデーション
$error = $contact->validate($name, $email, $body);
if ($error) {
    $_SESSION['error'] = $error;
    header("Location: ./");
    exit;
}

// メール送信
$result = $contact->send($name, $email, $body);

if ($result === true) {
    // 結果画面へリダイレクト
    header("Location: result.php");
} else {
    $_SESSION['error'] = "送信に失敗しました: {$result}";
    header("Location: ./");
}
exit;