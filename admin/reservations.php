<?php
// ============================================================
// admin/reservations.php
// Manage Book Reservations
// FIXED:
//   1. Fulfil button checks availability before fulfilling
//   2. Notify reserved user when book becomes available (on return)
//   3. Fulfil creates an issued_books record properly
// Gyansetu — St. Lawrence College Library
// ============================================================

require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/mailer.php';

requireAdmin();

$conn = getDBConnection();

// ── Handle Fulfil / Cancel ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['post_action'] ?? '';
    $resId      = intval($_POST['res_id'] ?? 0);

    if ($resId) {

        // ── FULFIL RESERVATION ───────────────────────────────
        if ($postAction === 'fulfil') {

            // Fetch reservation details
            $res = mysqli_fetch_assoc(mysqli_query($conn,
                "SELECT r.*, u.name AS user_name, u.email,
                        b.title AS book_title, b.available_copies,
                        b.id AS book_id_val
                 FROM reservations r
                 JOIN users u ON r.user_id = u.id
                 JOIN books b ON r.book_id = b.id
                 WHERE r.id = $resId AND r.status = 'pending'"));

            if (!$res) {
                setFlash('error', 'Reservation not found or already processed.');

            } elseif ($res['available_copies'] < 1) {
                // ── BUG FIX 1: Block fulfil if no copies available ──
                setFlash('error',
                    'Cannot fulfil — no copies of "' .
                    sanitize($res['book_title']) .
                    '" are available. Wait for a return first.');

            } else {
                // ── Fulfil: create issued_books record ──────────────
                // Due date from TODAY (fulfil date) + 14 days
                $issueDate = date('Y-m-d');
                $dueDate   = date('Y-m-d',
                    strtotime('+' . BORROW_DAYS . ' days'));

                // Check student doesn't already have active borrow
                $chk = mysqli_prepare($conn,
                    "SELECT id FROM issued_books
                     WHERE user_id=? AND book_id=?
                     AND status IN ('pending','issued','overdue')");
                mysqli_stmt_bind_param($chk, 'ii',
                    $res['user_id'], $res['book_id']);
                mysqli_stmt_execute($chk);
                mysqli_stmt_store_result($chk);

                if (mysqli_stmt_num_rows($chk) > 0) {
                    setFlash('warning',
                        'This student already has an active borrow for this book.');
                } else {
                    // Insert issued record
                    $ins = mysqli_prepare($conn,
                        "INSERT INTO issued_books
                         (user_id, book_id, issue_date, due_date, status)
                         VALUES (?,?,?,?,'issued')");
                    mysqli_stmt_bind_param($ins, 'iiss',
                        $res['user_id'], $res['book_id'],
                        $issueDate, $dueDate);
                    mysqli_stmt_execute($ins);

                    // Decrease available copies
                    mysqli_query($conn,
                        "UPDATE books
                         SET available_copies = available_copies - 1
                         WHERE id = " . $res['book_id']);

                    // Mark reservation as fulfilled
                    mysqli_query($conn,
                        "UPDATE reservations SET status='fulfilled'
                         WHERE id=$resId");

                    // Notify student
                    notifyBorrowApproved($conn,
                        $res['user_id'],
                        $res['user_name'],
                        $res['email'],
                        $res['book_title'],
                        date('M d, Y', strtotime($dueDate))
                    );

                    setFlash('success',
                        'Reservation fulfilled! "' .
                        sanitize($res['book_title']) .
                        '" issued to ' .
                        sanitize($res['user_name']) .
                        '. Due: ' . date('M d, Y', strtotime($dueDate)));
                }
            }

        // ── CANCEL RESERVATION ───────────────────────────────
        } elseif ($postAction === 'cancel') {
            // Get reservation info for notification
            $res = mysqli_fetch_assoc(mysqli_query($conn,
                "SELECT r.*, u.name AS user_name, u.email,
                        b.title AS book_title
                 FROM reservations r
                 JOIN users u ON r.user_id = u.id
                 JOIN books b ON r.book_id = b.id
                 WHERE r.id = $resId"));

            mysqli_query($conn,
                "UPDATE reservations SET status='cancelled'
                 WHERE id=$resId");

            // Notify student of cancellation
            if ($res) {
                notifyBorrowRejected($conn,
                    $res['user_id'],
                    $res['user_name'],
                    $res['email'],
                    $res['book_title'],
                    'Reservation cancelled by library admin'
                );
            }

            setFlash('info', 'Reservation cancelled.');
        }
    }
    redirect(BASE_URL . '/admin/reservations.php');
}

// ── Fetch Reservations ───────────────────────────────────────
$filter = $_GET['filter'] ?? 'pending';
$where  = $filter === 'all' ? '' : "WHERE r.status='$filter'";

$reservations = [];
$sql = "SELECT r.*, u.name AS user_name, u.email,
               b.title AS book_title, b.available_copies
        FROM reservations r
        JOIN users u ON r.user_id = u.id
        JOIN books b ON r.book_id = b.id
        $where
        ORDER BY r.request_time ASC";
$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) $reservations[] = $row;

$pageTitle = 'Reservations';
require_once '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>&#128278; Book Reservations</h1>
        <p class="page-subtitle">Manage student reservation requests</p>
    </div>
</div>

<!-- Filter Tabs -->
<div class="d-flex gap-2 mb-3">
    <?php
    $tabs = ['pending'=>'Pending','fulfilled'=>'Fulfilled',
             'cancelled'=>'Cancelled','all'=>'All'];
    foreach ($tabs as $key => $label):
        $active = $filter===$key ? 'btn-primary' : 'btn-outline';
    ?>
    <a href="reservations.php?filter=<?php echo $key; ?>"
       class="btn btn-sm <?php echo $active; ?>">
        <?php echo $label; ?>
    </a>
    <?php endforeach; ?>
</div>

<?php if (empty($reservations)): ?>
<div class="empty-state">
    <div style="font-size:2.5rem;">&#128278;</div>
    <h3>No reservations found</h3>
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
                    <th>Availability</th>
                    <th>Requested On</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php $i=1; foreach ($reservations as $res): ?>
                <?php
                    $bc = [
                        'pending'   => 'badge-warning',
                        'fulfilled' => 'badge-success',
                        'cancelled' => 'badge-secondary'
                    ][$res['status']] ?? 'badge-secondary';

                    $canFulfil = $res['available_copies'] > 0;
                ?>
                <tr>
                    <td><?php echo $i++; ?></td>
                    <td>
                        <strong><?php echo sanitize($res['user_name']); ?></strong><br>
                        <small style="color:var(--text-light);">
                            <?php echo sanitize($res['email']); ?>
                        </small>
                    </td>
                    <td>
                        <strong><?php echo sanitize($res['book_title']); ?></strong>
                    </td>
                    <td>
                        <span class="<?php echo $canFulfil ? 'available' : 'unavailable'; ?>"
                              style="font-weight:600;">
                            <?php echo $canFulfil
                                ? $res['available_copies'] . ' available'
                                : 'Not available'; ?>
                        </span>
                    </td>
                    <td style="font-size:.85rem;">
                        <?php echo date('M d, Y h:i A',
                            strtotime($res['request_time'])); ?>
                    </td>
                    <td>
                        <span class="badge <?php echo $bc; ?>">
                            <?php echo ucfirst($res['status']); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($res['status'] === 'pending'): ?>
                        <div class="d-flex gap-1" style="flex-wrap:wrap;">

                            <!-- FULFIL — only enabled when copies available -->
                            <form method="POST">
                                <input type="hidden" name="post_action" value="fulfil">
                                <input type="hidden" name="res_id"
                                       value="<?php echo $res['id']; ?>">
                                <button type="submit"
                                    class="btn btn-success btn-sm"
                                    <?php echo !$canFulfil
                                        ? 'disabled title="No copies available — wait for a return"'
                                        : 'title="Issue book to this student"'; ?>>
                                    &#10003; Fulfil
                                </button>
                            </form>

                            <!-- CANCEL -->
                            <form method="POST">
                                <input type="hidden" name="post_action" value="cancel">
                                <input type="hidden" name="res_id"
                                       value="<?php echo $res['id']; ?>">
                                <button type="submit" class="btn btn-danger btn-sm"
                                    data-confirm="Cancel this reservation?">
                                    &#10007; Cancel
                                </button>
                            </form>
                        </div>

                        <?php if (!$canFulfil): ?>
                        <div style="font-size:.72rem;color:var(--error);
                                    margin-top:.3rem;">
                            &#9888; Waiting for return
                        </div>
                        <?php endif; ?>

                        <?php else: ?>
                            <span style="color:var(--text-light);font-size:.85rem;">—</span>
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