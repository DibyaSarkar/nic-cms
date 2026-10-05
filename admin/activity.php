<?php
/**
 * admin/activity.php - last 100 actions (audit trail)
 */
require_once __DIR__ . '/../includes/auth.php';
require_permission('view_activity_log');

$logs = db()->query(
    'SELECT l.*, u.username
       FROM activity_log l LEFT JOIN users u ON u.user_id = l.user_id
   ORDER BY l.log_id DESC LIMIT 100'
)->fetchAll();

$page_title = 'Activity log';
require __DIR__ . '/_layout_top.php';
?>
<h1>Activity log</h1>
<table>
    <tr><th>When</th><th>User</th><th>Action</th><th>IP</th></tr>
    <?php foreach ($logs as $l): ?>
        <tr><td><?= e($l['created_at']) ?></td><td><?= e($l['username'] ?? '(deleted)') ?></td>
            <td><?= e($l['action']) ?></td><td><?= e($l['ip_address']) ?></td></tr>
    <?php endforeach; ?>
</table>
<?php require __DIR__ . '/_layout_bottom.php'; ?>
