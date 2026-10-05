<?php
/**
 * admin/roles.php - THE "dynamic" part of the CMS
 *
 * A grid of roles x permissions. Tick a box, press Save, and that role
 * gains (or loses) the ability on the very next page load - no PHP edits.
 * You can also add brand-new roles here (e.g. "Moderator").
 *
 * Under the hood a save is:  DELETE all rows for the role from
 * role_permissions, then INSERT one row per ticked box - inside a
 * TRANSACTION, so it is all-or-nothing.
 */
require_once __DIR__ . '/../includes/auth.php';
require_permission('manage_roles');

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    // ---- Add a new role ----
    if (isset($_POST['new_role'])) {
        $label = trim($_POST['label'] ?? '');
        $level = max(1, min(99, (int) ($_POST['level'] ?? 20)));     // only admin is 100
        if ($label === '') {
            flash('danger', 'Role name is required.');
        } else {
            $name = str_replace('-', '_', slugify($label));
            try {
                $pdo->prepare('INSERT INTO roles (role_name, label, level, description) VALUES (?, ?, ?, ?)')
                    ->execute([$name, $label, $level, trim($_POST['description'] ?? '')]);
                log_activity("Created role $name");
                flash('success', "Role \"$label\" created. Tick its permissions below.");
            } catch (PDOException $e) {
                flash('danger', 'A role with that name already exists.');   // UNIQUE constraint
            }
        }
        redirect('admin/roles.php');
    }

    // ---- Delete a role (only if nobody uses it) ----
    if (isset($_POST['delete_role'])) {
        $rid = (int) $_POST['delete_role'];
        $inUse = $pdo->prepare('SELECT COUNT(*) FROM users WHERE role_id = ?');
        $inUse->execute([$rid]);
        if ($inUse->fetchColumn() > 0) {
            flash('danger', 'Move its users to another role first (FOREIGN KEY ... ON DELETE RESTRICT).');
        } else {
            $pdo->prepare("DELETE FROM roles WHERE role_id = ? AND role_name <> 'admin'")->execute([$rid]);
            log_activity("Deleted role #$rid");
            flash('success', 'Role deleted.');
        }
        redirect('admin/roles.php');
    }

    // ---- Save the whole permission grid ----
    $grid    = $_POST['perm'] ?? [];          // perm[role_id][] = permission_id
    $roleIds = $pdo->query('SELECT role_id, role_name FROM roles')->fetchAll(PDO::FETCH_KEY_PAIR);
    $default = (int) ($_POST['default_role'] ?? 0);

    $pdo->beginTransaction();
    try {
        $del = $pdo->prepare('DELETE FROM role_permissions WHERE role_id = ?');
        $ins = $pdo->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)');

        foreach ($roleIds as $rid => $rname) {
            if ($rname === 'admin') continue;              // admin always keeps everything
            $del->execute([$rid]);
            foreach ($grid[$rid] ?? [] as $pid) {
                $ins->execute([$rid, (int) $pid]);
            }
        }
        if (isset($roleIds[$default])) {
            $pdo->exec('UPDATE roles SET is_default = 0');
            $pdo->prepare('UPDATE roles SET is_default = 1 WHERE role_id = ?')->execute([$default]);
        }
        $pdo->commit();
        log_activity('Updated role permissions');
        flash('success', 'Permissions saved. Log in as another role to see the difference.');
    } catch (Throwable $e) {
        $pdo->rollBack();                                    // undo EVERYTHING if one insert failed
        flash('danger', 'Save failed - nothing was changed.');
    }
    redirect('admin/roles.php');
}

$roles = $pdo->query(
    'SELECT r.*, (SELECT COUNT(*) FROM users u WHERE u.role_id = r.role_id) AS user_count
       FROM roles r ORDER BY r.level DESC'
)->fetchAll();
$permissions = $pdo->query('SELECT * FROM permissions ORDER BY perm_group, permission_id')->fetchAll();

// Build a quick lookup: $has["roleId-permId"] = true
$has = [];
foreach ($pdo->query('SELECT role_id, permission_id FROM role_permissions') as $rp) {
    $has[$rp['role_id'] . '-' . $rp['permission_id']] = true;
}

$page_title = 'Roles & Permissions';
require __DIR__ . '/_layout_top.php';
?>
<h1>Roles &amp; Permissions</h1>
<p class="muted">Tick boxes and save. Administrator is locked so you can never lock yourself out.</p>

<form method="post">
    <?= csrf_field() ?>
    <div class="table-scroll">
    <table class="grid">
        <tr>
            <th>Permission</th>
            <?php foreach ($roles as $r): ?>
                <th><?= e($r['label']) ?><br><small class="muted">level <?= (int) $r['level'] ?> &middot; <?= (int) $r['user_count'] ?> users</small></th>
            <?php endforeach; ?>
        </tr>
        <?php $group = null; foreach ($permissions as $p): ?>
            <?php if ($p['perm_group'] !== $group): $group = $p['perm_group']; ?>
                <tr class="group"><td colspan="<?= count($roles) + 1 ?>"><?= e($group) ?></td></tr>
            <?php endif; ?>
            <tr>
                <td><?= e($p['label']) ?><br><code class="small"><?= e($p['perm_key']) ?></code></td>
                <?php foreach ($roles as $r): ?>
                    <td class="center">
                        <input type="checkbox"
                               name="perm[<?= $r['role_id'] ?>][]"
                               value="<?= $p['permission_id'] ?>"
                               <?= isset($has[$r['role_id'] . '-' . $p['permission_id']]) ? 'checked' : '' ?>
                               <?= $r['role_name'] === 'admin' ? 'disabled' : '' ?>>
                    </td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
        <tr class="group"><td colspan="<?= count($roles) + 1 ?>">Sign-ups</td></tr>
        <tr>
            <td>Default role for new registrations</td>
            <?php foreach ($roles as $r): ?>
                <td class="center"><input type="radio" name="default_role" value="<?= $r['role_id'] ?>"
                    <?= $r['is_default'] ? 'checked' : '' ?> <?= $r['role_name'] === 'admin' ? 'disabled' : '' ?>></td>
            <?php endforeach; ?>
        </tr>
    </table>
    </div>
    <button class="btn">Save permissions</button>
</form>

<div class="row" style="margin-top:2rem">
    <form method="post" class="card">
        <h3>Add a role</h3>
        <?= csrf_field() ?>
        <input type="hidden" name="new_role" value="1">
        <label>Role name <input name="label" placeholder="e.g. Moderator" required></label>
        <label>Level (1-99) <input type="number" name="level" min="1" max="99" value="50"></label>
        <label>Description <input name="description"></label>
        <button class="btn">Create role</button>
    </form>

    <div class="card">
        <h3>Delete a role</h3>
        <p class="muted small">Only roles with 0 users can be deleted.</p>
        <?php foreach ($roles as $r): if ($r['role_name'] === 'admin' || $r['user_count'] > 0 || $r['is_default']) continue; ?>
            <form method="post" onsubmit="return confirm('Delete role <?= e($r['label']) ?>?')">
                <?= csrf_field() ?>
                <input type="hidden" name="delete_role" value="<?= $r['role_id'] ?>">
                <button class="link danger">Delete "<?= e($r['label']) ?>"</button>
            </form>
        <?php endforeach; ?>
    </div>
</div>
<?php require __DIR__ . '/_layout_bottom.php'; ?>
