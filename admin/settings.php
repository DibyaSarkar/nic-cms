<?php
/**
 * admin/settings.php
 * The form is GENERATED from the settings table: add a row in phpMyAdmin
 * and a new field appears here automatically (input_type picks the widget).
 */
require_once __DIR__ . '/../includes/auth.php';
require_permission('manage_settings');

$settings = db()->query('SELECT * FROM settings ORDER BY setting_key')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $stmt = db()->prepare('UPDATE settings SET setting_value = ? WHERE setting_key = ?');
    foreach ($settings as $s) {
        $key = $s['setting_key'];
        $value = match ($s['input_type']) {
            'checkbox' => isset($_POST[$key]) ? '1' : '0',
            'number'   => (string) max(1, (int) ($_POST[$key] ?? 1)),
            default    => trim($_POST[$key] ?? ''),
        };
        $stmt->execute([$value, $key]);
    }
    log_activity('Updated site settings');
    flash('success', 'Settings saved - look at the header and footer.');
    redirect('admin/settings.php');
}

$page_title = 'Settings';
require __DIR__ . '/_layout_top.php';
?>
<h1>Site settings</h1>
<form method="post" class="card">
    <?= csrf_field() ?>
    <?php foreach ($settings as $s): ?>
        <?php if ($s['input_type'] === 'checkbox'): ?>
            <label class="check">
                <input type="checkbox" name="<?= e($s['setting_key']) ?>" <?= $s['setting_value'] === '1' ? 'checked' : '' ?>>
                <?= e($s['label']) ?></label>
        <?php else: ?>
            <label><?= e($s['label']) ?>
                <input type="<?= e($s['input_type']) ?>" name="<?= e($s['setting_key']) ?>" value="<?= e($s['setting_value']) ?>"></label>
        <?php endif; ?>
    <?php endforeach; ?>
    <button class="btn">Save settings</button>
</form>
<?php require __DIR__ . '/_layout_bottom.php'; ?>
