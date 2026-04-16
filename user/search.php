<?php
// ============================================================
// user/search.php — Linear Search (Course-Filtered)
// BSc CSIT student searches only CSIT books
// BCA student searches only BCA books
// ============================================================
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../algorithms/linear_search.php';

requireStudent();

$conn       = getDBConnection();
$query      = sanitize($_GET['q'] ?? '');
$books      = [];
$searched   = false;

// Get student's course from session
$userCourse = $_SESSION['course'] ?? 'BSc CSIT';

// Semester filter (optional)
$filterSem = intval($_GET['sem'] ?? 0);

if ($query !== '') {
    // LINEAR SEARCH:
    // Step 1: Fetch only student's course books (not ALL books)
    $allBooks = getAllBooksForSearch($conn, $userCourse);
    // Step 2: Run linear search
    $books    = linearSearch($query, $allBooks);
    // Step 3: If semester filter applied, filter further
    if ($filterSem > 0) {
        $books = array_filter($books, fn($b) => $b['semester'] == $filterSem);
        $books = array_values($books);
    }
    $searched = true;
} else {
    // Show all books for this course (with optional semester filter)
    $sql = "SELECT * FROM books
            WHERE (course=? OR course='Both')";
    if ($filterSem > 0) $sql .= " AND semester=?";
    $sql .= " ORDER BY semester ASC, title ASC";

    $stmt = mysqli_prepare($conn, $sql);
    if ($filterSem > 0) {
        mysqli_stmt_bind_param($stmt, 'si', $userCourse, $filterSem);
    } else {
        mysqli_stmt_bind_param($stmt, 's', $userCourse);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) $books[] = $row;
}

$pageTitle = 'Search Books';
require_once '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>&#128269; Search Books</h1>
        <p class="page-subtitle">
            Linear Search &mdash;
            <strong><?php echo $userCourse; ?></strong> catalog
            <?php if ($filterSem > 0): ?>
            &mdash; Semester <?php echo $filterSem; ?>
            <?php endif; ?>
        </p>
    </div>
</div>

<!-- Search Bar -->
<div class="search-bar-wrapper">
    <form method="GET" action="search.php" class="search-form">
        <input type="text" name="q" id="searchQuery"
               class="form-control"
               placeholder="Search by title, author, or genre..."
               value="<?php echo $query; ?>" autofocus>
        <?php if ($filterSem > 0): ?>
        <input type="hidden" name="sem" value="<?php echo $filterSem; ?>">
        <?php endif; ?>
        <button type="submit" class="btn btn-primary">&#128269; Search</button>
        <?php if ($query || $filterSem): ?>
        <a href="search.php" class="btn btn-outline">&#10005; Clear</a>
        <?php endif; ?>
    </form>

    <!-- Semester quick-filter buttons -->
    <div style="display:flex;gap:.4rem;margin-top:.85rem;flex-wrap:wrap;align-items:center;">
        <span style="font-size:.75rem;font-weight:600;color:var(--text-light);
                     text-transform:uppercase;letter-spacing:.08em;margin-right:.25rem;">
            Filter by Semester:
        </span>
        <a href="search.php<?php echo $query ? '?q='.urlencode($query) : ''; ?>"
           class="btn btn-sm <?php echo $filterSem===0?'btn-primary':'btn-outline'; ?>">
            All
        </a>
        <?php for ($s=1; $s<=8; $s++): ?>
        <a href="search.php?<?php echo $query?'q='.urlencode($query).'&':''; ?>sem=<?php echo $s; ?>"
           class="btn btn-sm <?php echo $filterSem===$s?'btn-primary':'btn-outline'; ?>">
            Sem <?php echo $s; ?>
        </a>
        <?php endfor; ?>
    </div>

    <?php if ($searched): ?>
    <p style="margin-top:.75rem;font-size:.85rem;color:var(--text-light);">
        &#8505; Linear Search found
        <strong><?php echo count($books); ?></strong> result(s)
        for "<strong><?php echo $query; ?></strong>"
        in <strong><?php echo $userCourse; ?></strong> books
        — O(n) traversal
    </p>
    <?php endif; ?>
</div>

<!-- Results -->
<?php if (empty($books)): ?>
<div class="empty-state">
    <div style="font-size:3rem;">&#128269;</div>
    <h3>No books found</h3>
    <p>
        <?php if ($query): ?>
        No <?php echo $userCourse; ?> books matched "<?php echo $query; ?>".
        <?php else: ?>
        No books found for the selected filters.
        <?php endif; ?>
    </p>
</div>

<?php else: ?>
<div class="books-grid">
    <?php foreach ($books as $book): ?>
    <a href="<?php echo BASE_URL; ?>/user/book_detail.php?id=<?php echo $book['id']; ?>"
       class="book-card" style="text-decoration:none;">

        <?php if ($book['image'] && file_exists('../uploads/'.$book['image'])): ?>
            <img src="<?php echo BASE_URL; ?>/uploads/<?php echo sanitize($book['image']); ?>"
                 class="book-cover" alt="">
        <?php else: ?>
            <div class="book-cover-placeholder">&#128218;</div>
        <?php endif; ?>

        <div class="book-info">
            <!-- Semester badge -->
            <?php if (!empty($book['semester']) && $book['semester'] > 0): ?>
            <span style="font-size:.68rem;font-weight:700;
                         background:var(--mahogany);color:#fff;
                         padding:.12rem .5rem;border-radius:4px;margin-bottom:.3rem;
                         display:inline-block;">
                Sem <?php echo $book['semester']; ?>
            </span>
            <?php endif; ?>

            <?php if (isset($book['match_field'])): ?>
            <span class="badge badge-info"
                  style="font-size:.68rem;margin-bottom:.3rem;display:block;
                         width:fit-content;">
                matched: <?php echo $book['match_field']; ?>
            </span>
            <?php endif; ?>

            <div class="book-title" data-highlight>
                <?php echo sanitize($book['title']); ?>
            </div>
            <div class="book-author" data-highlight>
                <?php echo sanitize($book['author']); ?>
            </div>
            <span class="book-genre"><?php echo sanitize($book['genre']); ?></span>
            <div class="book-availability mt-1
                 <?php echo $book['available_copies']>0?'available':'unavailable'; ?>">
                <?php echo $book['available_copies']>0
                    ? '&#10003; '.$book['available_copies'].' available'
                    : '&#10007; Not available'; ?>
            </div>
        </div>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php
mysqli_close($conn);
require_once '../includes/footer.php';
?>