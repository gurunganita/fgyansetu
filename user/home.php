<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../algorithms/content_based.php';
require_once '../algorithms/collaborative.php';
requireStudent();

$conn     = getDBConnection();
$userId   = getCurrentUserId();
$userName = getCurrentUserName();

// ── Get student course + semester ─────────────────────────────
$uStmt = mysqli_prepare($conn,
    "SELECT course, semester FROM users WHERE id=?");
mysqli_stmt_bind_param($uStmt, 'i', $userId);
mysqli_stmt_execute($uStmt);
$uRow = mysqli_fetch_assoc(mysqli_stmt_get_result($uStmt));
$_SESSION['course']   = $uRow['course']   ?? 'BSc CSIT';
$_SESSION['semester'] = $uRow['semester'] ?? 1;

$userCourse   = $_SESSION['course'];
$userSemester = intval($_SESSION['semester']);

// Active semester (URL param or student's semester)
$activeSem = isset($_GET['sem']) ? intval($_GET['sem']) : $userSemester;
if ($activeSem < 1 || $activeSem > 8) $activeSem = $userSemester;

// ── FIX 1: Single query for semester counts (replaces 8 queries)
// Get all semester counts for this course in ONE query
$semCounts = array_fill(1, 8, 0);
$scResult  = mysqli_prepare($conn,
    "SELECT semester, COUNT(*) AS c FROM books
     WHERE (course=? OR course='Both')
     AND semester BETWEEN 1 AND 8
     GROUP BY semester");
mysqli_stmt_bind_param($scResult, 's', $userCourse);
mysqli_stmt_execute($scResult);
$scRows = mysqli_stmt_get_result($scResult);
while ($scRow = mysqli_fetch_assoc($scRows)) {
    $semCounts[intval($scRow['semester'])] = intval($scRow['c']);
}
$totalCourseBooks = array_sum($semCounts);

// ── Books for active semester ─────────────────────────────────
$semBooks = [];
$sBooks   = mysqli_prepare($conn,
    "SELECT * FROM books
     WHERE (course=? OR course='Both')
     AND semester=?
     ORDER BY title ASC");
mysqli_stmt_bind_param($sBooks, 'si', $userCourse, $activeSem);
mysqli_stmt_execute($sBooks);
$rBooks = mysqli_stmt_get_result($sBooks);
while ($r = mysqli_fetch_assoc($rBooks)) $semBooks[] = $r;

// ── FIX 2: Course-filtered books for recommendation engine ────
// BSc CSIT student gets ONLY BSc CSIT + Both books recommended
// BCA student gets ONLY BCA + Both books recommended
$courseBooks = [];
$cBooks = mysqli_prepare($conn,
    "SELECT * FROM books
     WHERE course=? OR course='Both'
     ORDER BY title ASC");
mysqli_stmt_bind_param($cBooks, 's', $userCourse);
mysqli_stmt_execute($cBooks);
$rCBooks = mysqli_stmt_get_result($cBooks);
while ($r = mysqli_fetch_assoc($rCBooks)) $courseBooks[] = $r;

// ── Hybrid Recommendations (course-filtered) ──────────────────
// CF: finds books borrowed by similar users
//     → then filters to only show student's course books
// CB: TF-IDF similarity within course books only
$cfRecs = getCollaborativeRecommendations($userId, $conn, 8, $userCourse);
$cbRecs = getPersonalizedContentRecs($userId, $courseBooks, $conn, 8);

// Filter CF results to student's course only
$courseBookIds = array_column($courseBooks, 'id');
$cfRecs = array_filter($cfRecs, function($b) use ($courseBookIds) {
    return in_array($b['id'], $courseBookIds);
});

// Merge into hybrid
$hybridIds = []; $hybridRecs = [];
foreach (array_merge(array_values($cfRecs), $cbRecs) as $b) {
    if (!in_array($b['id'], $hybridIds) && count($hybridRecs) < 6) {
        $hybridIds[]  = $b['id'];
        $hybridRecs[] = $b;
    }
}

// ── Stats ─────────────────────────────────────────────────────
$sA = mysqli_prepare($conn,
    "SELECT COUNT(*) AS c FROM issued_books
     WHERE user_id=? AND status IN ('issued','overdue')");
mysqli_stmt_bind_param($sA, 'i', $userId);
mysqli_stmt_execute($sA);
$activeBorrows = mysqli_fetch_assoc(mysqli_stmt_get_result($sA))['c'];

$sP = mysqli_prepare($conn,
    "SELECT COUNT(*) AS c FROM issued_books
     WHERE user_id=? AND status='pending'");
mysqli_stmt_bind_param($sP, 'i', $userId);
mysqli_stmt_execute($sP);
$pendingBorrows = mysqli_fetch_assoc(mysqli_stmt_get_result($sP))['c'];

$sF = mysqli_prepare($conn,
    "SELECT COALESCE(SUM(amount),0) AS t FROM fines
     WHERE user_id=? AND status='unpaid'");
mysqli_stmt_bind_param($sF, 'i', $userId);
mysqli_stmt_execute($sF);
$totalFines = mysqli_fetch_assoc(mysqli_stmt_get_result($sF))['t'];

// ── Semester subject names ─────────────────────────────────────
$semSubjects = [
    'BSc CSIT' => [
        1 => 'Intro IT · Digital Logic · C Programming · Maths I · Physics',
        2 => 'C++ OOP · Numerical Methods · Statistics · Discrete Maths',
        3 => 'Data Structures · Algorithms · DBMS · OOP Java · OS',
        4 => 'Computer Graphics · Networks · Software Eng · Microprocessor · AI',
        5 => 'DotNet Technology · Web Tech · Theory of Computation · Compiler',
        6 => 'Advanced Java · Distributed Systems · Mobile Computing',
        7 => 'Cloud Computing · Cyber Security · Machine Learning',
        8 => 'Project Work · Research Methodology · Seminar',
    ],
    'BCA' => [
        1 => 'Computer Fundamentals · Society & Tech · English I · Maths I · Digital Logic',
        2 => 'C Programming · Financial Accounting · English II · Maths II · Microprocessor',
        3 => 'Data Structures · Probability & Stats · System Analysis · OOP Java · Web Tech',
        4 => 'OS · Numerical Methods · Software Eng · Scripting · DBMS · Project I',
        5 => 'MIS & E-Business · DotNet · Networking · Management · Computer Graphics',
        6 => 'Mobile Programming · Distributed System · Applied Economics · Advanced Java',
        7 => 'Cyber Law & Ethics · Cloud Computing · Internship',
        8 => 'Project Work · Research Methods · Seminar',
    ],
];

$pageTitle = 'Home';
require_once '../includes/header.php';
?>

<style>
/* ── Hero ── */
.student-hero {
    background: linear-gradient(135deg, #2C0F0F 0%, var(--mahogany) 50%, #6B2D2D 100%);
    border-radius: var(--radius-lg);
    padding: 1.75rem 2.25rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
    position: relative;
    overflow: hidden;
}
.student-hero-DISABLED {
    content: '📚';
    position: absolute;
    right: 1rem; top: 50%;
    transform: translateY(-50%);
    font-size: 8rem;
    opacity: .05;
    pointer-events: none;
}
.hero-left h1 {
    font-family: var(--font-heading);
    font-size: 1.6rem; color: #fff; margin-bottom: .35rem;
}
.hero-left p { color: rgba(255,255,255,.55); font-size: .85rem; }
.hero-badges {
    display: flex; gap: .5rem; flex-wrap: wrap; margin-top: .5rem;
}
.hbadge {
    display: inline-flex; align-items: center; gap: .3rem;
    background: rgba(255,255,255,.12); color: #fff;
    padding: .28rem .8rem; border-radius: 20px;
    font-size: .78rem; font-weight: 600;
    border: 1px solid rgba(255,255,255,.18);
}
.hbadge.gold {
    background: rgba(201,151,60,.25);
    border-color: rgba(201,151,60,.4);
    color: #E8B96B;
}
.hero-actions { display: flex; gap: .6rem; flex-wrap: wrap; }

/* ── Stats row ── */
.mini-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    gap: .85rem;
    margin-bottom: 1.5rem;
}
.mstat {
    background: #fff;
    border-radius: var(--radius-md);
    padding: 1rem 1.1rem;
    border: 1px solid var(--border);
    box-shadow: var(--shadow-sm);
    display: flex; align-items: center; gap: .7rem;
}
.mstat-ic {
    width: 38px; height: 38px; border-radius: 9px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem; flex-shrink: 0;
}
.mic-red   { background: rgba(74,28,28,.1);   color: var(--mahogany); }
.mic-gold  { background: rgba(201,151,60,.15); color: var(--gold); }
.mic-blue  { background: rgba(41,128,185,.1); color: var(--info); }
.mstat-val { font-family: var(--font-heading); font-size: 1.5rem; color: var(--mahogany); line-height:1; }
.mstat-lbl { font-size: .72rem; color: var(--text-light); margin-top: .1rem; }

/* ── Recommendations ── */
.rec-section {
    background: #fff;
    border-radius: var(--radius-md);
    border: 1px solid var(--border);
    padding: 1.25rem 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: var(--shadow-sm);
}
.rec-header {
    display: flex; align-items: center;
    justify-content: space-between; margin-bottom: 1rem;
}
.rec-title {
    font-family: var(--font-heading);
    font-size: 1.1rem; color: var(--mahogany);
    display: flex; align-items: center; gap: .4rem;
}
.rec-sub { font-size: .75rem; color: var(--text-light); }

/* ── Semester Navigation ── */
.sem-panel {
    background: #fff;
    border-radius: var(--radius-md);
    border: 1px solid var(--border);
    padding: 1.25rem 1.5rem 1rem;
    margin-bottom: 1.5rem;
    box-shadow: var(--shadow-sm);
}
.sem-panel-header {
    display: flex; align-items: center;
    justify-content: space-between;
    margin-bottom: 1rem;
}
.sem-panel-title {
    font-size: .75rem; font-weight: 700;
    color: var(--text-mid); text-transform: uppercase;
    letter-spacing: .1em;
    display: flex; align-items: center; gap: .5rem;
}
.sem-grid {
    display: grid;
    grid-template-columns: repeat(8, 1fr);
    gap: .5rem;
}
.sbtn {
    position: relative;
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    gap: .15rem;
    padding: .65rem .4rem;
    border: 2px solid var(--border);
    border-radius: 10px;
    background: var(--ivory);
    text-decoration: none;
    transition: all .2s;
}
.sbtn:hover {
    border-color: var(--mahogany-lt);
    background: #fff;
    transform: translateY(-2px);
    box-shadow: var(--shadow-sm);
}
.sbtn.is-active {
    background: var(--mahogany);
    border-color: var(--mahogany);
    box-shadow: 0 4px 14px rgba(74,28,28,.3);
    transform: translateY(-2px);
}
.sbtn.is-mine { border-color: var(--gold); }
.sbtn.is-mine:not(.is-active) { background: rgba(201,151,60,.06); }
.snum {
    font-size: 1.05rem; font-weight: 800;
    font-family: var(--font-heading);
    color: var(--mahogany); line-height: 1;
}
.sbtn.is-active .snum { color: #fff; }
.slbl {
    font-size: .58rem; color: var(--text-light);
    font-weight: 600; text-transform: uppercase;
}
.sbtn.is-active .slbl { color: rgba(255,255,255,.65); }
.scnt {
    position: absolute; top: -7px; right: -7px;
    background: var(--gold); color: #fff;
    font-size: .58rem; font-weight: 700;
    width: 17px; height: 17px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    border: 2px solid #fff;
}
.sbtn.is-active .scnt { background: #fff; color: var(--mahogany); }
.star-tag {
    position: absolute; top: -10px; left: 50%;
    transform: translateX(-50%);
    background: var(--gold); color: #fff;
    font-size: .55rem; font-weight: 700;
    padding: .08rem .35rem; border-radius: 4px;
    white-space: nowrap;
}
.sem-subjects-strip {
    margin-top: .85rem;
    padding: .6rem 1rem;
    background: var(--ivory-dark);
    border-radius: 7px;
    font-size: .8rem; color: var(--text-mid);
    border-left: 3px solid var(--mahogany);
    line-height: 1.5;
}
.sem-subjects-strip strong { color: var(--mahogany); }

/* ── Books Section ── */
.books-section-hdr {
    display: flex; align-items: center;
    justify-content: space-between;
    margin-bottom: 1.1rem; flex-wrap: wrap; gap: .75rem;
}
.bsec-title {
    font-family: var(--font-heading);
    font-size: 1.25rem; color: var(--mahogany);
    display: flex; align-items: center; gap: .5rem;
}
.you-badge {
    background: var(--gold); color: #fff;
    font-size: .68rem; padding: .18rem .55rem;
    border-radius: 8px; font-weight: 700;
}
.course-pill {
    font-size: .68rem; font-weight: 700;
    padding: .15rem .5rem; border-radius: 5px;
    text-transform: uppercase; letter-spacing: .04em;
}
.cp-csit { background: #fde8e8; color: #721c24; }
.cp-bca  { background: #d1ecf1; color: #0c5460; }
.cp-both { background: #fff3cd; color: #856404; }

@media(max-width:600px) {
    .sem-grid { grid-template-columns: repeat(4,1fr); }
    .student-hero { padding: 1.25rem; }
    .mini-stats { grid-template-columns: repeat(2,1fr); }
}
</style>

<!-- Hero -->
<div class="student-hero">
    <div class="hero-left">
        <h1>Welcome, <?php echo sanitize($userName); ?> &#128218;</h1>
        <p><?php echo COLLEGE_NAME; ?> — Library System</p>
        <div class="hero-badges">
            <span class="hbadge">&#127979; <?php echo $userCourse; ?></span>
            <span class="hbadge gold">&#9733; Semester <?php echo $userSemester; ?></span>
            <span class="hbadge">&#128218; <?php echo $totalCourseBooks; ?> books</span>
        </div>
    </div>
    <div class="hero-actions">
        <a href="<?php echo BASE_URL; ?>/user/search.php"
           class="btn btn-gold btn-sm">&#128269; Search</a>
        <a href="<?php echo BASE_URL; ?>/user/borrowed.php"
           class="btn btn-sm"
           style="background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.3);">
            &#128196; My Books
        </a>
    </div>
</div>

<!-- Stats -->
<div class="mini-stats">
    <div class="mstat">
        <div class="mstat-ic mic-red">&#128218;</div>
        <div>
            <div class="mstat-val"><?php echo $activeBorrows; ?></div>
            <div class="mstat-lbl">Active Borrows</div>
        </div>
    </div>
    <?php if ($pendingBorrows > 0): ?>
    <div class="mstat">
        <div class="mstat-ic mic-gold">&#9203;</div>
        <div>
            <div class="mstat-val"><?php echo $pendingBorrows; ?></div>
            <div class="mstat-lbl">Pending Approval</div>
        </div>
    </div>
    <?php endif; ?>
    <div class="mstat">
        <div class="mstat-ic mic-gold">&#128176;</div>
        <div>
            <div class="mstat-val">Rs <?php echo number_format($totalFines,0); ?></div>
            <div class="mstat-lbl">Pending Fines</div>
        </div>
    </div>
    <div class="mstat">
        <div class="mstat-ic mic-blue">&#128218;</div>
        <div>
            <div class="mstat-val"><?php echo $totalCourseBooks; ?></div>
            <div class="mstat-lbl"><?php echo $userCourse; ?> Books</div>
        </div>
    </div>
</div>

<!-- Recommendations (course-filtered) -->
<?php if (!empty($hybridRecs)): ?>
<div class="rec-section">
    <div class="rec-header">
        <div class="rec-title">&#11088; Recommended For You</div>
        <span class="rec-sub">
            <?php echo $userCourse; ?> books only &bull; Hybrid AI
        </span>
    </div>
    <div class="rec-strip">
        <?php foreach ($hybridRecs as $book): ?>
        <a href="<?php echo BASE_URL; ?>/user/book_detail.php?id=<?php echo $book['id']; ?>"
           class="rec-book-card">
            <?php if (!empty($book['image']) && file_exists('../uploads/'.$book['image'])): ?>
                <img src="<?php echo BASE_URL; ?>/uploads/<?php echo sanitize($book['image']); ?>"
                     class="cover" alt="">
            <?php else: ?>
                <div class="cover" style="display:flex;align-items:center;justify-content:center;">
                    <span style="font-size:2rem;color:rgba(255,255,255,.3);">&#128218;</span>
                </div>
            <?php endif; ?>
            <div class="rec-info">
                <div class="rec-title"><?php echo sanitize($book['title']); ?></div>
                <div class="rec-author"><?php echo sanitize($book['author']); ?></div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Semester Navigation Panel -->
<div class="sem-panel">
    <div class="sem-panel-header">
        <div class="sem-panel-title">
            &#127979; <?php echo $userCourse; ?> &mdash; Select Semester
        </div>
        <span style="font-size:.75rem;color:var(--gold);font-weight:600;">
            &#9733; = Your current semester
        </span>
    </div>

    <div class="sem-grid">
        <?php for ($s = 1; $s <= 8; $s++):
            $isActive = ($s === $activeSem);
            $isMine   = ($s === $userSemester);
            $count    = $semCounts[$s] ?? 0;
            $cls = 'sbtn';
            if ($isActive) $cls .= ' is-active';
            if ($isMine)   $cls .= ' is-mine';
        ?>
        <a href="?sem=<?php echo $s; ?>" class="<?php echo $cls; ?>"
           title="Semester <?php echo $s; ?> — <?php echo $count; ?> books">
            <?php if ($isMine): ?>
                <span class="star-tag">&#9733;</span>
            <?php endif; ?>
            <?php if ($count > 0): ?>
                <span class="scnt"><?php echo $count; ?></span>
            <?php endif; ?>
            <span class="snum"><?php echo $s; ?></span>
            <span class="slbl">Sem</span>
        </a>
        <?php endfor; ?>
    </div>

    <!-- Subject hint -->
    <?php $subj = $semSubjects[$userCourse][$activeSem] ?? ''; ?>
    <?php if ($subj): ?>
    <div class="sem-subjects-strip">
        <strong>Sem <?php echo $activeSem; ?> Subjects:</strong>
        <?php echo $subj; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Books Grid -->
<div class="books-section-hdr">
    <div class="bsec-title">
        <?php echo $userCourse==='BCA' ? '&#128200;' : '&#128187;'; ?>
        Semester <?php echo $activeSem; ?> Books
        <?php if ($activeSem === $userSemester): ?>
        <span class="you-badge">&#9733; Your Semester</span>
        <?php endif; ?>
    </div>
    <div style="display:flex;align-items:center;gap:.75rem;">
        <span style="font-size:.83rem;color:var(--text-light);">
            <?php echo count($semBooks); ?> book(s)
        </span>
        <a href="<?php echo BASE_URL; ?>/user/search.php"
           class="btn btn-outline btn-sm">View All</a>
    </div>
</div>

<?php if (empty($semBooks)): ?>
<div class="empty-state">
    <div style="font-size:3rem;margin-bottom:1rem;">&#128218;</div>
    <h3>No books for Semester <?php echo $activeSem; ?></h3>
    <p>No <?php echo $userCourse; ?> books found for this semester yet.</p>
    <a href="<?php echo BASE_URL; ?>/user/search.php"
       class="btn btn-primary mt-2">&#128269; Browse All Books</a>
</div>
<?php else: ?>
<div class="books-grid">
    <?php foreach ($semBooks as $book): ?>
    <a href="<?php echo BASE_URL; ?>/user/book_detail.php?id=<?php echo $book['id']; ?>"
       class="book-card" style="text-decoration:none;">

        <?php if (!empty($book['image']) && file_exists('../uploads/'.$book['image'])): ?>
            <img src="<?php echo BASE_URL; ?>/uploads/<?php echo sanitize($book['image']); ?>"
                 class="book-cover" alt="<?php echo sanitize($book['title']); ?>">
        <?php else: ?>
            <div class="book-cover-placeholder">&#128218;</div>
        <?php endif; ?>

        <div class="book-info">
            <?php
            $cp = $book['course']==='BCA' ? 'cp-bca' :
                 ($book['course']==='BSc CSIT' ? 'cp-csit' : 'cp-both');
            ?>
            <span class="course-pill <?php echo $cp; ?>">
                <?php echo $book['course']; ?>
            </span>
            <div class="book-title mt-1"><?php echo sanitize($book['title']); ?></div>
            <div class="book-author"><?php echo sanitize($book['author']); ?></div>
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