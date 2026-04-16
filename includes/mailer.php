<?php
// ============================================================
// includes/mailer.php
// Email Notification System
// Gyansetu — St. Lawrence College Library
// ============================================================
// Uses PHP mail() as fallback, PHPMailer if configured
// ============================================================

require_once __DIR__ . '/../config/db.php';

/**
 * saveNotification()
 * Always saves notification to DB regardless of email
 */
function saveNotification($conn, $userId, $title, $message, $type = 'general') {
    $stmt = mysqli_prepare($conn,
        "INSERT INTO notifications (user_id, title, message, type)
         VALUES (?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, 'isss', $userId, $title, $message, $type);
    mysqli_stmt_execute($stmt);
}

/**
 * sendEmail()
 * Sends email using PHP mail() function
 * For production: replace with PHPMailer
 */
function sendEmail($toEmail, $toName, $subject, $body) {
    if (!MAIL_ENABLED) return false;

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n";
    $headers .= "Reply-To: " . MAIL_FROM . "\r\n";

    $fullBody = emailTemplate($subject, $body, $toName);
    return mail($toEmail, $subject, $fullBody, $headers);
}

/**
 * emailTemplate()
 * Beautiful HTML email template
 */
function emailTemplate($subject, $content, $name) {
    return '
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  body{font-family:Arial,sans-serif;background:#f5f0e8;margin:0;padding:20px;}
  .wrap{max-width:580px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.1);}
  .header{background:linear-gradient(135deg,#4A1C1C,#6B2D2D);padding:30px;text-align:center;}
  .header h1{color:#fff;margin:0;font-size:24px;}
  .header p{color:rgba(255,255,255,.7);margin:5px 0 0;font-size:13px;}
  .body{padding:30px;}
  .greeting{font-size:16px;color:#333;margin-bottom:15px;}
  .content{color:#555;line-height:1.7;font-size:14px;}
  .highlight{background:#faf6ef;border-left:4px solid #C9973C;padding:15px;border-radius:0 8px 8px 0;margin:20px 0;}
  .btn{display:inline-block;background:#4A1C1C;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:bold;margin-top:15px;}
  .footer{background:#f5f0e8;padding:20px;text-align:center;font-size:12px;color:#999;}
  .footer strong{color:#4A1C1C;}
</style>
</head>
<body>
<div class="wrap">
  <div class="header">
    <h1>&#128218; Gyansetu Library</h1>
    <p>' . COLLEGE_NAME . ' | Library Management System</p>
  </div>
  <div class="body">
    <p class="greeting">Dear <strong>' . htmlspecialchars($name) . '</strong>,</p>
    <div class="content">' . $content . '</div>
  </div>
  <div class="footer">
    <strong>' . COLLEGE_NAME . '</strong><br>
    ' . COLLEGE_ADDRESS . ' | ' . COLLEGE_EMAIL . '<br>
    This is an automated message from the Library System.
  </div>
</div>
</body>
</html>';
}

// ============================================================
// NOTIFICATION FUNCTIONS
// ============================================================

/**
 * notifyBorrowRequest()
 * Student submits borrow request → notify student + save DB
 */
function notifyBorrowRequest($conn, $userId, $userName, $userEmail, $bookTitle, $dueDate) {
    $title   = "Borrow Request Submitted";
    $message = "Your request to borrow \"$bookTitle\" has been submitted successfully. " .
               "Please wait for admin approval. You will be notified once approved. " .
               "Please visit the library with your ID card to collect the book after approval.";

    // Save in-app notification
    saveNotification($conn, $userId, $title, $message, 'general');

    // Send email
    $body = "
        <p>Your borrow request has been submitted successfully!</p>
        <div class='highlight'>
            <strong>&#128218; Book:</strong> $bookTitle<br>
            <strong>&#128197; Expected Due Date:</strong> $dueDate<br>
            <strong>&#9203; Status:</strong> Pending Admin Approval
        </div>
        <p>Please visit the library with your college ID card to collect the book once your request is approved.</p>
        <p><strong>Note:</strong> Requests are processed in FIFO order (first come, first served).</p>
    ";
    sendEmail($userEmail, $userName,
        "[Gyansetu] Borrow Request Submitted - $bookTitle", $body);
}

/**
 * notifyBorrowApproved()
 * Admin approves → notify student
 */
function notifyBorrowApproved($conn, $userId, $userName, $userEmail, $bookTitle, $dueDate) {
    $title   = "Borrow Request Approved!";
    $message = "Your request to borrow \"$bookTitle\" has been APPROVED! " .
               "Please visit the library to collect your book. Due date: $dueDate.";

    saveNotification($conn, $userId, $title, $message, 'approval');

    $body = "
        <p>Great news! Your borrow request has been <strong style='color:green;'>APPROVED</strong>!</p>
        <div class='highlight'>
            <strong>&#128218; Book:</strong> $bookTitle<br>
            <strong>&#128197; Due Date:</strong> $dueDate<br>
            <strong>&#9989; Status:</strong> Approved — Ready to Collect
        </div>
        <p><strong>Please visit the library as soon as possible to collect your book.</strong></p>
        <p>&#9888; Please return the book by the due date to avoid fines of Rs " . FINE_PER_DAY . " per day.</p>
    ";
    sendEmail($userEmail, $userName,
        "[Gyansetu] ✓ Book Approved - Please Collect: $bookTitle", $body);
}

/**
 * notifyBorrowRejected()
 * Admin rejects → notify student
 */
function notifyBorrowRejected($conn, $userId, $userName, $userEmail, $bookTitle, $reason = '') {
    $title   = "Borrow Request Rejected";
    $message = "Your request to borrow \"$bookTitle\" has been rejected. " .
               ($reason ? "Reason: $reason. " : '') .
               "Please contact the library for more information.";

    saveNotification($conn, $userId, $title, $message, 'rejection');

    $body = "
        <p>We regret to inform you that your borrow request has been <strong style='color:red;'>rejected</strong>.</p>
        <div class='highlight'>
            <strong>&#128218; Book:</strong> $bookTitle<br>
            " . ($reason ? "<strong>Reason:</strong> $reason<br>" : '') . "
            <strong>&#128222; Contact:</strong> " . COLLEGE_PHONE . "
        </div>
        <p>Please visit the library or contact us for more information.</p>
    ";
    sendEmail($userEmail, $userName,
        "[Gyansetu] Borrow Request Update - $bookTitle", $body);
}

/**
 * notifyDueReminder()
 * Book due soon — remind student
 */
function notifyDueReminder($conn, $userId, $userName, $userEmail, $bookTitle, $dueDate) {
    $title   = "Book Due Soon: $bookTitle";
    $message = "Reminder: \"$bookTitle\" is due on $dueDate. " .
               "Please return it on time to avoid fines of Rs " . FINE_PER_DAY . " per day.";

    saveNotification($conn, $userId, $title, $message, 'due_reminder');

    $body = "
        <p>This is a friendly reminder that your borrowed book is due soon!</p>
        <div class='highlight'>
            <strong>&#128218; Book:</strong> $bookTitle<br>
            <strong>&#128197; Due Date:</strong> <strong style='color:red;'>$dueDate</strong><br>
            <strong>&#128176; Fine if late:</strong> Rs " . FINE_PER_DAY . " per day
        </div>
        <p>Please return the book to the library by the due date to avoid fines.</p>
    ";
    sendEmail($userEmail, $userName,
        "[Gyansetu] ⚠ Book Due Reminder - $bookTitle", $body);
}

/**
 * notifyFineCharged()
 * Fine recorded → notify student
 */
function notifyFineCharged($conn, $userId, $userName, $userEmail, $bookTitle, $amount) {
    $title   = "Fine Charged: Rs $amount";
    $message = "A fine of Rs $amount has been charged for late return of \"$bookTitle\". " .
               "Please pay at the library desk.";

    saveNotification($conn, $userId, $title, $message, 'fine');

    $body = "
        <p>A fine has been charged to your library account.</p>
        <div class='highlight'>
            <strong>&#128218; Book:</strong> $bookTitle<br>
            <strong>&#128176; Fine Amount:</strong> <strong style='color:red;'>Rs $amount</strong><br>
            <strong>&#128197; Fine Rate:</strong> Rs " . FINE_PER_DAY . " per day
        </div>
        <p>Please visit the library desk to clear your fine as soon as possible.</p>
    ";
    sendEmail($userEmail, $userName,
        "[Gyansetu] Fine Charged - Rs $amount", $body);
}

/**
 * notifyReservationAvailable()
 * Reserved book becomes available → notify student
 */
function notifyReservationAvailable($conn, $userId, $userName, $userEmail, $bookTitle) {
    $title   = "Reserved Book Available: $bookTitle";
    $message = "Good news! The book \"$bookTitle\" you reserved is now available. " .
               "Please visit the library to collect it soon.";

    saveNotification($conn, $userId, $title, $message, 'reservation');

    $body = "
        <p>The book you reserved is now available!</p>
        <div class='highlight'>
            <strong>&#128218; Book:</strong> $bookTitle<br>
            <strong>&#9989; Status:</strong> Available for collection
        </div>
        <p><strong>Please visit the library as soon as possible to collect your reserved book.</strong></p>
        <p>&#9888; Your reservation may be cancelled if not collected within 3 days.</p>
    ";
    sendEmail($userEmail, $userName,
        "[Gyansetu] 📚 Reserved Book Available - $bookTitle", $body);
}

/**
 * getUnreadCount()
 * Get unread notification count for navbar bell
 */
function getUnreadCount($conn, $userId) {
    $stmt = mysqli_prepare($conn,
        "SELECT COUNT(*) AS cnt FROM notifications
         WHERE user_id=? AND is_read=0");
    mysqli_stmt_bind_param($stmt, 'i', $userId);
    mysqli_stmt_execute($stmt);
    return mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['cnt'] ?? 0;
}
?>