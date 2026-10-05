<?php
/**
 * index.php - public home page
 * Shows PUBLISHED posts, newest first, with pagination and an optional
 * category filter (?category=databases). Posts-per-page comes from settings.
 */
require_once __DIR__ . '/includes/auth.php';

$perPage = max(1, (int) setting('posts_per_page', '5'));
$page    = max(1, (int) ($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;
$catSlug = $_GET['category'] ?? '';

// Build the WHERE clause step by step, keeping values in $params (never in the SQL string)
$where  = "p.status = 'published'";
$params = [];
if ($catSlug !== '') {
    $where   .= ' AND c.slug = ?';
    $params[] = $catSlug;
}

// 1) How many posts match? (for the page links)
$countStmt = db()->prepare("SELECT COUNT(*) FROM posts p LEFT JOIN categories c ON c.category_id = p.category_id WHERE $where");
$countStmt->execute($params);
$total      = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));

// 2) Get this page's posts - JOIN pulls in the author name and category name
$sql = "SELECT p.title, p.slug, p.excerpt, p.published_at, p.views,
               u.full_name AS author, c.name AS category, c.slug AS category_slug
          FROM posts p
          JOIN users u      ON u.user_id = p.user_id
     LEFT JOIN categories c ON c.category_id = p.category_id
         WHERE $where
      ORDER BY p.published_at DESC
         LIMIT $perPage OFFSET $offset";        // both are integers we cast ourselves
$stmt = db()->prepare($sql);
$stmt->execute($params);
$posts = $stmt->fetchAll();

// Sidebar: categories with how many published posts each has
$categories = db()->query(
    "SELECT c.name, c.slug, COUNT(p.post_id) AS total
       FROM categories c
  LEFT JOIN posts p ON p.category_id = c.category_id AND p.status = 'published'
   GROUP BY c.category_id ORDER BY c.name"
)->fetchAll();

$page_title = 'Home';
require __DIR__ . '/includes/header.php';
?>
<div class="layout">
    <div>
        <?php if ($catSlug): ?>
            <p class="muted">Filtered by category: <strong><?= e($catSlug) ?></strong> &middot;
               <a href="<?= url() ?>">show all</a></p>
        <?php endif; ?>

        <?php if (!$posts): ?>
            <p>No posts yet.</p>
        <?php endif; ?>

        <?php foreach ($posts as $post): ?>
            <article class="card">
                <h2><a href="<?= url('post.php?slug=' . urlencode($post['slug'])) ?>"><?= e($post['title']) ?></a></h2>
                <p class="meta">
                    By <?= e($post['author']) ?> &middot;
                    <?= date('M j, Y', strtotime($post['published_at'])) ?>
                    <?php if ($post['category']): ?>
                        &middot; <a href="<?= url('?category=' . urlencode($post['category_slug'])) ?>"><?= e($post['category']) ?></a>
                    <?php endif; ?>
                    &middot; <?= (int) $post['views'] ?> views
                </p>
                <p><?= e($post['excerpt']) ?></p>
            </article>
        <?php endforeach; ?>

        <?php if ($totalPages > 1): ?>
            <nav class="pager">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a class="<?= $i === $page ? 'current' : '' ?>"
                       href="<?= url('?page=' . $i . ($catSlug ? '&category=' . urlencode($catSlug) : '')) ?>"><?= $i ?></a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    </div>

    <aside class="card">
        <h3>Categories</h3>
        <ul class="plain">
            <?php foreach ($categories as $c): ?>
                <li><a href="<?= url('?category=' . urlencode($c['slug'])) ?>"><?= e($c['name']) ?></a>
                    <span class="muted">(<?= (int) $c['total'] ?>)</span></li>
            <?php endforeach; ?>
        </ul>
    </aside>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
