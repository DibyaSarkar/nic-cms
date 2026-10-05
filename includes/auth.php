<?php
/**
 * includes/auth.php
 * ------------------------------------------------------------
 * Sessions, login, and the permission system.
 *
 * The key idea: pages never check "is this an admin?".
 * They check "can this user do X?" -> can('manage_users')
 * The answer comes from the role_permissions table, so changing
 * a role's abilities is a database change, not a code change.
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** The logged-in user's row (with role info), or null for guests. */
function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $user = null;
        if (!empty($_SESSION['user_id'])) {
            $stmt = db()->prepare(
                'SELECT u.*, r.role_name, r.label AS role_label, r.level
                   FROM users u JOIN roles r ON r.role_id = u.role_id
                  WHERE u.user_id = ? AND u.status = \'active\''
            );
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch() ?: null;
        }
    }
    return $user;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

/** Load the current user's permission keys once per request. */
function user_permissions(): array
{
    static $perms = null;
    if ($perms === null) {
        $perms = [];
        if ($user = current_user()) {
            $stmt = db()->prepare(
                'SELECT p.perm_key
                   FROM role_permissions rp
                   JOIN permissions p ON p.permission_id = rp.permission_id
                  WHERE rp.role_id = ?'
            );
            $stmt->execute([$user['role_id']]);
            $perms = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
    }
    return $perms;
}

/** THE check used everywhere: can('publish_posts') */
function can(string $permission): bool
{
    return in_array($permission, user_permissions(), true);
}

/** Guards - put at the top of a page to protect it. */
function require_login(): void
{
    if (!is_logged_in()) {
        flash('warning', 'Please log in first.');
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'];
        redirect('login.php');
    }
}

function require_permission(string $permission): void
{
    require_login();
    if (!can($permission)) {
        http_response_code(403);
        $page_title = 'Access denied';
        require __DIR__ . '/header.php';
        echo '<div class="alert alert-danger">Your role (' . e(current_user()['role_label'])
           . ') does not have the <code>' . e($permission) . '</code> permission.</div>';
        require __DIR__ . '/footer.php';
        exit;
    }
}

/** Try to log in. Returns true on success. */
function attempt_login(string $login, string $password): bool
{
    // Accept username OR email
    $stmt = db()->prepare('SELECT user_id, password_hash, status FROM users
                            WHERE username = ? OR email = ? LIMIT 1');
    $stmt->execute([$login, $login]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($password, $row['password_hash'])) {
        return false;                 // same message for "no user" and "wrong password"
    }
    if ($row['status'] !== 'active') {
        flash('danger', 'This account is suspended.');
        return false;
    }

    session_regenerate_id(true);      // stop session-fixation attacks
    $_SESSION['user_id'] = $row['user_id'];

    db()->prepare('UPDATE users SET last_login = NOW() WHERE user_id = ?')->execute([$row['user_id']]);
    log_activity('Logged in');
    return true;
}

function logout(): void
{
    log_activity('Logged out');
    $_SESSION = [];
    session_destroy();
}
