<?php
/**
 * register.php
 * New users get whichever role has is_default = 1 (Subscriber in the seed data).
 * Change the default role in the DB and every new sign-up follows it.
 */
require_once __DIR__ . '/includes/auth.php';

if (setting('allow_registration') !== '1') {
    flash('warning', 'Registration is currently closed.');
    redirect('login.php');
}
if (is_logged_in()) {
    redirect('');
}

$errors = [];
$old = ['username' => '', 'email' => '', 'full_name' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $old = [
        'username'  => trim($_POST['username'] ?? ''),
        'email'     => trim($_POST['email'] ?? ''),
        'full_name' => trim($_POST['full_name'] ?? ''),
    ];
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    // --- Validation ---
    if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $old['username'])) {
        $errors[] = 'Username: 3-30 letters, numbers or underscores.';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email.';
    }
    if ($old['full_name'] === '') {
        $errors[] = 'Full name is required.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    // Is the username/email already taken?
    if (!$errors) {
        $stmt = db()->prepare('SELECT COUNT(*) FROM users WHERE username = ? OR email = ?');
        $stmt->execute([$old['username'], $old['email']]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'That username or email is already registered.';
        }
    }

    if (!$errors) {
        $roleId = db()->query('SELECT role_id FROM roles WHERE is_default = 1 LIMIT 1')->fetchColumn();

        $stmt = db()->prepare(
            'INSERT INTO users (role_id, username, email, password_hash, full_name)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $roleId,
            $old['username'],
            $old['email'],
            password_hash($password, PASSWORD_DEFAULT),   // NEVER store the raw password
            $old['full_name'],
        ]);

        attempt_login($old['username'], $password);
        flash('success', 'Account created - welcome!');
        redirect('');
    }
}

$page_title = 'Sign up';
require __DIR__ . '/includes/header.php';
?>
<div class="card narrow">
    <h1>Create an account</h1>
    <?php foreach ($errors as $err): ?>
        <div class="alert alert-danger"><?= e($err) ?></div>
    <?php endforeach; ?>
    <form method="post">
        <?= csrf_field() ?>
        <label>Username <input name="username" value="<?= e($old['username']) ?>" required></label>
        <label>Full name <input name="full_name" value="<?= e($old['full_name']) ?>" required></label>
        <label>Email <input type="email" name="email" value="<?= e($old['email']) ?>" required></label>
        <label>Password <input type="password" name="password" required minlength="8"></label>
        <label>Confirm password <input type="password" name="confirm" required></label>
        <button class="btn">Sign up</button>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
