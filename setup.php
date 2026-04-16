<?php
// ============================================================
// setup.php
// ONE-TIME SETUP SCRIPT
// Gyansetu — St. Lawrence College Library
// ============================================================
// Run once at: http://localhost:8080/setup.php
// DELETE this file after running for security!
// ============================================================

require_once 'config/db.php';

$conn    = getDBConnection();
$results = [];
$errors  = [];

// ── Step 1: Set Admin Password ───────────────────────────────
$adminHash  = password_hash('admin123', PASSWORD_DEFAULT);
$adminEmail = 'admin@stlawrence.edu.np';

$stmt = mysqli_prepare($conn,
    "UPDATE users SET password=? WHERE email=? AND role='admin'");
mysqli_stmt_bind_param($stmt, 'ss', $adminHash, $adminEmail);
mysqli_stmt_execute($stmt);

if (mysqli_stmt_affected_rows($stmt) > 0) {
    $results[] = "✓ Admin password set successfully.";
} else {
    // Admin might not exist yet — try to insert
    $ins = mysqli_prepare($conn,
        "INSERT IGNORE INTO users (name, email, password, role, course)
         VALUES ('Admin', ?, ?, 'admin', 'admin')");
    mysqli_stmt_bind_param($ins, 'ss', $adminEmail, $adminHash);
    mysqli_stmt_execute($ins);

    if (mysqli_stmt_affected_rows($ins) > 0) {
        $results[] = "✓ Admin account created successfully.";
    } else {
        $errors[]  = "⚠ Admin account not found or already exists with correct password.";
    }
}

// ── Step 2: Add course column to users if not exists ─────────
$check = mysqli_query($conn,
    "SHOW COLUMNS FROM users LIKE 'course'");
if (mysqli_num_rows($check) === 0) {
    mysqli_query($conn,
        "ALTER TABLE users
         ADD COLUMN course ENUM('BSc CSIT','BCA','admin')
         DEFAULT 'BSc CSIT' AFTER role");
    $results[] = "✓ Added 'course' column to users table.";
} else {
    $results[] = "✓ 'course' column already exists in users.";
}

// ── Step 3: Add course column to books if not exists ─────────
$check2 = mysqli_query($conn,
    "SHOW COLUMNS FROM books LIKE 'course'");
if (mysqli_num_rows($check2) === 0) {
    mysqli_query($conn,
        "ALTER TABLE books
         ADD COLUMN course ENUM('BSc CSIT','BCA','Both')
         DEFAULT 'Both' AFTER genre");
    $results[] = "✓ Added 'course' column to books table.";
} else {
    $results[] = "✓ 'course' column already exists in books.";
}

// ── Step 4: Update issued_books status enum ──────────────────
mysqli_query($conn,
    "ALTER TABLE issued_books
     MODIFY status ENUM('pending','issued','returned','overdue','cancelled')
     DEFAULT 'pending'");
$results[] = "✓ Updated issued_books status enum (pending/issued/returned/overdue/cancelled).";

// ── Step 5: Create notifications table if not exists ─────────
$createNotif = mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        type ENUM('approval','rejection','due_reminder','fine','reservation','general')
             DEFAULT 'general',
        is_read TINYINT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB
");
if ($createNotif) {
    $results[] = "✓ Notifications table ready.";
} else {
    $errors[]  = "⚠ Notifications table error: " . mysqli_error($conn);
}

// ── Step 6: Create wishlist table (for compatibility) ─────────
$createWish = mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS wishlist (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        book_id INT NOT NULL,
        added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_wishlist (user_id, book_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE
    ) ENGINE=InnoDB
");
if ($createWish) {
    $results[] = "✓ Wishlist table ready (for compatibility).";
} else {
    $errors[]  = "⚠ Wishlist table error: " . mysqli_error($conn);
}

// ── Step 7: Create uploads folder if missing ─────────────────
$uploadsDir = __DIR__ . '/uploads';
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
    $results[] = "✓ Created /uploads folder.";
} else {
    $results[] = "✓ /uploads folder exists.";
}

// ── Step 8: Verify database tables ───────────────────────────
$tables = ['users','books','issued_books','reservations','fines',
           'notifications','wishlist'];
$missingTables = [];
foreach ($tables as $table) {
    $chk = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    if (mysqli_num_rows($chk) === 0) {
        $missingTables[] = $table;
    }
}

if (empty($missingTables)) {
    $results[] = "✓ All database tables verified.";
} else {
    $errors[] = "⚠ Missing tables: " . implode(', ', $missingTables) .
                ". Please import sql/gyansetu.sql first.";
}

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Setup | Gyansetu</title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'Segoe UI', system-ui, sans-serif;
    background: linear-gradient(135deg, #1a0808, #4A1C1C);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem;
}
.card {
    background: #fff;
    border-radius: 16px;
    padding: 2.5rem;
    max-width: 560px;
    width: 100%;
    box-shadow: 0 20px 60px rgba(0,0,0,.4);
}
.logo {
    text-align: center;
    margin-bottom: 1.75rem;
}
.logo-icon { font-size: 3rem; }
.logo h1 {
    font-size: 1.8rem;
    color: #4A1C1C;
    margin: .4rem 0 .2rem;
}
.logo p { color: #888; font-size: .88rem; }
h2 {
    font-size: 1.1rem;
    color: #4A1C1C;
    margin-bottom: 1rem;
    padding-bottom: .5rem;
    border-bottom: 2px solid #f0e8d8;
}
.result-list { list-style: none; margin-bottom: 1.5rem; }
.result-list li {
    padding: .5rem .75rem;
    margin-bottom: .4rem;
    border-radius: 6px;
    font-size: .9rem;
}
.success { background: #d4edda; color: #155724; }
.error   { background: #f8d7da; color: #721c24; }
.creds-box {
    background: #faf6ef;
    border: 2px solid #C9973C;
    border-radius: 10px;
    padding: 1.25rem;
    margin-bottom: 1.5rem;
}
.creds-box h3 {
    color: #4A1C1C;
    font-size: 1rem;
    margin-bottom: .75rem;
}
.cred-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: .4rem 0;
    border-bottom: 1px solid #e8d8b8;
    font-size: .9rem;
}
.cred-row:last-child { border-bottom: none; }
.cred-label { color: #666; font-weight: 600; }
.cred-value {
    font-family: monospace;
    background: #fff;
    padding: .25rem .65rem;
    border-radius: 5px;
    font-size: .88rem;
    font-weight: 700;
    color: #4A1C1C;
    border: 1px solid #e8d8b8;
}
.warning-box {
    background: #fff3cd;
    border: 1.5px solid #ffc107;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1.5rem;
    font-size: .88rem;
    color: #856404;
}
.btn {
    display: inline-block;
    padding: .75rem 1.5rem;
    border-radius: 8px;
    font-size: .95rem;
    font-weight: 700;
    text-decoration: none;
    text-align: center;
    transition: all .2s;
    cursor: pointer;
    border: none;
}
.btn-primary {
    background: #4A1C1C;
    color: #fff;
    width: 100%;
    display: block;
}
.btn-primary:hover { background: #6B2D2D; }
.btn-outline {
    background: transparent;
    color: #4A1C1C;
    border: 2px solid #4A1C1C;
    width: 100%;
    display: block;
    margin-top: .75rem;
}
.btn-outline:hover { background: #4A1C1C; color: #fff; }
</style>
</head>
<body>
<div class="card">

    <div class="logo">
        <div class="logo-icon">&#128218;</div>
        <h1>Gyansetu Setup</h1>
        <p><?php echo COLLEGE_NAME; ?> — Library Management System</p>
    </div>

    <h2>&#9881; Setup Results</h2>

    <ul class="result-list">
        <?php foreach ($results as $r): ?>
        <li class="success"><?php echo $r; ?></li>
        <?php endforeach; ?>
        <?php foreach ($errors as $e): ?>
        <li class="error"><?php echo $e; ?></li>
        <?php endforeach; ?>
    </ul>

    <!-- Admin Credentials -->
    <div class="creds-box">
        <h3>&#128272; Admin Login Credentials</h3>
        <div class="cred-row">
            <span class="cred-label">Email</span>
            <span class="cred-value">admin@stlawrence.edu.np</span>
        </div>
        <div class="cred-row">
            <span class="cred-label">Password</span>
            <span class="cred-value">admin123</span>
        </div>
        <div class="cred-row">
            <span class="cred-label">Login URL</span>
            <span class="cred-value">/admin/login.php</span>
        </div>
    </div>

    <!-- Warning -->
    <div class="warning-box">
        &#9888; <strong>IMPORTANT:</strong>
        Delete <code>setup.php</code> after setup is complete
        to prevent unauthorized access!
        <br><br>
        Run this command:
        <code style="display:block;margin-top:.5rem;background:#fff;
                     padding:.4rem .75rem;border-radius:4px;font-size:.82rem;">
            rm /home/anita/Documents/fgyansetu/setup.php
        </code>
    </div>

    <!-- Buttons -->
    <a href="<?php echo BASE_URL; ?>/admin/login.php" class="btn btn-primary">
        &#128274; Go to Admin Login
    </a>
    <a href="<?php echo BASE_URL; ?>/login.php" class="btn btn-outline">
        &#128100; Go to Student Login
    </a>

</div>
</body>
</html>