<?php
// ============================================================
// index.php
// Root entry point — redirect based on login state
// Gyansetu Library Management System
// ============================================================

require_once 'config/db.php';
require_once 'includes/auth.php';

startSession();

if (isLoggedIn()) {
    if (isAdmin()) {
        redirect(BASE_URL . '/admin/dashboard.php');
    } else {
        redirect(BASE_URL . '/user/home.php');
    }
} else {
    redirect(BASE_URL . '/login.php');
}
?>