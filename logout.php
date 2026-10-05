<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    logout();
    session_start();                      // fresh session just to carry the flash message
    flash('success', 'You have been logged out.');
}
redirect('');
