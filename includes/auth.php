<?php
// ============================================================
// includes/auth.php
// Session Authentication Helpers
// Gyansetu Library Management System
// ============================================================

/**
 * Start a secure PHP session if not already started.
 */
function startSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Check if any user (student or admin) is logged in.
 * Redirects to login page if not authenticated.
 */
function requireLogin() {
    startSession();
    if (!isset($_SESSION['user_id'])) {
        header("Location: " . BASE_URL . "/login.php");
        exit();
    }
}

/**
 * Check if the logged-in user is an admin.
 * Redirects to user home if not admin.
 */
function requireAdmin() {
    startSession();
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        header("Location: " . BASE_URL . "/login.php");
        exit();
    }
}

/**
 * Check if the logged-in user is a student.
 * Redirects to login if not a student.
 */
function requireStudent() {
    startSession();
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
        header("Location: " . BASE_URL . "/login.php");
        exit();
    }
}

/**
 * Returns true if user is currently logged in.
 */
function isLoggedIn() {
    startSession();
    return isset($_SESSION['user_id']);
}

/**
 * Returns true if logged-in user is admin.
 */
function isAdmin() {
    startSession();
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Get current logged-in user's ID.
 */
function getCurrentUserId() {
    startSession();
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current logged-in user's name.
 */
function getCurrentUserName() {
    startSession();
    return $_SESSION['user_name'] ?? 'Guest';
}

/**
 * Destroy session and log the user out.
 */
function logoutUser() {
    startSession();
    session_unset();
    session_destroy();
    header("Location: " . BASE_URL . "/login.php");
    exit();
}

/**
 * Sanitize user input to prevent XSS and SQL injection.
 *
 * @param string $data Raw user input
 * @return string Sanitized string
 */
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

/**
 * Redirect to a given URL.
 *
 * @param string $url Target URL
 */
function redirect($url) {
    header("Location: $url");
    exit();
}

/**
 * Set a flash message in session.
 *
 * @param string $type  'success' | 'error' | 'info' | 'warning'
 * @param string $msg   Message text
 */
function setFlash($type, $msg) {
    startSession();
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

/**
 * Display and clear flash message from session.
 * Returns HTML string or empty string.
 */
function getFlash() {
    startSession();
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        $icon = [
            'success' => '✓',
            'error'   => '✗',
            'info'    => 'ℹ',
            'warning' => '⚠'
        ][$f['type']] ?? 'ℹ';
        return "<div class=\"alert alert-{$f['type']}\"><span class=\"alert-icon\">{$icon}</span> {$f['msg']}</div>";
    }
    return '';
}

/**
 * Calculate overdue fine for a given due date.
 *
 * @param string $due_date  Date string (Y-m-d)
 * @return float Fine amount
 */
function calculateFine($due_date) {
    $today = new DateTime();
    $due   = new DateTime($due_date);
    if ($today > $due) {
        $diff = $today->diff($due);
        return $diff->days * FINE_PER_DAY;
    }
    return 0;
}
?>