<?php
/**
 * admin/index.php - Dashboard
 * Every role that has view_dashboard lands here, but the numbers are
 * scoped: authors see THEIR posts, editors/admins see everything.
 */
require_once __DIR__ . '/../includes/auth.php';
require_permission('view_dashboard');

$uid      = $_SESSION['user_id'];
$seeAll   = can('edit_any_post');
$scope    = $seeAll ? '' : ' WHERE user_id = ' . (int) $uid;   // safe: cast to int

// One query, several counts, using SUM(condition)
$postStats = db()->query(
    "SELECT COUNT(*) AS total,
            SUM(status = 'published') AS published,
            SUM(status = 'pending')   AS pending,
            SUM(status = 'draft')     AS draft,
            COALESCE(SUM(views), 0)   AS views
       FROM posts $scope"
)->fetch();

$cards = [
    ['Posts' . ($seeAll ? '' : ' (yours)'), $postStats['total']],
    ['Published', $postStats['published'] ?? 0],
    ['Pending review', $postStats['pending'] ?? 0],
    ['Total views', $postStats['views']],
];
if (can('manage_users')) {
    $cards[] = ['Users', db()->query('SELECT COUNT(*) FROM users')->fetchColumn()];
}
if (can('moderate_comments')) {
    $cards[] = ['Comments to approve', db()->query("SELECT COUNT(*) FROM comments WHERE status='pending'")->fetchColumn()];
}

// Users per role - a GROUP BY demo for class
$roleCounts = can('manage_users') ? db()->query(
    'SELECT r.label, COUNT(u.user_id) AS total
       FROM roles r LEFT JOIN users u ON u.role_id = r.role_id
   GROUP BY r.role_id ORDER BY r.level DESC'
)->fetchAll() : [];

$recent = db()->query(
    "SELECT title, status, updated_at FROM posts $scope ORDER BY updated_at DESC LIMIT 5"
)->fetchAll();

$page_title = 'Dashboard';
require __DIR__ . '/_layout_top.php';
?>
<h1>Dashboard</h1>
<p class="muted">Hello <?= e(current_user()['full_name']) ?>. The links on the left are generated from your role's permissions.</p>

<div class="stats">
    <?php foreach ($cards as [$label, $value]): ?>
        <div class="stat"><span><?= (int) $value ?></span><?= e($label) ?></div>
    <?php endforeach; ?>
</div>

<?php if ($roleCounts): ?>
    <h3>Users per role</h3>
    <table>
        <tr><th>Role</th><th>Users</th></tr>
        <?php foreach ($roleCounts as $r): ?>
            <tr><td><?= e($r['label']) ?></td><td><?= (int) $r['total'] ?></td></tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

<h3>Recently updated posts</h3>
<table>
    <tr><th>Title</th><th>Status</th><th>Updated</th></tr>
    <?php foreach ($recent as $p): ?>
        <tr><td><?= e($p['title']) ?></td>
            <td><span class="status status-<?= e($p['status']) ?>"><?= e($p['status']) ?></span></td>
            <td><?= e($p['updated_at']) ?></td></tr>
    <?php endforeach; ?>
</table>
<?php require __DIR__ . '/_layout_bottom.php'; ?>
