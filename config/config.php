<?php
/**
 * config/config.php
 * ------------------------------------------------------------
 * The ONLY file you should need to edit to connect to MySQL.
 * Defaults match a fresh XAMPP install (user "root", no password).
 * ------------------------------------------------------------
 */

define('DB_HOST', '127.0.0.1');   // or 'localhost'
define('DB_PORT', '3306');        // XAMPP's MySQL port (check the Control Panel if you changed it)
define('DB_NAME', 'nic_cms');
define('DB_USER', 'root');
define('DB_PASS', '');            // XAMPP default is an empty password
define('DB_CHARSET', 'utf8mb4');

// The folder name inside htdocs. If you put the project at
// C:\xampp\htdocs\nic-cms  then the URL is  http://localhost/nic-cms
define('BASE_URL', '/nic-cms');

// Show PHP errors while we are learning. Turn OFF on a real server!
define('DEBUG', true);

if (DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}
