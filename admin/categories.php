<?php
/**
 * admin/categories.php - full CRUD on one screen
 *   Create: form at the bottom (no id)
 *   Read:   the table
 *   Update: ?edit=ID loads the row into the form
 *   Delete: POST delete_id  (posts in it become "no category" - ON DELETE SET NULL)
 */
require_once __DIR__ . '/../includes/auth.php';
require_permission('manage_categories');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (isset($_POST['delete_id'])) {
        db()->prepare('DELETE FROM categories WHERE category_id = ?')->execute([$_POST['delete_id']]);
        log_activity('Deleted category #' . (int) $_POST['delete_id']);
        flash('success', 'Category deleted. Its posts now have no category.');
        redirect('admin/categories.php');
    }

    $id   = (int) ($_POST['category_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');

    if ($name === '') {
        flash('danger', 'Name is required.');
    } elseif ($id) {
        db()->prepare('UPDATE categories SET name = ?, slug = ?, description = ? WHERE category_id = ?')
            ->execute([$name, unique_slug('categories', $name, $id), $desc, $id]);
        log_activity("Updated category #$id");
        flash('success', 'Category updated.');
    } else {
        db()->prepare('INSERT INTO categories (name, slug, description) VALUES (?, ?, ?)')
            ->execute([$name, unique_slug('categories', $name), $desc]);
        log_activity('Created category ' . $name);
        flash('success', 'Category added.');
    }
    redirect('admin/categories.php');
}

$editing = ['category_id' => 0, 'name' => '', 'description' => ''];
if (isset($_GET['edit'])) {
    $stmt = db()->prepare('SELECT * FROM categories WHERE category_id = ?');
    $stmt->execute([$_GET['edit']]);
    $editing = $stmt->fetch() ?: $editing;
}

$categories = db()->query(
    'SELECT c.*, COUNT(p.post_id) AS post_count
       FROM categories c LEFT JOIN posts p ON p.category_id = c.category_id
   GROUP BY c.category_id ORDER BY c.name'
)->fetchAll();

$page_title = 'Categories';
require __DIR__ . '/_layout_top.php';
?>
<h1>Categories</h1>
<table>
    <tr><th>Name</th><th>Slug</th><th>Posts</th><th></th></tr>
    <?php foreach ($categories as $c): ?>
        <tr>
            <td><?= e($c['name']) ?><br><small class="muted"><?= e($c['description']) ?></small></td>
            <td><code><?= e($c['slug']) ?></code></td>
            <td><?= (int) $c['post_count'] ?></td>
            <td class="actions">
                <a href="?edit=<?= $c['category_id'] ?>">Edit</a>
                <form method="post" onsubmit="return confirm('Delete this category?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="delete_id" value="<?= $c['category_id'] ?>">
                    <button class="link danger">Delete</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
</table>

<form method="post" class="card">
    <h3><?= $editing['category_id'] ? 'Edit category' : 'Add category' ?></h3>
    <?= csrf_field() ?>
    <input type="hidden" name="category_id" value="<?= (int) $editing['category_id'] ?>">
    <label>Name <input name="name" value="<?= e($editing['name']) ?>" required></label>
    <label>Description <input name="description" value="<?= e($editing['description']) ?>"></label>
    <button class="btn">Save</button>
    <?php if ($editing['category_id']): ?><a href="categories.php">Cancel</a><?php endif; ?>
</form>
<?php require __DIR__ . '/_layout_bottom.php'; ?>
