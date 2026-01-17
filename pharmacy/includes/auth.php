<?php
// Prevent direct access
if (basename($_SERVER['SCRIPT_NAME']) === basename(__FILE__)) {
    exit('❌ Direct access not allowed.');
}

session_start();

// Session timeout: 30 minutes of inactivity
$timeout = 1800; // 30 * 60 seconds
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > $timeout) {
    session_unset();
    session_destroy();
    header('Location: index.php?msg=session_expired');
    exit();
}

// Refresh login time on activity
if (isset($_SESSION['login_time'])) {
    $_SESSION['login_time'] = time();
}

// 🔒 Require login
function require_login() {
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['error'] = "🔒 Please log in to access this page.";
        header('Location: ' . (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php'));
        exit();
    }
}

// 🔐 Require specific role (e.g., 'admin', 'staff')
function require_role($required_role) {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $required_role) {
        $_SESSION['error'] = "🚫 Access denied. Insufficient privileges.";
        header('Location: dashboard.php');
        exit();
    }
}

// ✅ Optional: Get current user ID safely
function get_user_id() {
    return $_SESSION['user_id'] ?? null;
}
?>