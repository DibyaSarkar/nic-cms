<?php
/**
 * admin/comments.php - approve or delete comments
 */
require_once __DIR__ . '/../includes/auth.php';
require_permission('moderate_comments');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $cid = (int) ($_POST['comment_id'] ?? 0);
    if (($_POST['action'] ?? '') === 'approve') {
        db()->prepare("UPDATE comments SET status = 'approved' WHERE comment_id = ?")->execute([$cid]);
        log_activity("Approved comment #$cid");
        flash('success', 'Comment approved.');
    } elseif (($_POST['action'] ?? '') === 'delete') {
        db()->prepare('DELETE FROM comments WHERE comment_id = ?')->execute([$cid]);
        log_activity("Deleted comment #$cid");
        flash('success', 'Comment deleted.');
    }
    redirect('admin/comments.php');
}

$comments = db()->query(
    "SELECT cm.*, u.username, p.title AS post_title
       FROM comments cm
       JOIN users u ON u.user_id = cm.user_id
       JOIN posts p ON p.post_id = cm.post_id
   ORDER BY cm.status = 'pending' DESC, cm.created_at DESC"   // pending first
)->fetchAll();

$page_title = 'Comments';
require __DIR__ . '/_layout_top.php';
?>
<h1>Comments</h1>
<table>
    <tr><th>Comment</th><th>By</th><th>On post</th><th>Status</th><th></th></tr>
    <?php foreach ($comments as $c): ?>
        <tr>
            <td><?= e($c['body']) ?></td>
            <td><?= e($c['username']) ?></td>
            <td><?= e($c['post_title']) ?></td>
            <td><span class="status status-<?= e($c['status']) ?>"><?= e($c['status']) ?></span></td>
            <td class="actions">
                <?php foreach ($c['status'] === 'pending' ? ['approve', 'delete'] : ['delete'] as $action): ?>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="comment_id" value="<?= $c['comment_id'] ?>">
                        <button class="link <?= $action === 'delete' ? 'danger' : '' ?>" name="action" value="<?= $action ?>"><?= ucfirst($action) ?></button>
                    </form>
                <?php endforeach; ?>
            </td>
        </tr>
    <?php endforeach; ?>
</table>
<?php require __DIR__ . '/_layout_bottom.php'; ?>
