<?php
/**
 * admin/post-edit.php          -> create a new post
 * admin/post-edit.php?id=5     -> edit post 5
 *
 * One form for both INSERT and UPDATE - the presence of an id decides.
 * Status rule: without publish_posts you can only save 'draft' or 'pending'.
 */
require_once __DIR__ . '/../includes/auth.php';
require_permission('create_posts');

$uid    = (int) $_SESSION['user_id'];
$id     = isset($_GET['id']) ? (int) $_GET['id'] : null;
$errors = [];

// Defaults for a brand-new post
$post = ['title' => '', 'excerpt' => '', 'content' => '', 'category_id' => null, 'status' => 'draft'];

// ---- Load existing post (and check ownership) ----
if ($id) {
    $stmt = db()->prepare('SELECT * FROM posts WHERE post_id = ?');
    $stmt->execute([$id]);
    $post = $stmt->fetch();

    if (!$post) {
        flash('danger', 'Post not found.');
        redirect('admin/posts.php');
    }
    if ($post['user_id'] != $uid && !can('edit_any_post')) {
        flash('danger', 'You can only edit your own posts.');
        redirect('admin/posts.php');
    }
}

// Which statuses may this user choose? (comes from permissions, not role names)
$allowedStatuses = can('publish_posts') ? ['draft', 'pending', 'published'] : ['draft', 'pending'];

// ---- Save ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $post['title']       = trim($_POST['title'] ?? '');
    $post['excerpt']     = trim($_POST['excerpt'] ?? '');
    $post['content']     = trim($_POST['content'] ?? '');
    $post['category_id'] = ($_POST['category_id'] ?? '') !== '' ? (int) $_POST['category_id'] : null;
    $newStatus           = $_POST['status'] ?? 'draft';

    if ($post['title'] === '')   $errors[] = 'Title is required.';
    if ($post['content'] === '') $errors[] = 'Content is required.';
    if (!in_array($newStatus, $allowedStatuses, true)) {
        $errors[] = 'Your role is not allowed to set status "' . $newStatus . '".';
    }

    if (!$errors) {
        $slug = unique_slug('posts', $post['title'], $id);
        // published_at is set the FIRST time a post is published
        $publishedAt = ($newStatus === 'published')
            ? ($post['published_at'] ?? date('Y-m-d H:i:s'))
            : null;

        if ($id) {
            $stmt = db()->prepare(
                'UPDATE posts SET title = ?, slug = ?, excerpt = ?, content = ?,
                                  category_id = ?, status = ?, published_at = ?
                  WHERE post_id = ?'
            );
            $stmt->execute([$post['title'], $slug, $post['excerpt'], $post['content'],
                            $post['category_id'], $newStatus, $publishedAt, $id]);
            log_activity("Updated post #$id");
        } else {
            $stmt = db()->prepare(
                'INSERT INTO posts (user_id, category_id, title, slug, excerpt, content, status, published_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$uid, $post['category_id'], $post['title'], $slug, $post['excerpt'],
                            $post['content'], $newStatus, $publishedAt]);
            $id = (int) db()->lastInsertId();          // the new AUTO_INCREMENT id
            log_activity("Created post #$id");
        }
        flash('success', 'Post saved as ' . $newStatus . '.');
        redirect('admin/post-edit.php?id=' . $id);
    }
    $post['status'] = $newStatus;
}

$categories = db()->query('SELECT category_id, name FROM categories ORDER BY name')->fetchAll();

$page_title = $id ? 'Edit post' : 'New post';
require __DIR__ . '/_layout_top.php';
?>
<h1><?= $id ? 'Edit post' : 'New post' ?></h1>
<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>

<form method="post" class="card">
    <?= csrf_field() ?>
    <label>Title <input name="title" value="<?= e($post['title']) ?>" required></label>
    <label>Excerpt <input name="excerpt" maxlength="300" value="<?= e($post['excerpt']) ?>"></label>
    <label>Content <textarea name="content" rows="12" required><?= e($post['content']) ?></textarea></label>
    <div class="row">
        <label>Category
            <select name="category_id">
                <option value="">- none -</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= $c['category_id'] ?>" <?= $post['category_id'] == $c['category_id'] ? 'selected' : '' ?>>
                        <?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Status
            <select name="status">
                <?php foreach ($allowedStatuses as $s): ?>
                    <option <?= $post['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>
    <?php if (!can('publish_posts')): ?>
        <p class="muted small">Your role can't publish. Choose <b>pending</b> to send it to an editor.</p>
    <?php endif; ?>
    <button class="btn">Save post</button>
    <a href="<?= url('admin/posts.php') ?>">Back to posts</a>
</form>
<?php require __DIR__ . '/_layout_bottom.php'; ?>
