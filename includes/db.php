<?php
/**
 * includes/db.php
 * ------------------------------------------------------------
 * Creates ONE PDO connection and reuses it for the whole request.
 *
 *   $pdo = db();
 *   $stmt = $pdo->prepare('SELECT * FROM users WHERE user_id = ?');
 *   $stmt->execute([$id]);
 *   $user = $stmt->fetch();
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/../config/config.php';

function db(): PDO
{
    static $pdo = null;          // "static" = remembered between calls

    if ($pdo === null) {
        // DSN = Data Source Name: which driver, which server, which database
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT
             . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // throw errors we can catch
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // rows as ['column' => value]
            PDO::ATTR_EMULATE_PREPARES   => false,                  // real prepared statements
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Never show raw DB errors to visitors on a live site.
            http_response_code(500);
            $msg = DEBUG ? $e->getMessage() : 'Please try again later.';
            exit('<h1>Database connection failed</h1><p>' . htmlspecialchars($msg) . '</p>'
               . '<p>Is MySQL started in the XAMPP Control Panel? Did you import database/cms.sql?</p>');
        }
    }
    return $pdo;
}
