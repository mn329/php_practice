<?php
require __DIR__ . '/../src/bootstrap.php';

use App\UserRepository;

if (current_user()) {
    redirect('index.php');
}

// ---- POST：登録処理 ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $name     = input($_POST, 'name');
    $email    = input($_POST, 'email');
    $password = $_POST['password'] ?? '';
    $users    = new UserRepository();

    $errors = [];
    if ($name === '' || mb_strlen($name) > 20) {
        $errors[] = 'ニックネームは 1〜20 文字で入力してください。';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'メールアドレスの形式が正しくありません。';
    } elseif ($users->findByEmail($email)) {
        $errors[] = 'このメールアドレスは既に登録されています。';
    }
    if (!is_string($password) || strlen($password) < 8) {
        $errors[] = 'パスワードは 8 文字以上で入力してください。';
    }

    if ($errors) {
        foreach ($errors as $e) {
            flash('error', $e);
        }
        set_old(['name' => $name, 'email' => $email]);
        redirect('register.php');
    }

    $id = $users->create($name, $email, $password);

    // 登録後はそのままログイン状態にする
    session_regenerate_id(true);
    $_SESSION['user'] = ['id' => $id, 'name' => $name];
    flash('success', 'ようこそ、' . $name . ' さん！最初の旅を計画しましょう。');
    redirect('index.php');
}

// ---- GET：フォーム表示 ----
$old = pull_old();
$pageTitle = '新規登録';
require __DIR__ . '/../src/templates/header.php';
?>
<section class="auth-card card">
    <h1>新規登録</h1>
    <form action="register.php" method="post" class="form">
        <?= csrf_field() ?>
        <label>
            ニックネーム
            <input type="text" name="name" value="<?= h($old['name'] ?? '') ?>" maxlength="20" required>
        </label>
        <label>
            メールアドレス
            <input type="email" name="email" value="<?= h($old['email'] ?? '') ?>" required>
        </label>
        <label>
            パスワード（8 文字以上）
            <input type="password" name="password" minlength="8" required>
        </label>
        <button type="submit" class="btn btn-block">登録する</button>
    </form>
    <p class="center small">アカウントをお持ちの方は <a href="login.php">ログイン</a></p>
</section>
<?php require __DIR__ . '/../src/templates/footer.php'; ?>
