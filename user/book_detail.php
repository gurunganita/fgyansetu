<?php
// ============================================================
// user/book_detail.php
// Book Detail — Borrow Request (FIFO) / Reserve / Related Books
// Gyansetu — St. Lawrence College Library
// NO WISHLIST
// ============================================================

require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../algorithms/content_based.php';

requireStudent();

$conn   = getDBConnection();
$userId = getCurrentUserId();
$bookId = intval($_GET['id'] ?? 0);

if (!$bookId) {
    setFlash('error', 'Invalid book ID.');
    redirect(BASE_URL . '/user/home.php');
}

// Fetch book
$stmt = mysqli_prepare($conn, "SELECT * FROM books WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $bookId);
mysqli_stmt_execute($stmt);
$book = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$book) {
    setFlash('error', 'Book not found.');
    redirect(BASE_URL . '/user/home.php');
}

// ── Handle POST Actions ──────────────────────────────────────
if (isset($_POST['action'])) {
    $action = $_POST['action'];

    // ── BORROW REQUEST (status = pending, FIFO) ───────────────
    if ($action === 'borrow') {

        // Check if already has active borrow or pending request
        $chk = mysqli_prepare($conn,
            "SELECT id, status FROM issued_books
             WHERE user_id=? AND book_id=?
             AND status IN ('pending','issued','overdue')
             LIMIT 1");
        mysqli_stmt_bind_param($chk, 'ii', $userId, $bookId);
        mysqli_stmt_execute($chk);
        $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($chk));

        if ($existing) {
            if ($existing['status'] === 'pending') {
                setFlash('warning',
                    'You already have a pending request for this book. '.
                    'Please wait for admin approval.');
            } else {
                setFlash('warning', 'You have already borrowed this book.');
            }
        } else {
            // Insert as PENDING — admin must approve
            // FIFO: sorted by issue_date when admin reviews
            $issueDate = date('Y-m-d');
            $dueDate   = date('Y-m-d', strtotime('+' . BORROW_DAYS . ' days'));

            $ins = mysqli_prepare($conn,
                "INSERT INTO issued_books
                 (user_id, book_id, issue_date, due_date, status)
                 VALUES (?,?,?,?,'pending')");
            mysqli_stmt_bind_param($ins, 'iiss',
                $userId, $bookId, $issueDate, $dueDate);

            if (mysqli_stmt_execute($ins)) {
                setFlash('success',
                    'Borrow request submitted successfully! '.
                    'Please wait for admin approval, then visit '.
                    'the library to collect your book.');
            } else {
                setFlash('error', 'Failed to submit request. Please try again.');
            }
        }
    }

    // ── RESERVE ──────────────────────────────────────────────
    elseif ($action === 'reserve') {
        $chkRes = mysqli_prepare($conn,
            "SELECT id FROM reservations
             WHERE user_id=? AND book_id=? AND status='pending'");
        mysqli_stmt_bind_param($chkRes, 'ii', $userId, $bookId);
        mysqli_stmt_execute($chkRes);
        mysqli_stmt_store_result($chkRes);

        if (mysqli_stmt_num_rows($chkRes) > 0) {
            setFlash('warning', 'You have already reserved this book.');
        } else {
            $ins = mysqli_prepare($conn,
                "INSERT INTO reservations (user_id, book_id) VALUES (?,?)");
            mysqli_stmt_bind_param($ins, 'ii', $userId, $bookId);
            if (mysqli_stmt_execute($ins)) {
                setFlash('success',
                    'Book reserved! You will be notified when available.');
            } else {
                setFlash('error', 'Reservation failed. Please try again.');
            }
        }
    }

    redirect(BASE_URL . '/user/book_detail.php?id=' . $bookId);
}

// Re-fetch book after action
mysqli_stmt_execute($stmt);
$book = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

// Related Books — filtered to same course as the book being viewed
$bookCourse     = $book['course'] === 'Both' ? ($_SESSION['course'] ?? 'BSc CSIT') : $book['course'];
$allBooksResult = mysqli_prepare($conn,
    "SELECT * FROM books WHERE course=? OR course='Both'");
mysqli_stmt_bind_param($allBooksResult, 's', $bookCourse);
mysqli_stmt_execute($allBooksResult);
$allBooks = [];
while ($r = mysqli_fetch_assoc(mysqli_stmt_get_result($allBooksResult))) $allBooks[] = $r;
$relatedBooks = getContentBasedRecommendations($bookId, $allBooks, 4);

// ── Status checks (no wishlist) ──────────────────────────────

// Active borrow?
$chkIssued = mysqli_prepare($conn,
    "SELECT id FROM issued_books
     WHERE user_id=? AND book_id=?
     AND status IN ('issued','overdue')");
mysqli_stmt_bind_param($chkIssued, 'ii', $userId, $bookId);
mysqli_stmt_execute($chkIssued);
mysqli_stmt_store_result($chkIssued);
$hasBook = mysqli_stmt_num_rows($chkIssued) > 0;

// Pending request?
$chkPending = mysqli_prepare($conn,
    "SELECT id FROM issued_books
     WHERE user_id=? AND book_id=? AND status='pending'");
mysqli_stmt_bind_param($chkPending, 'ii', $userId, $bookId);
mysqli_stmt_execute($chkPending);
mysqli_stmt_store_result($chkPending);
$hasPending = mysqli_stmt_num_rows($chkPending) > 0;

// Reserved?
$chkRes = mysqli_prepare($conn,
    "SELECT id FROM reservations
     WHERE user_id=? AND book_id=? AND status='pending'");
mysqli_stmt_bind_param($chkRes, 'ii', $userId, $bookId);
mysqli_stmt_execute($chkRes);
mysqli_stmt_store_result($chkRes);
$hasRes = mysqli_stmt_num_rows($chkRes) > 0;

// FIFO queue position
$queuePos = 0;
if ($hasPending) {
    $myReq = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT issue_date FROM issued_books
         WHERE user_id=$userId AND book_id=$bookId
         AND status='pending' LIMIT 1"));
    if ($myReq) {
        $reqTime = $myReq['issue_date'];
        $posRow  = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT COUNT(*) AS pos FROM issued_books
             WHERE book_id=$bookId AND status='pending'
             AND issue_date <= '$reqTime'
             AND user_id != $userId"));
        $queuePos = ($posRow['pos'] ?? 0) + 1;
    }
}

$pageTitle = sanitize($book['title']);
require_once '../includes/header.php';
?>

<a href="javascript:history.back()" class="btn btn-outline btn-sm mb-3">
    &#8592; Back
</a>

<div class="book-detail">

    <!-- Cover -->
    <div class="book-detail-cover">
        <?php if ($book['image'] && file_exists('../uploads/' . $book['image'])): ?>
            <img src="<?php echo BASE_URL; ?>/uploads/<?php echo sanitize($book['image']); ?>"
                 alt="<?php echo sanitize($book['title']); ?>">
        <?php else: ?>
            <div style="width:100%;height:360px;
                        background:linear-gradient(135deg,var(--mahogany),var(--mahogany-mid));
                        display:flex;align-items:center;justify-content:center;
                        color:rgba(255,255,255,.25);font-size:5rem;">
                &#128218;
            </div>
        <?php endif; ?>
    </div>

    <!-- Info -->
    <div class="book-detail-info">
        <h1><?php echo sanitize($book['title']); ?></h1>
        <p style="color:var(--text-light);font-size:1rem;margin-bottom:1rem;">
            by <strong><?php echo sanitize($book['author']); ?></strong>
        </p>

        <div class="book-meta-list">
            <div class="book-meta-item">
                <span class="book-meta-label">Genre</span>
                <?php echo sanitize($book['genre']); ?>
            </div>
            <?php if (!empty($book['isbn'])): ?>
            <div class="book-meta-item">
                <span class="book-meta-label">ISBN</span>
                <?php echo sanitize($book['isbn']); ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($book['publisher'])): ?>
            <div class="book-meta-item">
                <span class="book-meta-label">Publisher</span>
                <?php echo sanitize($book['publisher']); ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($book['published_year'])): ?>
            <div class="book-meta-item">
                <span class="book-meta-label">Year</span>
                <?php echo $book['published_year']; ?>
            </div>
            <?php endif; ?>
            <div class="book-meta-item">
                <span class="book-meta-label">Availability</span>
                <span class="<?php echo $book['available_copies'] > 0 ? 'available' : 'unavailable'; ?>"
                      style="font-weight:600;">
                    <?php echo $book['available_copies']; ?> /
                    <?php echo $book['total_copies']; ?> copies available
                </span>
            </div>
        </div>

        <?php if (!empty($book['description'])): ?>
        <p class="book-description">
            <?php echo nl2br(sanitize($book['description'])); ?>
        </p>
        <?php endif; ?>

        <!-- Action Buttons -->
        <div class="d-flex gap-2 mt-3 flex-wrap" style="align-items:center;">

            <?php if ($hasBook): ?>
                <span class="badge badge-success"
                      style="font-size:.9rem;padding:.55rem 1.2rem;">
                    &#10003; Currently Borrowed
                </span>

            <?php elseif ($hasPending): ?>
                <div class="pending-box">
                    <div style="font-size:1.6rem;">&#9203;</div>
                    <div>
                        <div style="font-weight:700;color:#856404;font-size:.9rem;">
                            Request Pending Approval
                        </div>
                        <div style="color:#a07800;font-size:.8rem;margin-top:.15rem;">
                            You are <strong>#<?php echo $queuePos; ?></strong>
                            in queue &mdash; Admin approves in FIFO order
                        </div>
                    </div>
                </div>

            <?php elseif ($book['available_copies'] > 0): ?>
                <form method="POST">
                    <input type="hidden" name="action" value="borrow">
                    <button type="submit" class="btn btn-primary">
                        &#128218; Request to Borrow
                    </button>
                </form>

            <?php else: ?>
                <?php if ($hasRes): ?>
                    <span class="badge badge-warning"
                          style="font-size:.9rem;padding:.55rem 1.2rem;">
                        &#128278; Reserved
                    </span>
                <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="reserve">
                        <button type="submit" class="btn btn-gold">
                            &#128278; Reserve Book
                        </button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>

        </div>

        <!-- Info strip -->
        <p style="font-size:.78rem;color:var(--text-light);margin-top:.85rem;
                  border-top:1px solid var(--border);padding-top:.75rem;">
            &#128197; Borrow period: <strong><?php echo BORROW_DAYS; ?> days</strong>
            &nbsp;|&nbsp;
            &#128176; Fine: <strong>Rs <?php echo FINE_PER_DAY; ?>/day</strong> after due date
            &nbsp;|&nbsp;
            &#9203; Requests processed in <strong>FIFO order</strong>
        </p>

    </div>
</div>

<!-- Related Books (Content-Based Filtering) -->
<?php if (!empty($relatedBooks)): ?>
<section class="mt-4">
    <div class="section-header">
        <h2 class="section-title">&#128279; Related Books</h2>
        <span style="font-size:.8rem;color:var(--text-light);">
            Based on content similarity (TF-IDF)
        </span>
    </div>
    <div class="rec-strip">
        <?php foreach ($relatedBooks as $rel): ?>
        <a href="<?php echo BASE_URL; ?>/user/book_detail.php?id=<?php echo $rel['id']; ?>"
           class="rec-book-card">
            <?php if (!empty($rel['image']) && file_exists('../uploads/' . $rel['image'])): ?>
                <img src="<?php echo BASE_URL; ?>/uploads/<?php echo sanitize($rel['image']); ?>"
                     class="cover" alt="">
            <?php else: ?>
                <div class="cover"
                     style="display:flex;align-items:center;justify-content:center;">
                    <span style="font-size:1.5rem;color:rgba(255,255,255,.4);">&#128218;</span>
                </div>
            <?php endif; ?>
            <div class="rec-info">
                <div class="rec-title"><?php echo sanitize($rel['title']); ?></div>
                <div class="rec-author"><?php echo sanitize($rel['author']); ?></div>
                <?php if (isset($rel['score'])): ?>
                <div style="font-size:.7rem;color:var(--gold);margin-top:.2rem;">
                    <?php echo round($rel['score'] * 100); ?>% similar
                </div>
                <?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<style>
.pending-box {
    display: flex;
    align-items: center;
    gap: .75rem;
    background: #fff8e6;
    border: 2px solid #f0c040;
    border-radius: var(--radius-sm);
    padding: .7rem 1rem;
}
</style>

<?php
mysqli_close($conn);
require_once '../includes/footer.php';
?>