<?php
// ============================================================
// config/db.php
// Database + App Configuration
// Gyansetu — St. Lawrence College Library System
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'YourNewStrongPassword123!');
define('DB_NAME', 'gyansetu');

// College Info
define('COLLEGE_NAME',    'St. Lawrence College');
define('COLLEGE_SHORT',   'SLC');
define('COLLEGE_EMAIL',   'library@stlawrence.edu.np');
define('COLLEGE_PHONE',   '+977-01-XXXXXXX');
define('COLLEGE_ADDRESS', 'Chabahil, Kathmandu, Nepal');

// Library Settings
define('FINE_PER_DAY',  5);     // Rs 5 per day overdue
define('BORROW_DAYS',   2);    // 14 days borrow period
define('MAX_BORROWS',   3);     // Max books per student at once

// PHP Mailer Settings (Gmail SMTP)
define('MAIL_HOST',     'smtp.gmail.com');
define('MAIL_PORT',     587);
define('MAIL_USERNAME', 'your_gmail@gmail.com');   // ← Change this
define('MAIL_PASSWORD', 'your_app_password');       // ← Change this (Gmail App Password)
define('MAIL_FROM',     'library@stlawrence.edu.np');
define('MAIL_FROM_NAME', 'St. Lawrence College Library');
define('MAIL_ENABLED',  false); // Set true after configuring Gmail

// Auto-detect BASE_URL
$protocol  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host      = $_SERVER['HTTP_HOST'] ?? 'localhost';
$docRoot   = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$scriptDir = str_replace('\\', '/', dirname(dirname(__FILE__)));
$subPath   = str_replace($docRoot, '', $scriptDir);
define('BASE_URL', $protocol . '://' . $host . $subPath);

function getDBConnection() {
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if (!$conn) {
        die("Database connection failed: " . mysqli_connect_error());
    }
    mysqli_set_charset($conn, 'utf8mb4');
    return $conn;
}
?>