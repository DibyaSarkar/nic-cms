<?php
/**
 * includes/functions.php
 * Small helpers used everywhere. Each one does ONE job.
 */

require_once __DIR__ . '/db.php';

/** Escape output - use on EVERYTHING that came from a user or the database. */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/** Build a URL inside the project: url('admin/posts.php') */
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

/** Read a site setting from the `settings` table (cached per request). */
function setting(string $key, string $default = ''): string
{
    static $settings = null;
    if ($settings === null) {
        $rows = db()->query('SELECT setting_key, setting_value FROM settings')->fetchAll();
        $settings = array_column($rows, 'setting_value', 'setting_key');
    }
    return $settings[$key] ?? $default;
}

/** Turn "Hello PHP World!" into "hello-php-world" for URLs. */
function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-') ?: 'item';
}

/** Make a slug unique in a table by adding -2, -3 ... if needed. */
function unique_slug(string $table, string $title, ?int $ignoreId = null): string
{
    $allowed = ['posts' => 'post_id', 'pages' => 'page_id', 'categories' => 'category_id'];
    $idCol   = $allowed[$table];                 // whitelist - table names can't be bound with ?
    $base = slugify($title);
    $slug = $base;
    $n = 2;
    while (true) {
        $stmt = db()->prepare("SELECT COUNT(*) FROM $table WHERE slug = ? AND $idCol <> ?");
        $stmt->execute([$slug, $ignoreId ?? 0]);
        if ((int) $stmt->fetchColumn() === 0) return $slug;
        $slug = $base . '-' . $n++;
    }
}

/* ---------- Flash messages: show a message once, on the next page ---------- */

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $message];
}

function show_flashes(): string
{
    $html = '';
    foreach ($_SESSION['flash'] ?? [] as $f) {
        $html .= '<div class="alert alert-' . e($f['type']) . '">' . e($f['msg']) . '</div>';
    }
    unset($_SESSION['flash']);
    return $html;
}

/* ---------- CSRF protection: every form carries a secret token ---------- */

function csrf_field(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return '<input type="hidden" name="csrf" value="' . $_SESSION['csrf'] . '">';
}

function verify_csrf(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('Invalid form submission (CSRF token mismatch). Go back and try again.');
    }
}

/** Write a line to activity_log. */
function log_activity(string $action): void
{
    $stmt = db()->prepare('INSERT INTO activity_log (user_id, action, ip_address) VALUES (?, ?, ?)');
    $stmt->execute([$_SESSION['user_id'] ?? null, $action, $_SERVER['REMOTE_ADDR'] ?? null]);
}

/** Simple paragraph formatting for stored text (escape first, then add <p>). */
function paragraphs(string $text): string
{
    $parts = preg_split('/\R{2,}/', trim($text));
    return '<p>' . implode('</p><p>', array_map(fn($p) => nl2br(e($p)), $parts)) . '</p>';
}
