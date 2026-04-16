<?php
// ============================================================
// user/borrowed.php
// Student's Borrowing History and Active Loans
// Gyansetu Library Management System
// ============================================================

require_once '../config/db.php';
require_once '../includes/auth.php';

requireStudent();

$conn   = getDBConnection();
$userId = getCurrentUserId();

// ── Fetch all issued books for this user ────────────────────
$sql = "SELECT ib.*, b.title, b.author, b.genre, b.image
        FROM issued_books ib
        JOIN books b ON ib.book_id = b.id
        WHERE ib.user_id = ?
        ORDER BY ib.issue_date DESC";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $userId);
mysqli_stmt_execute($stmt);
$issued = mysqli_stmt_get_result($stmt);

$pageTitle = 'My Borrowed Books';
require_once '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-book"></i> My Borrowed Books</h1>
        <p class="page-subtitle">Your complete borrowing history and due dates</p>
    </div>
</div>

<?php
$rows = [];
while ($row = mysqli_fetch_assoc($issued)) {
    $rows[] = $row;
}
?>

<?php if (empty($rows)): ?>
<div class="empty-state">
    <i class="fas fa-book-open"></i>
    <h3>No borrowing history</h3>
    <p>You haven't borrowed any books yet.</p>
    <a href="<?php echo BASE_URL; ?>/user/search.php" class="btn btn-primary mt-2">
        <i class="fas fa-search"></i> Browse Books
    </a>
</div>

<?php else: ?>
<div class="table-wrapper">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Book</th>
                    <th>Issued On</th>
                    <th>Due Date</th>
                    <th>Returned</th>
                    <th>Status</th>
                    <th>Fine</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; foreach ($rows as $row): ?>
                <?php
                    $fine = 0;
                    if ($row['status'] === 'issued' && strtotime($row['due_date']) < time()) {
                        $fine = calculateFine($row['due_date']);
                        // Update status to overdue
                        mysqli_query($conn, "UPDATE issued_books SET status='overdue' WHERE id=" . $row['id']);
                    }
                ?>
                <tr>
                    <td><?php echo $i++; ?></td>
                    <td>
                        <strong><?php echo sanitize($row['title']); ?></strong><br>
                        <small style="color:var(--text-light);"><?php echo sanitize($row['author']); ?></small>
                    </td>
                    <td><?php echo date('M d, Y', strtotime($row['issue_date'])); ?></td>
                    <td><?php echo date('M d, Y', strtotime($row['due_date'])); ?></td>
                    <td>
                        <?php echo $row['return_date'] ? date('M d, Y', strtotime($row['return_date'])) : '<span style="color:var(--text-light);">—</span>'; ?>
                    </td>
                    <td>
                        <?php
                        $status = $row['status'];
                        if ($status === 'issued' && strtotime($row['due_date']) < time()) $status = 'overdue';
                        $badgeClass = ['issued' => 'badge-info', 'returned' => 'badge-success', 'overdue' => 'badge-danger'][$status] ?? 'badge-secondary';
                        ?>
                        <span class="badge <?php echo $badgeClass; ?>"><?php echo ucfirst($status); ?></span>
                    </td>
                    <td>
                        <?php if ($fine > 0): ?>
                            <span style="color:var(--error);font-weight:600;">Rs <?php echo $fine; ?></span>
                        <?php else: ?>
                            <span style="color:var(--success);">Rs 0</span>
                        <?php endif; ?>
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