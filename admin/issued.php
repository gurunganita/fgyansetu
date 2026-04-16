<?php
// ============================================================
// admin/issued.php
// Issue Books — FIFO Approval System
// Gyansetu Library Management System
//
// FIFO LOGIC:
//   When multiple students request the same book,
//   they are queued by issue_date (request time).
//   Admin approves in order — first requested = first approved.
//   When available_copies runs out, remaining pending
//   requests are automatically cancelled.
// ============================================================

require_once '../config/db.php';
require_once '../includes/mailer.php';
require_once '../includes/auth.php';

requireAdmin();

$conn   = getDBConnection();
$action = $_GET['action'] ?? 'list';

// ============================================================
// HANDLE POST ACTIONS
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['post_action'] ?? '';

    // ── ADMIN ISSUES BOOK DIRECTLY ───────────────────────────
    if ($postAction === 'issue') {
        $userId = intval($_POST['user_id'] ?? 0);
        $bookId = intval($_POST['book_id'] ?? 0);

        if (!$userId || !$bookId) {
            setFlash('error', 'Please select both student and book.');
        } else {
            $bk = mysqli_fetch_assoc(mysqli_query($conn,
                "SELECT available_copies FROM books WHERE id=$bookId"));

            if (!$bk || $bk['available_copies'] < 1) {
                setFlash('error', 'Book not available.');
            } else {
                // Check already issued/pending
                $chk = mysqli_prepare($conn,
                    "SELECT id FROM issued_books
                     WHERE user_id=? AND book_id=?
                     AND status IN ('pending','issued','overdue')");
                mysqli_stmt_bind_param($chk, 'ii', $userId, $bookId);
                mysqli_stmt_execute($chk);
                mysqli_stmt_store_result($chk);

                if (mysqli_stmt_num_rows($chk) > 0) {
                    setFlash('warning',
                        'This student already has an active borrow or pending request.');
                } else {
                    $issueDate = date('Y-m-d');
                    $dueDate   = date('Y-m-d',
                        strtotime('+' . BORROW_DAYS . ' days'));

                    $ins = mysqli_prepare($conn,
                        "INSERT INTO issued_books
                         (user_id,book_id,issue_date,due_date,status)
                         VALUES (?,?,?,?,'issued')");
                    mysqli_stmt_bind_param($ins, 'iiss',
                        $userId, $bookId, $issueDate, $dueDate);
                    mysqli_stmt_execute($ins);

                    mysqli_query($conn,
                        "UPDATE books
                         SET available_copies=available_copies-1
                         WHERE id=$bookId");

                    setFlash('success',
                        'Book issued directly. Due: ' .
                        date('M d, Y', strtotime($dueDate)));
                }
            }
        }
        redirect(BASE_URL . '/admin/issued.php');
    }

    // ── APPROVE PENDING REQUEST (FIFO) ───────────────────────
    elseif ($postAction === 'approve') {
        $issuedId = intval($_POST['issued_id'] ?? 0);

        if ($issuedId) {
            // Get this pending request
            $row = mysqli_fetch_assoc(mysqli_query($conn,
                "SELECT ib.*, b.available_copies, b.title AS book_title
                 FROM issued_books ib
                 JOIN books b ON ib.book_id = b.id
                 WHERE ib.id = $issuedId AND ib.status = 'pending'"));

            if (!$row) {
                setFlash('error', 'Request not found or already processed.');
            } elseif ($row['available_copies'] < 1) {
                // ── No copies left — cancel this and all later requests ──
                setFlash('error',
                    'No copies available. ' .
                    'This request and later pending requests have been cancelled.');

                // Cancel this request
                mysqli_query($conn,
                    "UPDATE issued_books SET status='cancelled'
                     WHERE id=$issuedId");

            } else {
                // ── FIFO CHECK ───────────────────────────────
                // Is there an earlier pending request for same book?
                $bookId   = $row['book_id'];
                $reqTime  = $row['issue_date'];
                $reqId    = $row['id'];

                $earlier = mysqli_fetch_assoc(mysqli_query($conn,
                    "SELECT ib.id, u.name FROM issued_books ib
                     JOIN users u ON ib.user_id = u.id
                     WHERE ib.book_id = $bookId
                     AND ib.status = 'pending'
                     AND ib.issue_date < '$reqTime'
                     AND ib.id != $reqId
                     LIMIT 1"));

                if ($earlier) {
                    // There is an earlier request — must approve that first
                    setFlash('warning',
                        'FIFO order violated! Please approve ' .
                        sanitize($earlier['name']) .
                        "'s earlier request first (Request #" .
                        $earlier['id'] . ").");
                } else {
                    // ── Approve this request ─────────────────
                    // Due date calculated from APPROVAL DATE (today)
                    // NOT from request date — student gets full 14 days
                    // from when they actually collect the book
                    $approvalDate = date('Y-m-d');
                    $newDueDate   = date('Y-m-d',
                        strtotime('+' . BORROW_DAYS . ' days'));

                    mysqli_query($conn,
                        "UPDATE issued_books
                         SET status='issued',
                             issue_date='$approvalDate',
                             due_date='$newDueDate'
                         WHERE id=$issuedId");

                    // Decrease available copies
                    mysqli_query($conn,
                        "UPDATE books
                         SET available_copies = available_copies - 1
                         WHERE id = $bookId");

                    // ── Auto-cancel remaining pending if copies = 0 ──
                    $remaining = mysqli_fetch_assoc(mysqli_query($conn,
                        "SELECT available_copies FROM books
                         WHERE id=$bookId"))['available_copies'];

                    $cancelledCount = 0;
                    if ($remaining < 1) {
                        // Cancel all other pending requests for this book
                        mysqli_query($conn,
                            "UPDATE issued_books SET status='cancelled'
                             WHERE book_id=$bookId
                             AND status='pending'
                             AND id != $issuedId");
                        $cancelledCount = mysqli_affected_rows($conn);
                    }

                    $msg = 'Request approved! Book issued successfully.';
                    if ($cancelledCount > 0) {
                        $msg .= " $cancelledCount other pending request(s) " .
                                "auto-cancelled (no copies left).";
                    }
                    // Fetch user info for notification
                    $uInfo = mysqli_fetch_assoc(mysqli_query($conn,
                        "SELECT u.name, u.email, b.title, ib.due_date
                         FROM issued_books ib
                         JOIN users u ON ib.user_id=u.id
                         JOIN books b ON ib.book_id=b.id
                         WHERE ib.id=$issuedId"));
                    if ($uInfo) {
                        notifyBorrowApproved($conn,
                            $row['user_id'],
                            $uInfo['name'],
                            $uInfo['email'],
                            $uInfo['title'],
                            date('M d, Y', strtotime($uInfo['due_date']))
                        );
                    }
                    setFlash('success', $msg);
                }
            }
        }
        redirect(BASE_URL . '/admin/issued.php?filter=pending');
    }

    // ── REJECT A PENDING REQUEST ─────────────────────────────
    elseif ($postAction === 'reject') {
        $issuedId = intval($_POST['issued_id'] ?? 0);
        if ($issuedId) {
            mysqli_query($conn,
                "UPDATE issued_books SET status='cancelled'
                 WHERE id=$issuedId AND status='pending'");
            setFlash('info', 'Borrow request rejected.');
        }
        redirect(BASE_URL . '/admin/issued.php?filter=pending');
    }

    // ── RETURN BOOK ──────────────────────────────────────────
    elseif ($postAction === 'return') {
        $issuedId = intval($_POST['issued_id'] ?? 0);
        if ($issuedId) {
            $row = mysqli_fetch_assoc(mysqli_query($conn,
                "SELECT * FROM issued_books WHERE id=$issuedId"));
            if ($row) {
                $returnDate = date('Y-m-d');
                $bookId     = $row['book_id'];

                // Mark as returned
                mysqli_query($conn,
                    "UPDATE issued_books
                     SET status='returned', return_date='$returnDate'
                     WHERE id=$issuedId");

                // Increase available copies
                mysqli_query($conn,
                    "UPDATE books
                     SET available_copies=available_copies+1
                     WHERE id=$bookId");

                // Calculate fine if overdue
                $fine = calculateFine($row['due_date']);
                if ($fine > 0) {
                    $ins = mysqli_prepare($conn,
                        "INSERT INTO fines (user_id,issued_book_id,amount)
                         VALUES (?,?,?)
                         ON DUPLICATE KEY UPDATE amount=?");
                    mysqli_stmt_bind_param($ins, 'iidd',
                        $row['user_id'], $issuedId, $fine, $fine);
                    mysqli_stmt_execute($ins);

                    // Notify student of fine
                    $fineUser = mysqli_fetch_assoc(mysqli_query($conn,
                        "SELECT u.name, u.email, b.title
                         FROM users u
                         JOIN books b ON b.id=$bookId
                         WHERE u.id=" . $row['user_id']));
                    if ($fineUser) {
                        notifyFineCharged($conn,
                            $row['user_id'],
                            $fineUser['name'],
                            $fineUser['email'],
                            $fineUser['title'],
                            $fine
                        );
                    }
                }

                // ── BUG FIX 2: Notify reserved users when book returned ──
                // Find the OLDEST pending reservation for this book (FIFO)
                $nextReservation = mysqli_fetch_assoc(mysqli_query($conn,
                    "SELECT r.*, u.name AS user_name, u.email,
                            b.title AS book_title
                     FROM reservations r
                     JOIN users u ON r.user_id = u.id
                     JOIN books b ON r.book_id = b.id
                     WHERE r.book_id = $bookId
                     AND r.status = 'pending'
                     ORDER BY r.request_time ASC
                     LIMIT 1"));

                if ($nextReservation) {
                    // Notify the first reserved student
                    notifyReservationAvailable($conn,
                        $nextReservation['user_id'],
                        $nextReservation['user_name'],
                        $nextReservation['email'],
                        $nextReservation['book_title']
                    );

                    $msg = 'Book returned.' .
                        ($fine > 0 ? " Fine Rs $fine recorded." : '') .
                        ' Student ' . sanitize($nextReservation['user_name']) .
                        ' has been notified — they have a reservation for this book.';
                } else {
                    $msg = 'Book returned.' .
                        ($fine > 0 ? " Fine Rs $fine recorded." : '');
                }

                setFlash('success', $msg);
            }
        }
        redirect(BASE_URL . '/admin/issued.php');
    }
}

// ============================================================
// FETCH DATA
// ============================================================
$filter = $_GET['filter'] ?? 'pending';

// Count pending
$pendingCount = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS cnt FROM issued_books
     WHERE status='pending'"))['cnt'];

// Build query based on filter
$whereMap = [
    'pending'  => "WHERE ib.status = 'pending'",
    'active'   => "WHERE ib.status IN ('issued','overdue')",
    'returned' => "WHERE ib.status = 'returned'",
    'cancelled'=> "WHERE ib.status = 'cancelled'",
    'all'      => ''
];
$where = $whereMap[$filter] ?? '';

// FIFO: order pending by issue_date ASC (oldest first)
$orderBy = ($filter === 'pending')
    ? "ORDER BY ib.issue_date ASC, ib.id ASC"
    : "ORDER BY ib.issue_date DESC";

$issued = [];
$sql = "SELECT ib.*,
               u.name AS user_name, u.email,
               b.title AS book_title, b.author,
               b.available_copies
        FROM issued_books ib
        JOIN users u ON ib.user_id = u.id
        JOIN books b ON ib.book_id = b.id
        $where
        $orderBy";
$res = mysqli_query($conn, $sql);
while ($r = mysqli_fetch_assoc($res)) $issued[] = $r;

// For issue modal
$students = [];
$sRes = mysqli_query($conn,
    "SELECT id, name, email FROM users
     WHERE role='student' ORDER BY name");
while ($r = mysqli_fetch_assoc($sRes)) $students[] = $r;

$availBooks = [];
$bRes = mysqli_query($conn,
    "SELECT id, title, author, available_copies
     FROM books WHERE available_copies>0 ORDER BY title");
while ($r = mysqli_fetch_assoc($bRes)) $availBooks[] = $r;

$pageTitle = 'Issued Books';
require_once '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>&#128218; Issued Books</h1>
        <p class="page-subtitle">FIFO-based borrow approval system</p>
    </div>
    <button class="btn btn-primary" data-modal-target="#issueModal">
        + Issue Book Directly
    </button>
</div>

<!-- Pending alert -->
<?php if ($pendingCount > 0 && $filter !== 'pending'): ?>
<div class="alert alert-warning">
    <span class="alert-icon">&#9203;</span>
    <strong><?php echo $pendingCount; ?> pending</strong>
    borrow request(s) waiting for approval.
    <a href="issued.php?filter=pending" style="font-weight:700;margin-left:.5rem;">
        Review Now &#8594;
    </a>
</div>
<?php endif; ?>

<!-- Filter Tabs -->
<div class="d-flex gap-2 mb-3 flex-wrap">
    <?php
    $tabs = [
        'pending'   => '&#9203; Pending (' . $pendingCount . ')',
        'active'    => 'Active',
        'returned'  => 'Returned',
        'cancelled' => 'Cancelled',
        'all'       => 'All'
    ];
    foreach ($tabs as $key => $label):
        $active = $filter === $key ? 'btn-primary' : 'btn-outline';
    ?>
    <a href="issued.php?filter=<?php echo $key; ?>"
       class="btn btn-sm <?php echo $active; ?>">
        <?php echo $label; ?>
    </a>
    <?php endforeach; ?>
</div>

<!-- FIFO explanation for pending tab -->
<?php if ($filter === 'pending' && !empty($issued)): ?>
<div style="background:#f0f7ff;border:1px solid #b8d4f0;border-radius:8px;
            padding:.85rem 1.1rem;margin-bottom:1.25rem;font-size:.88rem;color:#1a4a7a;">
    &#128203; <strong>FIFO Order:</strong>
    Requests are sorted by submission time — oldest request is at the top.
    Approve from top to bottom to maintain fairness.
    When copies run out, remaining requests are auto-cancelled.
</div>
<?php endif; ?>

<?php if (empty($issued)): ?>
<div class="empty-state">
    <div style="font-size:2.5rem;">&#128218;</div>
    <h3>No records found</h3>
</div>

<?php else: ?>
<div class="table-wrapper">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Queue #</th>
                    <th>Student</th>
                    <th>Book</th>
                    <th>Requested</th>
                    <th>Due Date</th>
                    <th>Avail.</th>
                    <th>Fine</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; foreach ($issued as $row): ?>
                <?php
                    $status = $row['status'];
                    if ($status === 'issued' &&
                        strtotime($row['due_date']) < time()) {
                        $status = 'overdue';
                    }
                    $fine = 0;
                    if ($status === 'overdue') {
                        $fine = calculateFine($row['due_date']);
                    }
                    $badgeMap = [
                        'pending'   => 'badge-warning',
                        'issued'    => 'badge-info',
                        'overdue'   => 'badge-danger',
                        'returned'  => 'badge-success',
                        'cancelled' => 'badge-secondary',
                    ];
                    $bc = $badgeMap[$status] ?? 'badge-secondary';
                ?>
                <tr <?php echo $status === 'pending' ? 'style="background:#fffdf0;"' : ''; ?>>
                    <td>
                        <?php if ($status === 'pending'): ?>
                            <span style="background:#4A1C1C;color:#fff;
                                         border-radius:50%;width:26px;height:26px;
                                         display:inline-flex;align-items:center;
                                         justify-content:center;font-size:.8rem;
                                         font-weight:700;">
                                <?php echo $i; ?>
                            </span>
                        <?php else: ?>
                            <?php echo $i; ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong><?php echo sanitize($row['user_name']); ?></strong><br>
                        <small style="color:var(--text-light);">
                            <?php echo sanitize($row['email']); ?>
                        </small>
                    </td>
                    <td>
                        <strong><?php echo sanitize($row['book_title']); ?></strong><br>
                        <small style="color:var(--text-light);">
                            <?php echo sanitize($row['author']); ?>
                        </small>
                    </td>
                    <td style="font-size:.85rem;">
                        <?php echo date('M d, Y', strtotime($row['issue_date'])); ?>
                        <?php if ($status === 'pending'): ?>
                        <br>
                        <small style="color:var(--gold);font-weight:600;">
                            &#9203; Waiting
                        </small>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:.85rem;
                        <?php echo $status==='overdue' ? 'color:var(--error);font-weight:600;' : ''; ?>">
                        <?php echo date('M d, Y', strtotime($row['due_date'])); ?>
                    </td>
                    <td>
                        <span class="<?php echo $row['available_copies'] > 0 ? 'available' : 'unavailable'; ?>"
                              style="font-weight:600;">
                            <?php echo $row['available_copies']; ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($fine > 0): ?>
                            <span style="color:var(--error);font-weight:600;">
                                Rs <?php echo $fine; ?>
                            </span>
                        <?php else: ?>
                            <span style="color:var(--text-light);">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge <?php echo $bc; ?>">
                            <?php echo ucfirst($status); ?>
                        </span>
                    </td>
                    <td>
                        <div class="d-flex gap-1" style="flex-wrap:wrap;">
                        <?php if ($status === 'pending'): ?>
                            <!-- APPROVE -->
                            <form method="POST">
                                <input type="hidden" name="post_action" value="approve">
                                <input type="hidden" name="issued_id"
                                       value="<?php echo $row['id']; ?>">
                                <button type="submit"
                                    class="btn btn-success btn-sm"
                                    <?php echo $row['available_copies'] < 1 ? 'disabled title="No copies available"' : ''; ?>>
                                    &#10003; Approve
                                </button>
                            </form>
                            <!-- REJECT -->
                            <form method="POST">
                                <input type="hidden" name="post_action" value="reject">
                                <input type="hidden" name="issued_id"
                                       value="<?php echo $row['id']; ?>">
                                <button type="submit" class="btn btn-danger btn-sm"
                                    data-confirm="Reject this borrow request?">
                                    &#10007; Reject
                                </button>
                            </form>

                        <?php elseif ($status === 'issued' || $status === 'overdue'): ?>
                            <!-- RETURN -->
                            <form method="POST">
                                <input type="hidden" name="post_action" value="return">
                                <input type="hidden" name="issued_id"
                                       value="<?php echo $row['id']; ?>">
                                <button type="submit"
                                    class="btn btn-outline btn-sm"
                                    data-confirm="Mark this book as returned?">
                                    &#8617; Return
                                </button>
                            </form>

                        <?php else: ?>
                            <span style="color:var(--text-light);font-size:.82rem;">—</span>
                        <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php $i++; endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Issue Book Modal -->
<div class="modal-overlay" id="issueModal">
    <div class="modal">
        <div class="modal-header">
            <h3>&#128218; Issue Book Directly</h3>
            <button class="modal-close">&times;</button>
        </div>
        <form method="POST" action="issued.php" data-validate>
            <input type="hidden" name="post_action" value="issue">
            <div class="modal-body">
                <p style="font-size:.85rem;color:var(--text-light);margin-bottom:1rem;">
                    This directly issues a book without pending approval
                    — use when student is physically present at the library.
                </p>
                <div class="form-group">
                    <label class="form-label">Select Student *</label>
                    <select name="user_id" class="form-control" required>
                        <option value="">— Choose Student —</option>
                        <?php foreach ($students as $s): ?>
                        <option value="<?php echo $s['id']; ?>">
                            <?php echo sanitize($s['name']); ?>
                            (<?php echo sanitize($s['email']); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Select Book *</label>
                    <select name="book_id" class="form-control" required>
                        <option value="">— Choose Book —</option>
                        <?php foreach ($availBooks as $b): ?>
                        <option value="<?php echo $b['id']; ?>">
                            <?php echo sanitize($b['title']); ?>
                            — <?php echo sanitize($b['author']); ?>
                            (<?php echo $b['available_copies']; ?> avail.)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <p style="font-size:.82rem;color:var(--text-light);">
                    &#8505; Borrow period: <?php echo BORROW_DAYS; ?> days.
                    Fine: Rs <?php echo FINE_PER_DAY; ?>/day after due date.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline modal-close">
                    Cancel
                </button>
                <button type="submit" class="btn btn-primary">
                    &#10003; Issue Now
                </button>
            </div>
        </form>
    </div>
</div>

<?php
mysqli_close($conn);
require_once '../includes/footer.php';
?>