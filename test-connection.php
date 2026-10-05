<?php
/**
 * test-connection.php  -  STEP 3 of the lesson
 * Open http://localhost/nic-cms/test-connection.php
 * If you see a green table of users, PHP is talking to MySQL. Delete this file afterwards.
 */
require_once __DIR__ . '/config/config.php';

$dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo '<h2 style="color:green">Connected to MySQL!</h2>';
    echo '<p>Server version: ' . htmlspecialchars($pdo->getAttribute(PDO::ATTR_SERVER_VERSION)) . '</p>';

    $rows = $pdo->query(
        'SELECT u.username, r.label AS role FROM users u JOIN roles r ON r.role_id = u.role_id ORDER BY r.level DESC'
    )->fetchAll(PDO::FETCH_ASSOC);

    echo '<table border="1" cellpadding="6"><tr><th>Username</th><th>Role</th></tr>';
    foreach ($rows as $row) {
        echo '<tr><td>' . htmlspecialchars($row['username']) . '</td><td>' . htmlspecialchars($row['role']) . '</td></tr>';
    }
    echo '</table>';
} catch (PDOException $e) {
    echo '<h2 style="color:red">Connection failed</h2>';
    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<ul>
            <li>Is <b>MySQL</b> running (green) in the XAMPP Control Panel?</li>
            <li>Did you import <b>database/cms.sql</b> in phpMyAdmin?</li>
            <li>Check DB_USER / DB_PASS / DB_PORT in <b>config/config.php</b></li>
          </ul>';
}
