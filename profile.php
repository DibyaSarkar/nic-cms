<?php
/**
 * profile.php - ANY logged-in user (even a subscriber) can edit their own
 * name, bio and password. Note: the WHERE uses the SESSION id, never an id
 * from the form, so nobody can edit someone else's profile.
 */
require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $fullName = trim($_POST['full_name'] ?? '');
    $bio      = trim($_POST['bio'] ?? '');
    $newPass  = $_POST['new_password'] ?? '';

    if ($fullName === '') {
        flash('danger', 'Full name is required.');
    } else {
        db()->prepare('UPDATE users SET full_name = ?, bio = ? WHERE user_id = ?')
            ->execute([$fullName, $bio, $_SESSION['user_id']]);

        if ($newPass !== '') {
            if (strlen($newPass) < 8) {
                flash('danger', 'New password must be at least 8 characters - password not changed.');
                redirect('profile.php');
            }
            db()->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?')
                ->execute([password_hash($newPass, PASSWORD_DEFAULT), $_SESSION['user_id']]);
        }
        log_activity('Updated own profile');
        flash('success', 'Profile saved.');
    }
    redirect('profile.php');
}

$page_title = 'My profile';
require __DIR__ . '/includes/header.php';
?>
<div class="card narrow">
    <h1>My profile</h1>
    <p class="muted">@<?= e($user['username']) ?> &middot; <?= e($user['email']) ?> &middot;
        role: <span class="badge badge-<?= e($user['role_name']) ?>"><?= e($user['role_label']) ?></span></p>
    <h3>What your role can do</h3>
    <p><?php
        $perms = user_permissions();
        echo $perms ? implode(' ', array_map(fn($p) => '<code>' . e($p) . '</code>', $perms))
                    : '<span class="muted">No back-end permissions (read &amp; comment only).</span>';
    ?></p>
    <form method="post">
        <?= csrf_field() ?>
        <label>Full name <input name="full_name" value="<?= e($user['full_name']) ?>" required></label>
        <label>Bio <textarea name="bio" rows="3"><?= e($user['bio']) ?></textarea></label>
        <label>New password <small class="muted">(leave blank to keep current)</small>
            <input type="password" name="new_password"></label>
        <button class="btn">Save</button>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
