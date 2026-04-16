<?php
// ============================================================
// includes/header.php — Shared Header + Navigation
// Gyansetu — St. Lawrence College Library
// ============================================================
require_once __DIR__ . '/../includes/mailer.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' | Gyansetu' : 'Gyansetu | ' . COLLEGE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <link rel="icon" href="<?php echo BASE_URL; ?>/assets/images/favicon.png" type="image/png">
</head>
<body>

<?php if (isLoggedIn()):
    $conn          = getDBConnection();
    $currentUserId = getCurrentUserId();
    $unreadCount   = isAdmin() ? 0 : getUnreadCount($conn, $currentUserId);
?>
<nav class="navbar">
    <div class="nav-container">
        <!-- Brand -->
        <a href="<?php echo BASE_URL; ?>/<?php echo isAdmin() ? 'admin/dashboard.php' : 'user/home.php'; ?>"
           class="nav-brand">
            <span class="brand-icon">&#128218;</span>
            <span class="brand-text">Gyansetu</span>
            <span class="brand-college"><?php echo COLLEGE_SHORT; ?></span>
        </a>

        <button class="nav-toggle" id="navToggle">&#9776;</button>

        <ul class="nav-links" id="navLinks">
            <?php if (isAdmin()): ?>
                <li><a href="<?php echo BASE_URL; ?>/admin/dashboard.php"
                    class="nav-link <?php echo basename($_SERVER['PHP_SELF'])==='dashboard.php'?'active':''; ?>">
                    &#9699; Dashboard</a></li>
                <li><a href="<?php echo BASE_URL; ?>/admin/books.php"
                    class="nav-link <?php echo basename($_SERVER['PHP_SELF'])==='books.php'?'active':''; ?>">
                    &#128218; Books</a></li>
                <li><a href="<?php echo BASE_URL; ?>/admin/users.php"
                    class="nav-link <?php echo basename($_SERVER['PHP_SELF'])==='users.php'?'active':''; ?>">
                    &#128100; Users</a></li>
                <li><a href="<?php echo BASE_URL; ?>/admin/issued.php"
                    class="nav-link <?php echo basename($_SERVER['PHP_SELF'])==='issued.php'?'active':''; ?>">
                    &#128196; Issued</a></li>
                <li><a href="<?php echo BASE_URL; ?>/admin/reservations.php"
                    class="nav-link <?php echo basename($_SERVER['PHP_SELF'])==='reservations.php'?'active':''; ?>">
                    &#128278; Reservations</a></li>
                <li><a href="<?php echo BASE_URL; ?>/admin/fines.php"
                    class="nav-link <?php echo basename($_SERVER['PHP_SELF'])==='fines.php'?'active':''; ?>">
                    &#128176; Fines</a></li>
            <?php else: ?>
                <li><a href="<?php echo BASE_URL; ?>/user/home.php"
                    class="nav-link <?php echo basename($_SERVER['PHP_SELF'])==='home.php'?'active':''; ?>">
                    &#127968; Home</a></li>
                <li><a href="<?php echo BASE_URL; ?>/user/search.php"
                    class="nav-link <?php echo basename($_SERVER['PHP_SELF'])==='search.php'?'active':''; ?>">
                    &#128269; Search</a></li>
                <li><a href="<?php echo BASE_URL; ?>/user/borrowed.php"
                    class="nav-link <?php echo basename($_SERVER['PHP_SELF'])==='borrowed.php'?'active':''; ?>">
                    &#128196; My Books</a></li>
                <li><a href="<?php echo BASE_URL; ?>/user/fines.php"
                    class="nav-link <?php echo basename($_SERVER['PHP_SELF'])==='fines.php'?'active':''; ?>">
                    &#128176; Fines</a></li>
                <!-- Notification Bell -->
                <li>
                    <a href="<?php echo BASE_URL; ?>/user/notifications.php"
                       class="nav-link notif-bell <?php echo basename($_SERVER['PHP_SELF'])==='notifications.php'?'active':''; ?>"
                       title="Notifications">
                        &#128276;
                        <?php if ($unreadCount > 0): ?>
                        <span class="notif-badge"><?php echo $unreadCount; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endif; ?>

            <li class="nav-user-info">
                <span class="user-badge">
                    &#128100;
                    <?php echo sanitize(getCurrentUserName()); ?>
                    <?php if (!isAdmin()):
                        $userCourse = $_SESSION['course'] ?? '';
                        if ($userCourse):
                    ?>
                    <span class="course-tag"><?php echo $userCourse; ?></span>
                    <?php endif; endif; ?>
                </span>
                <a href="<?php echo BASE_URL; ?>/logout.php" class="btn-logout">
                    &#128682; Logout
                </a>
            </li>
        </ul>
    </div>
</nav>

<style>
.brand-college{
    font-size:.65rem;font-weight:700;
    background:rgba(201,151,60,.25);
    color:#E8B96B;
    padding:.15rem .5rem;border-radius:4px;
    margin-left:.3rem;letter-spacing:.06em;
}
.notif-bell{position:relative;}
.notif-badge{
    position:absolute;top:-4px;right:-6px;
    background:#e74c3c;color:#fff;
    font-size:.6rem;font-weight:700;
    width:16px;height:16px;border-radius:50%;
    display:flex;align-items:center;justify-content:center;
}
.course-tag{
    font-size:.65rem;font-weight:700;
    background:rgba(201,151,60,.2);
    color:#E8B96B;padding:.1rem .4rem;
    border-radius:4px;margin-left:.3rem;
}
</style>

<?php endif; ?>

<main class="main-content">
<?php echo getFlash(); ?>