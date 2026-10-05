<?php
/**
 * login.php
 */
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect('');
}

$login = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $login    = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    if (attempt_login($login, $password)) {
        flash('success', 'Welcome back!');
        $next = $_SESSION['after_login'] ?? null;
        unset($_SESSION['after_login']);
        if ($next) {
            header('Location: ' . $next);
            exit;
        }
        redirect(can('view_dashboard') ? 'admin/index.php' : '');
    }
    flash('danger', 'Wrong username/email or password.');
    redirect('login.php');
}

$page_title = 'Log in';
require __DIR__ . '/includes/header.php';
?>
<div class="card narrow">
    <h1>Log in</h1>
    <form method="post">
        <?= csrf_field() ?>
        <label>Username or email <input name="login" value="<?= e($login) ?>" required autofocus></label>
        <label>Password <input type="password" name="password" required></label>
        <button class="btn">Log in</button>
    </form>
    <p class="muted small">Demo accounts: admin, editor, author, writer2, reader &mdash; password <code>Password123!</code></p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
