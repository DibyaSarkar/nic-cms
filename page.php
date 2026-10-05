<?php
/**
 * page.php?slug=about  - a static page stored in the `pages` table
 */
require_once __DIR__ . '/includes/auth.php';

$stmt = db()->prepare("SELECT * FROM pages WHERE slug = ? AND status = 'published'");
$stmt->execute([$_GET['slug'] ?? '']);
$pageRow = $stmt->fetch();

if (!$pageRow) {
    http_response_code(404);
}

$page_title = $pageRow['title'] ?? 'Not found';
require __DIR__ . '/includes/header.php';
?>
<article class="card">
    <?php if ($pageRow): ?>
        <h1><?= e($pageRow['title']) ?></h1>
        <?= paragraphs($pageRow['content']) ?>
    <?php else: ?>
        <h1>Page not found</h1>
    <?php endif; ?>
</article>
<?php require __DIR__ . '/includes/footer.php'; ?>
