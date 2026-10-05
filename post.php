<?php
/**
 * post.php?slug=...  - one post, its approved comments, and a comment form
 */
require_once __DIR__ . '/includes/auth.php';

$slug = $_GET['slug'] ?? '';

$stmt = db()->prepare(
    "SELECT p.*, u.full_name AS author, c.name AS category
       FROM posts p
       JOIN users u ON u.user_id = p.user_id
  LEFT JOIN categories c ON c.category_id = p.category_id
      WHERE p.slug = ? AND p.status = 'published'"
);
$stmt->execute([$slug]);
$post = $stmt->fetch();

if (!$post) {
    http_response_code(404);
    $page_title = 'Not found';
    require __DIR__ . '/includes/header.php';
    echo '<h1>Post not found</h1>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

// Handle a new comment (POST request)
$commentsOn = setting('allow_comments') === '1';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $commentsOn) {
    verify_csrf();
    require_login();
    $body = trim($_POST['body'] ?? '');
    if ($body === '') {
        flash('danger', 'Comment cannot be empty.');
    } else {
        // Moderators' comments go live straight away; everyone else waits for approval
        $status = can('moderate_comments') ? 'approved' : 'pending';
        db()->prepare('INSERT INTO comments (post_id, user_id, body, status) VALUES (?, ?, ?, ?)')
            ->execute([$post['post_id'], $_SESSION['user_id'], $body, $status]);
        flash('success', $status === 'approved' ? 'Comment posted.' : 'Thanks! Your comment is waiting for approval.');
    }
    redirect('post.php?slug=' . urlencode($slug));   // Post/Redirect/Get: refresh won't re-submit
}

// Count a view (UPDATE ... SET views = views + 1)
db()->prepare('UPDATE posts SET views = views + 1 WHERE post_id = ?')->execute([$post['post_id']]);

$cStmt = db()->prepare(
    "SELECT cm.body, cm.created_at, u.username
       FROM comments cm JOIN users u ON u.user_id = cm.user_id
      WHERE cm.post_id = ? AND cm.status = 'approved'
   ORDER BY cm.created_at"
);
$cStmt->execute([$post['post_id']]);
$comments = $cStmt->fetchAll();

$page_title = $post['title'];
require __DIR__ . '/includes/header.php';
?>
<article class="card">
    <h1><?= e($post['title']) ?></h1>
    <p class="meta">By <?= e($post['author']) ?> &middot; <?= date('M j, Y', strtotime($post['published_at'])) ?>
        <?= $post['category'] ? '&middot; ' . e($post['category']) : '' ?></p>
    <?= paragraphs($post['content']) ?>
</article>

<section class="card">
    <h3>Comments (<?= count($comments) ?>)</h3>
    <?php foreach ($comments as $c): ?>
        <div class="comment">
            <strong><?= e($c['username']) ?></strong>
            <span class="muted"><?= date('M j, Y g:ia', strtotime($c['created_at'])) ?></span>
            <p><?= nl2br(e($c['body'])) ?></p>
        </div>
    <?php endforeach; ?>

    <?php if (!$commentsOn): ?>
        <p class="muted">Comments are turned off in Settings.</p>
    <?php elseif (is_logged_in()): ?>
        <form method="post">
            <?= csrf_field() ?>
            <label>Add a comment <textarea name="body" rows="3" required></textarea></label>
            <button class="btn">Post comment</button>
        </form>
    <?php else: ?>
        <p><a href="<?= url('login.php') ?>">Log in</a> to comment.</p>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
