<?php
// ============================================================
// user/notifications.php
// Student Notifications Page
// ============================================================
require_once '../config/db.php';
require_once '../includes/auth.php';

requireStudent();

$conn   = getDBConnection();
$userId = getCurrentUserId();

// Mark all as read
if (isset($_GET['mark_read'])) {
    mysqli_query($conn,
        "UPDATE notifications SET is_read=1 WHERE user_id=$userId");
    redirect(BASE_URL . '/user/notifications.php');
}

// Fetch notifications
$result = mysqli_query($conn,
    "SELECT * FROM notifications WHERE user_id=$userId
     ORDER BY created_at DESC LIMIT 50");
$notifications = [];
while ($row = mysqli_fetch_assoc($result)) $notifications[] = $row;

$pageTitle = 'Notifications';
require_once '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>&#128276; Notifications</h1>
        <p class="page-subtitle"><?php echo count($notifications); ?> total notifications</p>
    </div>
    <?php if (!empty($notifications)): ?>
    <a href="notifications.php?mark_read=1" class="btn btn-outline btn-sm">
        &#10003; Mark All Read
    </a>
    <?php endif; ?>
</div>

<?php if (empty($notifications)): ?>
<div class="empty-state">
    <div style="font-size:3rem;">&#128276;</div>
    <h3>No notifications yet</h3>
    <p>You will receive notifications for borrow approvals, due dates, fines and more.</p>
</div>
<?php else: ?>
<div style="display:flex;flex-direction:column;gap:.75rem;">
    <?php foreach ($notifications as $n):
        $icons = [
            'approval'     => '&#9989;',
            'rejection'    => '&#10060;',
            'due_reminder' => '&#9203;',
            'fine'         => '&#128176;',
            'reservation'  => '&#128278;',
            'general'      => '&#128218;'
        ];
        $colors = [
            'approval'     => '#d4edda',
            'rejection'    => '#f8d7da',
            'due_reminder' => '#fff3cd',
            'fine'         => '#f8d7da',
            'reservation'  => '#d1ecf1',
            'general'      => '#f5f0e8'
        ];
        $icon  = $icons[$n['type']]  ?? '&#128218;';
        $color = $colors[$n['type']] ?? '#f5f0e8';
    ?>
    <div style="background:<?php echo $n['is_read'] ? '#fff' : $color; ?>;
                border:1px solid var(--border);
                border-radius:10px;padding:1rem 1.25rem;
                display:flex;gap:1rem;align-items:flex-start;
                <?php echo !$n['is_read'] ? 'border-left:4px solid var(--mahogany);' : ''; ?>">
        <div style="font-size:1.5rem;flex-shrink:0;"><?php echo $icon; ?></div>
        <div style="flex:1;">
            <div style="font-weight:700;color:var(--mahogany);font-size:.95rem;margin-bottom:.25rem;">
                <?php echo sanitize($n['title']); ?>
                <?php if (!$n['is_read']): ?>
                <span style="background:var(--mahogany);color:#fff;font-size:.65rem;
                             padding:.15rem .5rem;border-radius:10px;margin-left:.5rem;">
                    NEW
                </span>
                <?php endif; ?>
            </div>
            <div style="color:var(--text-mid);font-size:.88rem;line-height:1.6;">
                <?php echo sanitize($n['message']); ?>
            </div>
            <div style="color:var(--text-light);font-size:.75rem;margin-top:.4rem;">
                <?php echo date('M d, Y h:i A', strtotime($n['created_at'])); ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php
mysqli_close($conn);
require_once '../includes/footer.php';
?>