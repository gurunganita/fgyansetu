<?php
// ============================================================
// logout.php
// Destroys session and redirects to login
// Gyansetu Library Management System
// ============================================================

require_once 'config/db.php';
require_once 'includes/auth.php';

logoutUser(); // Defined in includes/auth.php — destroys session + redirects
?>