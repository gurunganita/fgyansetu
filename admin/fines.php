<?php
// ============================================================
// admin/fines.php
// Fine Management — View and Mark as Paid
// Gyansetu Library Management System
// ============================================================

require_once '../config/db.php';
require_once '../includes/auth.php';

requireAdmin();

$conn = getDBConnection();

// ── Handle Mark as Paid ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['post_action'] ?? '') === 'mark_paid') {
    $fineId = intval($_POST['fine_id'] ?? 0);
    if ($fineId) {
        mysqli_query($conn, "UPDATE fines SET status='paid', paid_at=NOW() WHERE id=$fineId");
        setFlash('success', 'Fine marked as paid.');
    }
    redirect(BASE_URL . '/admin/fines.php');
}

// ── Fetch Fines ──────────────────────────────────────────────
$filter = $_GET['filter'] ?? 'unpaid';
$where  = $filter === 'all' ? '' : "WHERE f.status='$filter'";

$fines       = [];
$totalUnpaid = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) AS t FROM fines WHERE status='unpaid'"))['t'];

$sql = "SELECT f.*, u.name AS user_name, u.email, b.title AS book_title, ib.due_date, ib.return_date
        FROM fines f
        JOIN users u ON f.user_id = u.id
        JOIN issued_books ib ON f.issued_book_id = ib.id
        JOIN books b ON ib.book_id = b.id
        $where
        ORDER BY f.created_at DESC";
$res = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($res)) $fines[] = $row;

$pageTitle = 'Fines Management';
require_once '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-coins"></i> Fine Management</h1>
        <p class="page-subtitle">Fine rate: Rs <?php echo FINE_PER_DAY; ?>/day. Total unpaid: <strong>Rs <?php echo number_format($totalUnpaid, 2); ?></strong></p>
    </div>
</div>

<!-- Filter Tabs -->
<div class="d-flex gap-2 mb-3">
    <a href="fines.php?filter=unpaid" class="btn btn-sm <?php echo $filter==='unpaid' ? 'btn-primary' : 'btn-outline'; ?>">Unpaid</a>
    <a href="fines.php?filter=paid"   class="btn btn-sm <?php echo $filter==='paid' ? 'btn-primary' : 'btn-outline'; ?>">Paid</a>
    <a href="fines.php?filter=all"    class="btn btn-sm <?php echo $filter==='all' ? 'btn-primary' : 'btn-outline'; ?>">All</a>
</div>

<?php if (empty($fines)): ?>
<div class="empty-state">
    <i class="fas fa-coins"></i>
    <h3>No fines found</h3>
</div>
<?php else: ?>
<div class="table-wrapper">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Student</th>
                    <th>Book</th>
                    <th>Due Date</th>
                    <th>Returned</th>
                    <th>Fine (Rs)</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php $i=1; foreach ($fines as $fine): ?>
                <tr>
                    <td><?php echo $i++; ?></td>
                    <td>
                        <strong><?php echo sanitize($fine['user_name']); ?></strong><br>
                        <small style="color:var(--text-light);"><?php echo sanitize($fine['email']); ?></small>
                    </td>
                    <td><?php echo sanitize($fine['book_title']); ?></td>
                    <td style="font-size:.85rem;"><?php echo date('M d, Y', strtotime($fine['due_date'])); ?></td>
                    <td style="font-size:.85rem;"><?php echo $fine['return_date'] ? date('M d, Y', strtotime($fine['return_date'])) : '—'; ?></td>
                    <td><strong style="color:var(--error);">Rs <?php echo number_format($fine['amount'], 2); ?></strong></td>
                    <td>
                        <span class="badge <?php echo $fine['status']==='paid' ? 'badge-success' : 'badge-danger'; ?>">
                            <?php echo ucfirst($fine['status']); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($fine['status'] === 'unpaid'): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="post_action" value="mark_paid">
                            <input type="hidden" name="fine_id" value="<?php echo $fine['id']; ?>">
                            <button type="submit" class="btn btn-success btn-sm"
                                data-confirm="Mark this fine as paid?">
                                <i class="fas fa-check"></i> Mark Paid
                            </button>
                        </form>
                        <?php else: ?>
                            <span style="font-size:.8rem;color:var(--text-light);">
                                <?php echo $fine['paid_at'] ? date('M d', strtotime($fine['paid_at'])) : '—'; ?>
                            </span>
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