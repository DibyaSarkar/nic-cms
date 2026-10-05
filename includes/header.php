<?php
/**
 * includes/header.php
 * Top of every page. The menu is built from the database:
 *   - pages with show_in_menu = 1, ordered by menu_order
 *   - an "Admin" link ONLY for roles with view_dashboard
 */
require_once __DIR__ . '/auth.php';

$menuPages = db()->query(
    "SELECT title, slug FROM pages
      WHERE show_in_menu = 1 AND status = 'published'
      ORDER BY menu_order, title"
)->fetchAll();

$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(($page_title ?? '') ? $page_title . ' | ' : '') ?><?= e(setting('site_name')) ?></title>
    <link rel="stylesheet" href="<?= url('assets/style.css') ?>">
</head>
<body>
<header class="site-header">
    <div class="wrap header-inner">
        <a class="brand" href="<?= url() ?>">
            <strong><?= e(setting('site_name')) ?></strong>
            <small><?= e(setting('site_tagline')) ?></small>
        </a>
        <nav class="main-nav">
            <a href="<?= url() ?>">Home</a>
            <?php foreach ($menuPages as $p): ?>
                <a href="<?= url('page.php?slug=' . urlencode($p['slug'])) ?>"><?= e($p['title']) ?></a>
            <?php endforeach; ?>

            <?php if ($user): ?>
                <?php if (can('view_dashboard')): ?>
                    <a class="btn btn-small" href="<?= url('admin/index.php') ?>">Admin</a>
                <?php endif; ?>
                <a href="<?= url('profile.php') ?>"><?= e($user['username']) ?>
                    <span class="badge badge-<?= e($user['role_name']) ?>"><?= e($user['role_label']) ?></span></a>
                <a href="<?= url('logout.php') ?>">Log out</a>
            <?php else: ?>
                <a href="<?= url('login.php') ?>">Log in</a>
                <?php if (setting('allow_registration') === '1'): ?>
                    <a class="btn btn-small" href="<?= url('register.php') ?>">Sign up</a>
                <?php endif; ?>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main class="wrap">
<?= show_flashes() ?>
