<?php
/**
 * admin/pages.php - manage static pages AND the site menu.
 * show_in_menu + menu_order decide what appears in the top navigation.
 */
require_once __DIR__ . '/../includes/auth.php';
require_permission('manage_pages');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (isset($_POST['delete_id'])) {
        db()->prepare('DELETE FROM pages WHERE page_id = ?')->execute([$_POST['delete_id']]);
        log_activity('Deleted page #' . (int) $_POST['delete_id']);
        flash('success', 'Page deleted.');
        redirect('admin/pages.php');
    }

    $id   = (int) ($_POST['page_id'] ?? 0);
    $data = [
        trim($_POST['title'] ?? ''),
        trim($_POST['content'] ?? ''),
        isset($_POST['show_in_menu']) ? 1 : 0,          // unchecked boxes are NOT sent at all
        (int) ($_POST['menu_order'] ?? 0),
        ($_POST['status'] ?? '') === 'draft' ? 'draft' : 'published',
        $_SESSION['user_id'],
    ];

    if ($data[0] === '' || $data[1] === '') {
        flash('danger', 'Title and content are required.');
        redirect('admin/pages.php' . ($id ? "?edit=$id" : ''));
    }

    if ($id) {
        $stmt = db()->prepare('UPDATE pages SET title=?, content=?, show_in_menu=?, menu_order=?, status=?, updated_by=?, slug=?
                                WHERE page_id=?');
        $stmt->execute([...$data, unique_slug('pages', $data[0], $id), $id]);
        log_activity("Updated page #$id");
    } else {
        $stmt = db()->prepare('INSERT INTO pages (title, content, show_in_menu, menu_order, status, updated_by, slug)
                               VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([...$data, unique_slug('pages', $data[0])]);
        log_activity('Created page ' . $data[0]);
    }
    flash('success', 'Page saved. Check the menu at the top!');
    redirect('admin/pages.php');
}

$editing = ['page_id' => 0, 'title' => '', 'content' => '', 'show_in_menu' => 1, 'menu_order' => 0, 'status' => 'published'];
if (isset($_GET['edit'])) {
    $stmt = db()->prepare('SELECT * FROM pages WHERE page_id = ?');
    $stmt->execute([$_GET['edit']]);
    $editing = $stmt->fetch() ?: $editing;
}

$pages = db()->query(
    'SELECT pg.*, u.username FROM pages pg LEFT JOIN users u ON u.user_id = pg.updated_by
   ORDER BY pg.menu_order, pg.title'
)->fetchAll();

$page_title = 'Pages';
require __DIR__ . '/_layout_top.php';
?>
<h1>Pages &amp; menu</h1>
<table>
    <tr><th>Order</th><th>Title</th><th>In menu?</th><th>Status</th><th>Last edited by</th><th></th></tr>
    <?php foreach ($pages as $pg): ?>
        <tr>
            <td><?= (int) $pg['menu_order'] ?></td>
            <td><?= e($pg['title']) ?></td>
            <td><?= $pg['show_in_menu'] ? 'Yes' : 'No' ?></td>
            <td><span class="status status-<?= e($pg['status']) ?>"><?= e($pg['status']) ?></span></td>
            <td><?= e($pg['username'] ?? '-') ?></td>
            <td class="actions">
                <a href="?edit=<?= $pg['page_id'] ?>">Edit</a>
                <a href="<?= url('page.php?slug=' . urlencode($pg['slug'])) ?>" target="_blank">View</a>
                <form method="post" onsubmit="return confirm('Delete this page?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="delete_id" value="<?= $pg['page_id'] ?>">
                    <button class="link danger">Delete</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
</table>

<form method="post" class="card">
    <h3><?= $editing['page_id'] ? 'Edit page' : 'Add page' ?></h3>
    <?= csrf_field() ?>
    <input type="hidden" name="page_id" value="<?= (int) $editing['page_id'] ?>">
    <label>Title <input name="title" value="<?= e($editing['title']) ?>" required></label>
    <label>Content <textarea name="content" rows="8" required><?= e($editing['content']) ?></textarea></label>
    <div class="row">
        <label class="check"><input type="checkbox" name="show_in_menu" <?= $editing['show_in_menu'] ? 'checked' : '' ?>> Show in menu</label>
        <label>Menu order <input type="number" name="menu_order" value="<?= (int) $editing['menu_order'] ?>"></label>
        <label>Status
            <select name="status">
                <?php foreach (['published', 'draft'] as $s): ?>
                    <option <?= $editing['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>
    <button class="btn">Save page</button>
    <?php if ($editing['page_id']): ?><a href="pages.php">Cancel</a><?php endif; ?>
</form>
<?php require __DIR__ . '/_layout_bottom.php'; ?>
