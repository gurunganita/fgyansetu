<?php
// ============================================================
// user/fines.php
// Student Fine Management Page
// Gyansetu Library Management System
// ============================================================

require_once '../config/db.php';
require_once '../includes/auth.php';

requireStudent();

$conn   = getDBConnection();
$userId = getCurrentUserId();

// ── Auto-calculate and store fines for overdue books ───────
$overdueSQL = "SELECT ib.id, ib.due_date FROM issued_books ib
               WHERE ib.user_id = ? AND ib.status IN ('issued','overdue') AND ib.due_date < CURDATE()";
$stmt = mysqli_prepare($conn, $overdueSQL);
mysqli_stmt_bind_param($stmt, 'i', $userId);
mysqli_stmt_execute($stmt);
$overdueResult = mysqli_stmt_get_result($stmt);

while ($od = mysqli_fetch_assoc($overdueResult)) {
    $fine = calculateFine($od['due_date']);

    // Check if fine already exists for this issued_book
    $chk = mysqli_prepare($conn, "SELECT id FROM fines WHERE issued_book_id = ?");
    mysqli_stmt_bind_param($chk, 'i', $od['id']);
    mysqli_stmt_execute($chk);
    mysqli_stmt_store_result($chk);

    if (mysqli_stmt_num_rows($chk) > 0) {
        // Update existing fine amount
        mysqli_query($conn, "UPDATE fines SET amount=$fine WHERE issued_book_id=" . $od['id']);
    } else {
        // Insert new fine record
        $ins = mysqli_prepare($conn, "INSERT INTO fines (user_id, issued_book_id, amount) VALUES (?,?,?)");
        mysqli_stmt_bind_param($ins, 'iid', $userId, $od['id'], $fine);
        mysqli_stmt_execute($ins);
    }

    // Mark issued book as overdue
    mysqli_query($conn, "UPDATE issued_books SET status='overdue' WHERE id=" . $od['id']);
}

// ── Fetch all fines for this user ───────────────────────────
$sql = "SELECT f.*, b.title, b.author, ib.due_date, ib.return_date
        FROM fines f
        JOIN issued_books ib ON f.issued_book_id = ib.id
        JOIN books b ON ib.book_id = b.id
        WHERE f.user_id = ?
        ORDER BY f.created_at DESC";
$stmt2 = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt2, 'i', $userId);
mysqli_stmt_execute($stmt2);
$fineResult = mysqli_stmt_get_result($stmt2);

$fines = [];
$totalUnpaid = 0;
while ($row = mysqli_fetch_assoc($fineResult)) {
    $fines[] = $row;
    if ($row['status'] === 'unpaid') {
        $totalUnpaid += $row['amount'];
    }
}

$pageTitle = 'My Fines';
require_once '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-coins"></i> My Fines</h1>
        <p class="page-subtitle">Late return fine: Rs <?php echo FINE_PER_DAY; ?> per day</p>
    </div>
    <?php if ($totalUnpaid > 0): ?>
    <div style="background:var(--error);color:#fff;padding:.75rem 1.25rem;border-radius:var(--radius-sm);text-align:right;">
        <div style="font-size:.8rem;opacity:.8;">Total Unpaid</div>
        <div style="font-size:1.5rem;font-weight:700;font-family:var(--font-heading);">Rs <?php echo number_format($totalUnpaid, 2); ?></div>
    </div>
    <?php endif; ?>
</div>

<?php if (empty($fines)): ?>
<div class="empty-state">
    <i class="fas fa-check-circle" style="color:var(--success);"></i>
    <h3>No fines!</h3>
    <p>You have no outstanding fines. Keep returning books on time!</p>
</div>

<?php else: ?>
<div class="table-wrapper">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Book</th>
                    <th>Due Date</th>
                    <th>Fine Amount</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; foreach ($fines as $fine): ?>
                <tr>
                    <td><?php echo $i++; ?></td>
                    <td>
                        <strong><?php echo sanitize($fine['title']); ?></strong><br>
                        <small style="color:var(--text-light);"><?php echo sanitize($fine['author']); ?></small>
                    </td>
                    <td><?php echo date('M d, Y', strtotime($fine['due_date'])); ?></td>
                    <td><strong style="color:var(--error);">Rs <?php echo number_format($fine['amount'], 2); ?></strong></td>
                    <td>
                        <span class="badge <?php echo $fine['status'] === 'paid' ? 'badge-success' : 'badge-danger'; ?>">
                            <?php echo ucfirst($fine['status']); ?>
                        </span>
                    </td>
                    <td style="font-size:.85rem;color:var(--text-light);">
                        <?php echo date('M d, Y', strtotime($fine['created_at'])); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<p class="mt-3" style="font-size:.85rem;color:var(--text-light);">
    <i class="fas fa-info-circle"></i>
    Please visit the library desk to pay your fines. Fine rate: <strong>Rs <?php echo FINE_PER_DAY; ?>/day</strong> after due date.
</p>
<?php endif; ?>

<?php
mysqli_close($conn);
require_once '../includes/footer.php';
?>