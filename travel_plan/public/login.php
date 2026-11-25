<?php
require __DIR__ . '/../src/bootstrap.php';

use App\UserRepository;

if (current_user()) {
    redirect('index.php');
}

// ---- POST：ログイン処理 ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email    = input($_POST, 'email');
    $password = $_POST['password'] ?? '';

    $user = is_string($password) ? (new UserRepository())->authenticate($email, $password) : null;
    if ($user === null) {
        flash('error', 'メールアドレスまたはパスワードが違います。');
        set_old(['email' => $email]);
        redirect('login.php');
    }

    // セッション固定攻撃対策で ID を作り直す
    session_regenerate_id(true);
    $_SESSION['user'] = ['id' => (int) $user['id'], 'name' => $user['name']];
    flash('success', 'おかえりなさい、' . $user['name'] . ' さん！');
    redirect('index.php');
}

// ---- GET：フォーム表示 ----
$old = pull_old();
$pageTitle = 'ログイン';
require __DIR__ . '/../src/templates/header.php';
?>
<section class="auth-card card">
    <h1>ログイン</h1>
    <form action="login.php" method="post" class="form">
        <?= csrf_field() ?>
        <label>
            メールアドレス
            <input type="email" name="email" value="<?= h($old['email'] ?? '') ?>" required autofocus>
        </label>
        <label>
            パスワード
            <input type="password" name="password" required>
        </label>
        <button type="submit" class="btn btn-block">ログイン</button>
    </form>
    <p class="center small">はじめての方は <a href="register.php">新規登録</a></p>
</section>
<?php require __DIR__ . '/../src/templates/footer.php'; ?>
