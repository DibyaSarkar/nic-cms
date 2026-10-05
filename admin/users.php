<?php
/**
 * admin/users.php - list, create, edit, suspend, delete users
 *
 * Safety rules (so an admin can't lock everyone out):
 *   - you cannot change your OWN role or status, or delete yourself
 *   - you cannot give someone a role with a higher level than your own
 */
require_once __DIR__ . '/../includes/auth.php';
require_permission('manage_users');

$me      = current_user();
$roles   = db()->query('SELECT role_id, label, level FROM roles ORDER BY level DESC')->fetchAll();
$roleLvl = array_column($roles, 'level', 'role_id');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    // ---- Delete ----
    if (isset($_POST['delete_id'])) {
        $did = (int) $_POST['delete_id'];
        if ($did === (int) $me['user_id']) {
            flash('danger', "You can't delete your own account.");
        } else {
            db()->prepare('DELETE FROM users WHERE user_id = ?')->execute([$did]);
            log_activity("Deleted user #$did");
            flash('success', 'User deleted (and their posts, via ON DELETE CASCADE).');
        }
        redirect('admin/users.php');
    }

    // ---- Create / update ----
    $id       = (int) ($_POST['user_id'] ?? 0);
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $roleId   = (int) ($_POST['role_id'] ?? 0);
    $status   = ($_POST['status'] ?? '') === 'suspended' ? 'suspended' : 'active';
    $password = $_POST['password'] ?? '';

    $errors = [];
    if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) $errors[] = 'Username: 3-30 letters, numbers or underscores.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))      $errors[] = 'Invalid email.';
    if ($fullName === '')                                 $errors[] = 'Full name is required.';
    if (!isset($roleLvl[$roleId]))                        $errors[] = 'Pick a valid role.';
    elseif ($roleLvl[$roleId] > $me['level'])             $errors[] = "You can't assign a role above your own.";
    if (!$id && strlen($password) < 8)                    $errors[] = 'New users need a password of 8+ characters.';
    if ($id && $password !== '' && strlen($password) < 8) $errors[] = 'Password must be 8+ characters.';
    if ($id === (int) $me['user_id'] && ($roleId !== (int) $me['role_id'] || $status !== 'active')) {
        $errors[] = "You can't change your own role or suspend yourself.";
    }

    $dup = db()->prepare('SELECT COUNT(*) FROM users WHERE (username = ? OR email = ?) AND user_id <> ?');
    $dup->execute([$username, $email, $id]);
    if ($dup->fetchColumn() > 0) $errors[] = 'Username or email already in use.';

    if ($errors) {
        foreach ($errors as $err) flash('danger', $err);
        redirect('admin/users.php' . ($id ? "?edit=$id" : ''));
    }

    if ($id) {
        db()->prepare('UPDATE users SET username=?, email=?, full_name=?, role_id=?, status=? WHERE user_id=?')
            ->execute([$username, $email, $fullName, $roleId, $status, $id]);
        if ($password !== '') {
            db()->prepare('UPDATE users SET password_hash=? WHERE user_id=?')
                ->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
        }
        log_activity("Updated user #$id ($username)");
    } else {
        db()->prepare('INSERT INTO users (username, email, full_name, role_id, status, password_hash) VALUES (?,?,?,?,?,?)')
            ->execute([$username, $email, $fullName, $roleId, $status, password_hash($password, PASSWORD_DEFAULT)]);
        log_activity("Created user $username");
    }
    flash('success', 'User saved.');
    redirect('admin/users.php');
}

$editing = ['user_id' => 0, 'username' => '', 'email' => '', 'full_name' => '', 'role_id' => 0, 'status' => 'active'];
if (isset($_GET['edit'])) {
    $stmt = db()->prepare('SELECT * FROM users WHERE user_id = ?');
    $stmt->execute([$_GET['edit']]);
    $editing = $stmt->fetch() ?: $editing;
}

// Filter by role: ?role=author
$roleFilter = $_GET['role'] ?? '';
$sql = 'SELECT u.user_id, u.username, u.full_name, u.email, u.status, u.last_login,
               r.role_name, r.label AS role_label,
               (SELECT COUNT(*) FROM posts p WHERE p.user_id = u.user_id) AS post_count
          FROM users u JOIN roles r ON r.role_id = u.role_id'
     . ($roleFilter ? ' WHERE r.role_name = ?' : '')
     . ' ORDER BY r.level DESC, u.username';
$stmt = db()->prepare($sql);
$stmt->execute($roleFilter ? [$roleFilter] : []);
$users = $stmt->fetchAll();

$page_title = 'Users';
require __DIR__ . '/_layout_top.php';
?>
<h1>Users</h1>
<p class="filters">Show:
    <a href="users.php">All</a>
    <?php foreach (db()->query('SELECT role_name, label FROM roles ORDER BY level DESC') as $r): ?>
        &middot; <a href="?role=<?= e($r['role_name']) ?>"><?= e($r['label']) ?></a>
    <?php endforeach; ?>
</p>
<table>
    <tr><th>User</th><th>Role</th><th>Posts</th><th>Status</th><th>Last login</th><th></th></tr>
    <?php foreach ($users as $u): ?>
        <tr>
            <td><strong><?= e($u['username']) ?></strong><br><small class="muted"><?= e($u['full_name']) ?> &middot; <?= e($u['email']) ?></small></td>
            <td><span class="badge badge-<?= e($u['role_name']) ?>"><?= e($u['role_label']) ?></span></td>
            <td><?= (int) $u['post_count'] ?></td>
            <td><span class="status status-<?= e($u['status']) ?>"><?= e($u['status']) ?></span></td>
            <td><?= e($u['last_login'] ?? 'never') ?></td>
            <td class="actions">
                <a href="?edit=<?= $u['user_id'] ?>">Edit</a>
                <?php if ($u['user_id'] != $me['user_id']): ?>
                    <form method="post" onsubmit="return confirm('Delete user AND all their posts?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="delete_id" value="<?= $u['user_id'] ?>">
                        <button class="link danger">Delete</button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
</table>

<form method="post" class="card">
    <h3><?= $editing['user_id'] ? 'Edit user: ' . e($editing['username']) : 'Add user' ?></h3>
    <?= csrf_field() ?>
    <input type="hidden" name="user_id" value="<?= (int) $editing['user_id'] ?>">
    <div class="row">
        <label>Username <input name="username" value="<?= e($editing['username']) ?>" required></label>
        <label>Full name <input name="full_name" value="<?= e($editing['full_name']) ?>" required></label>
    </div>
    <label>Email <input type="email" name="email" value="<?= e($editing['email']) ?>" required></label>
    <div class="row">
        <label>Role
            <select name="role_id">
                <?php foreach ($roles as $r): if ($r['level'] > $me['level']) continue; ?>
                    <option value="<?= $r['role_id'] ?>" <?= $editing['role_id'] == $r['role_id'] ? 'selected' : '' ?>><?= e($r['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Status
            <select name="status">
                <?php foreach (['active', 'suspended'] as $s): ?>
                    <option <?= $editing['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>
    <label>Password <?= $editing['user_id'] ? '<small class="muted">(leave blank to keep)</small>' : '' ?>
        <input type="password" name="password" <?= $editing['user_id'] ? '' : 'required minlength="8"' ?>></label>
    <button class="btn">Save user</button>
    <?php if ($editing['user_id']): ?><a href="users.php">Cancel</a><?php endif; ?>
</form>
<?php require __DIR__ . '/_layout_bottom.php'; ?>
