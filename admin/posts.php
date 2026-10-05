<?php
/**
 * admin/posts.php - list posts, delete posts
 *
 * Ownership rule:
 *   edit_any_post  -> see / edit / delete EVERY post   (editor, admin)
 *   otherwise      -> only posts where user_id = me    (author)
 */
require_once __DIR__ . '/../includes/auth.php';
require_permission('create_posts');

$uid    = (int) $_SESSION['user_id'];
$seeAll = can('edit_any_post');

// ---- DELETE (always via POST + CSRF, never via a GET link) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    verify_csrf();
    $sql    = 'DELETE FROM posts WHERE post_id = ?' . ($seeAll ? '' : ' AND user_id = ?');
    $params = $seeAll ? [$_POST['delete_id']] : [$_POST['delete_id'], $uid];
    $stmt   = db()->prepare($sql);
    $stmt->execute($params);

    if ($stmt->rowCount()) {
        log_activity('Deleted post #' . (int) $_POST['delete_id']);
        flash('success', 'Post deleted.');
    } else {
        flash('danger', 'Post not found or not yours.');
    }
    redirect('admin/posts.php');
}

// ---- LIST with optional status filter + search ----
$status = $_GET['status'] ?? '';
$q      = trim($_GET['q'] ?? '');

$where  = [];
$params = [];
if (!$seeAll)                                           { $where[] = 'p.user_id = ?';  $params[] = $uid; }
if (in_array($status, ['draft', 'pending', 'published'])) { $where[] = 'p.status = ?';   $params[] = $status; }
if ($q !== '')                                           { $where[] = 'p.title LIKE ?'; $params[] = "%$q%"; }

$sql = 'SELECT p.post_id, p.title, p.slug, p.status, p.views, p.updated_at,
               u.username, c.name AS category
          FROM posts p
          JOIN users u ON u.user_id = p.user_id
     LEFT JOIN categories c ON c.category_id = p.category_id'
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . ' ORDER BY p.updated_at DESC';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$posts = $stmt->fetchAll();

$page_title = 'Posts';
require __DIR__ . '/_layout_top.php';
?>
<div class="row-between">
    <h1><?= $seeAll ? 'All posts' : 'My posts' ?></h1>
    <a class="btn" href="<?= url('admin/post-edit.php') ?>">+ New post</a>
</div>

<form class="filters" method="get">
    <input name="q" placeholder="Search titles..." value="<?= e($q) ?>">
    <select name="status">
        <option value="">Any status</option>
        <?php foreach (['draft', 'pending', 'published'] as $s): ?>
            <option <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn btn-small">Filter</button>
</form>

<table>
    <tr><th>Title</th><th>Author</th><th>Category</th><th>Status</th><th>Views</th><th></th></tr>
    <?php foreach ($posts as $p): ?>
        <tr>
            <td><?= e($p['title']) ?></td>
            <td><?= e($p['username']) ?></td>
            <td><?= e($p['category'] ?? '-') ?></td>
            <td><span class="status status-<?= e($p['status']) ?>"><?= e($p['status']) ?></span></td>
            <td><?= (int) $p['views'] ?></td>
            <td class="actions">
                <a href="<?= url('admin/post-edit.php?id=' . $p['post_id']) ?>">Edit</a>
                <?php if ($p['status'] === 'published'): ?>
                    <a href="<?= url('post.php?slug=' . urlencode($p['slug'])) ?>" target="_blank">View</a>
                <?php endif; ?>
                <form method="post" onsubmit="return confirm('Delete this post?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="delete_id" value="<?= $p['post_id'] ?>">
                    <button class="link danger">Delete</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$posts): ?><tr><td colspan="6" class="muted">No posts found.</td></tr><?php endif; ?>
</table>
<?php require __DIR__ . '/_layout_bottom.php'; ?>
