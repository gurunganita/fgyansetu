<?php
// ============================================================
// admin/users.php
// Student User Management
// Gyansetu Library Management System
// ============================================================

require_once '../config/db.php';
require_once '../includes/auth.php';

requireAdmin();

$conn = getDBConnection();

// ── Handle Delete ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['post_action'] ?? '') === 'delete') {
    $delId = intval($_POST['user_id'] ?? 0);
    if ($delId) {
        mysqli_query($conn, "DELETE FROM users WHERE id=$delId AND role='student'");
        setFlash('success', 'User deleted.');
    }
    redirect(BASE_URL . '/admin/users.php');
}

// ── Fetch All Students ───────────────────────────────────────
$search = sanitize($_GET['search'] ?? '');
$users  = [];

if ($search) {
    $like = "%$search%";
    $s    = mysqli_prepare($conn, "SELECT u.*, (SELECT COUNT(*) FROM issued_books ib WHERE ib.user_id=u.id AND ib.status IN ('issued','overdue')) AS active_borrows FROM users u WHERE u.role='student' AND (u.name LIKE ? OR u.email LIKE ?) ORDER BY u.created_at DESC");
    mysqli_stmt_bind_param($s, 'ss', $like, $like);
    mysqli_stmt_execute($s);
    $res = mysqli_stmt_get_result($s);
} else {
    $res = mysqli_query($conn, "SELECT u.*, (SELECT COUNT(*) FROM issued_books ib WHERE ib.user_id=u.id AND ib.status IN ('issued','overdue')) AS active_borrows FROM users u WHERE u.role='student' ORDER BY u.created_at DESC");
}

while ($row = mysqli_fetch_assoc($res)) $users[] = $row;

$pageTitle = 'Manage Users';
require_once '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-users"></i> Manage Students</h1>
        <p class="page-subtitle"><?php echo count($users); ?> registered students</p>
    </div>
</div>

<!-- Search -->
<div class="search-bar-wrapper">
    <form method="GET" class="search-form">
        <input type="text" name="search" class="form-control" placeholder="Search by name or email..."
            value="<?php echo $search; ?>">
        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
        <?php if ($search): ?><a href="users.php" class="btn btn-outline"><i class="fas fa-times"></i> Clear</a><?php endif; ?>
    </form>
</div>

<?php if (empty($users)): ?>
<div class="empty-state">
    <i class="fas fa-users"></i>
    <h3>No students found</h3>
    <p>No students have registered yet.</p>
</div>
<?php else: ?>
<div class="table-wrapper">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Active Borrows</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php $i=1; foreach ($users as $user): ?>
                <tr>
                    <td><?php echo $i++; ?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:.6rem;">
                            <div style="width:34px;height:34px;border-radius:50%;background:var(--mahogany);display:flex;align-items:center;justify-content:center;color:var(--ivory);font-size:.85rem;font-weight:600;flex-shrink:0;">
                                <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                            </div>
                            <?php echo sanitize($user['name']); ?>
                        </div>
                    </td>
                    <td style="color:var(--text-light);font-size:.88rem;"><?php echo sanitize($user['email']); ?></td>
                    <td style="font-size:.88rem;"><?php echo $user['phone'] ? sanitize($user['phone']) : '—'; ?></td>
                    <td>
                        <?php if ($user['active_borrows'] > 0): ?>
                            <span class="badge badge-warning"><?php echo $user['active_borrows']; ?> book(s)</span>
                        <?php else: ?>
                            <span style="color:var(--text-light);font-size:.85rem;">None</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:.85rem;"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="post_action" value="delete">
                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                            <button type="submit" class="btn btn-danger btn-sm"
                                data-confirm="Delete user '<?php echo sanitize($user['name']); ?>'? All their records will be removed.">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php
mysqli_close($conn);
require_once '../includes/footer.php';
?>