<?php
/**
 * Session configuration and security
 */

session_start();

// Session timeout: 30 minutes
$timeout_duration = 1800;

if (isset($_SESSION['last_activity'])) {
    if ((time() - $_SESSION['last_activity']) > $timeout_duration) {
        session_destroy();
        header('Location: login.php');
        exit;
    }
}

$_SESSION['last_activity'] = time();

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['admin_id']);
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

// Generate CSRF token
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF token
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>
