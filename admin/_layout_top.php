<?php
/**
 * admin/_layout_top.php
 * Opens the admin layout. The sidebar is DYNAMIC: each link appears
 * only if the logged-in user's role has the matching permission.
 * Add a row here + a permission in the DB = a new admin section.
 */
require_once __DIR__ . '/../includes/header.php';

$adminMenu = [
    // file              label            permission needed
    ['index.php',       'Dashboard',     'view_dashboard'],
    ['posts.php',       'Posts',         'create_posts'],
    ['categories.php',  'Categories',    'manage_categories'],
    ['pages.php',       'Pages',         'manage_pages'],
    ['comments.php',    'Comments',      'moderate_comments'],
    ['users.php',       'Users',         'manage_users'],
    ['roles.php',       'Roles & Permissions', 'manage_roles'],
    ['settings.php',    'Settings',      'manage_settings'],
    ['activity.php',    'Activity Log',  'view_activity_log'],
];
$currentFile = basename($_SERVER['SCRIPT_NAME']);
?>
<div class="admin-grid">
    <aside class="admin-side">
        <p class="side-title"><?= e(current_user()['role_label']) ?> panel</p>
        <?php foreach ($adminMenu as [$file, $label, $perm]): ?>
            <?php if (can($perm)): ?>
                <a href="<?= url('admin/' . $file) ?>"
                   class="<?= $currentFile === $file ? 'active' : '' ?>"><?= e($label) ?></a>
            <?php endif; ?>
        <?php endforeach; ?>
    </aside>
    <section class="admin-main">
