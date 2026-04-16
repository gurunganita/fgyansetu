<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
requireAdmin();
$conn = getDBConnection();

// Stats
$totalStudents = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS c FROM users WHERE role='student'"))['c'];
$totalCSIT = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS c FROM users WHERE role='student' AND course='BSc CSIT'"))['c'];
$totalBCA  = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS c FROM users WHERE role='student' AND course='BCA'"))['c'];

// Total books = sum of all copies
$totalBooks = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT SUM(total_copies) AS c FROM books"))['c'] ?? 0;
$totalTitles = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS c FROM books"))['c'];
$csitBooks = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS c FROM books WHERE course='BSc CSIT' OR course='Both'"))['c'];
$bcaBooks  = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS c FROM books WHERE course='BCA' OR course='Both'"))['c'];

$issuedBooks  = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS c FROM issued_books WHERE status IN ('issued','overdue')"))['c'];
$pendingBooks = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS c FROM issued_books WHERE status='pending'"))['c'];
$overdueBooks = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS c FROM issued_books
     WHERE status='overdue' OR (status='issued' AND due_date < CURDATE())"))['c'];
$pendingRes   = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS c FROM reservations WHERE status='pending'"))['c'];
$unpaidFines  = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COALESCE(SUM(amount),0) AS t FROM fines WHERE status='unpaid'"))['t'];

// Recent issued
$recentIssued = [];
$sql = "SELECT ib.*, u.name AS uname, b.title AS btitle
        FROM issued_books ib
        JOIN users u ON ib.user_id=u.id
        JOIN books b ON ib.book_id=b.id
        ORDER BY ib.issue_date DESC LIMIT 6";
$r = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($r)) $recentIssued[] = $row;

// Recent students
$recentStudents = [];
$r2 = mysqli_query($conn,
    "SELECT id, name, email, course, semester, created_at
     FROM users WHERE role='student'
     ORDER BY created_at DESC LIMIT 6");
while ($row = mysqli_fetch_assoc($r2)) $recentStudents[] = $row;

// Books per semester (CSIT)
$csitSemData = [];
for ($s=1; $s<=8; $s++) {
    $r = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) AS c FROM books
         WHERE (course='BSc CSIT' OR course='Both') AND semester=$s"));
    $csitSemData[$s] = $r['c'];
}
$bcaSemData = [];
for ($s=1; $s<=8; $s++) {
    $r = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) AS c FROM books
         WHERE (course='BCA' OR course='Both') AND semester=$s"));
    $bcaSemData[$s] = $r['c'];
}

$pageTitle = 'Dashboard';
require_once '../includes/header.php';
?>

<style>
/* ── Admin Dashboard Styles ── */
.dash-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1rem;
}
.dash-title h1 {
    font-family: var(--font-heading);
    font-size: 1.9rem;
    color: var(--mahogany);
}
.dash-title p { color: var(--text-light); font-size: .88rem; margin-top: .2rem; }

/* Stats Grid */
.stats-section { margin-bottom: 2rem; }
.stats-section-title {
    font-size: .72rem;
    font-weight: 700;
    color: var(--text-light);
    text-transform: uppercase;
    letter-spacing: .12em;
    margin-bottom: .85rem;
    display: flex; align-items: center; gap: .5rem;
}
.stats-section-title::after {
    content: '';
    flex: 1; height: 1px;
    background: var(--border);
}
.stats-grid-4 {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 1rem;
}
.scard {
    background: #fff;
    border-radius: var(--radius-md);
    padding: 1.25rem;
    border: 1px solid var(--border);
    box-shadow: var(--shadow-sm);
    display: flex;
    flex-direction: column;
    gap: .4rem;
    transition: all .2s;
    text-decoration: none;
}
.scard:hover {
    box-shadow: var(--shadow-md);
    transform: translateY(-2px);
}
.scard-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.scard-icon {
    width: 42px; height: 42px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.2rem;
}
.si-red    { background: rgba(74,28,28,.1);   color: var(--mahogany); }
.si-gold   { background: rgba(201,151,60,.15); color: var(--gold); }
.si-green  { background: rgba(45,106,79,.1);  color: var(--success); }
.si-blue   { background: rgba(41,128,185,.1); color: var(--info); }
.si-orange { background: rgba(230,126,34,.1); color: #e67e22; }
.si-pink   { background: rgba(192,57,43,.1);  color: var(--error); }
.scard-change {
    font-size: .7rem;
    color: var(--text-light);
}
.scard-val {
    font-family: var(--font-heading);
    font-size: 1.8rem;
    color: var(--mahogany);
    line-height: 1;
}
.scard-label {
    font-size: .8rem;
    color: var(--text-light);
    font-weight: 500;
}

/* Alert banner */
.alert-banner {
    display: flex;
    align-items: center;
    gap: 1rem;
    background: linear-gradient(135deg, #fff3cd, #fff8e6);
    border: 1px solid #ffc107;
    border-left: 4px solid #ffc107;
    border-radius: var(--radius-sm);
    padding: .85rem 1.25rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
}
.alert-banner .ab-icon { font-size: 1.3rem; }
.alert-banner .ab-text { flex: 1; font-size: .88rem; color: #856404; }
.alert-banner strong { font-weight: 700; }

/* Two-col layout */
.dash-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}
@media(max-width:900px) { .dash-grid { grid-template-columns: 1fr; } }

/* Table card */
.tcard {
    background: #fff;
    border-radius: var(--radius-md);
    border: 1px solid var(--border);
    box-shadow: var(--shadow-sm);
    overflow: hidden;
}
.tcard-header {
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.tcard-title {
    font-family: var(--font-heading);
    font-size: 1rem;
    color: var(--mahogany);
    display: flex;
    align-items: center;
    gap: .4rem;
}

/* Semester bars */
.sem-bars { padding: 1rem 1.25rem; }
.sem-bar-row {
    display: flex;
    align-items: center;
    gap: .75rem;
    margin-bottom: .6rem;
}
.sem-bar-label {
    font-size: .78rem;
    font-weight: 700;
    color: var(--text-mid);
    width: 50px;
    flex-shrink: 0;
}
.sem-bar-track {
    flex: 1;
    height: 8px;
    background: var(--ivory-dark);
    border-radius: 4px;
    overflow: hidden;
}
.sem-bar-fill {
    height: 100%;
    border-radius: 4px;
    background: var(--mahogany);
    transition: width .5s ease;
}
.sem-bar-fill.bca { background: var(--info); }
.sem-bar-count {
    font-size: .75rem;
    color: var(--text-light);
    width: 24px;
    text-align: right;
    flex-shrink: 0;
}
</style>

<!-- Header -->
<div class="dash-header">
    <div class="dash-title">
        <h1>&#9699; Admin Dashboard</h1>
        <p>Welcome back, <?php echo sanitize(getCurrentUserName()); ?> &mdash;
           <?php echo date('l, F d Y'); ?></p>
    </div>
    <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
        <a href="<?php echo BASE_URL; ?>/admin/books.php?action=add"
           class="btn btn-primary btn-sm">&#43; Add Book</a>
        <a href="<?php echo BASE_URL; ?>/admin/issued.php?action=issue"
           class="btn btn-gold btn-sm">&#128218; Issue Book</a>
    </div>
</div>

<!-- Alert for pending items -->
<?php if ($pendingBooks > 0 || $overdueBooks > 0): ?>
<div class="alert-banner">
    <span class="ab-icon">&#9888;</span>
    <div class="ab-text">
        <?php if ($pendingBooks > 0): ?>
        <strong><?php echo $pendingBooks; ?> pending</strong> borrow request(s) waiting approval.
        <?php endif; ?>
        <?php if ($overdueBooks > 0): ?>
        &nbsp; <strong><?php echo $overdueBooks; ?> overdue</strong> book(s) not yet returned.
        <?php endif; ?>
    </div>
    <a href="<?php echo BASE_URL; ?>/admin/issued.php?filter=pending"
       class="btn btn-sm btn-primary">Review Now &#8594;</a>
</div>
<?php endif; ?>

<!-- Student Stats -->
<div class="stats-section">
    <div class="stats-section-title">&#128100; Students</div>
    <div class="stats-grid-4">
        <div class="scard">
            <div class="scard-top">
                <div class="scard-icon si-blue">&#128100;</div>
            </div>
            <div class="scard-val"><?php echo $totalStudents; ?></div>
            <div class="scard-label">Total Students</div>
        </div>
        <div class="scard">
            <div class="scard-top">
                <div class="scard-icon si-red">&#128187;</div>
            </div>
            <div class="scard-val"><?php echo $totalCSIT; ?></div>
            <div class="scard-label">BSc CSIT Students</div>
        </div>
        <div class="scard">
            <div class="scard-top">
                <div class="scard-icon si-blue">&#128200;</div>
            </div>
            <div class="scard-val"><?php echo $totalBCA; ?></div>
            <div class="scard-label">BCA Students</div>
        </div>
    </div>
</div>

<!-- Book Stats -->
<div class="stats-section">
    <div class="stats-section-title">&#128218; Library Collection</div>
    <div class="stats-grid-4">
        <div class="scard">
            <div class="scard-top">
                <div class="scard-icon si-red">&#128218;</div>
                <span class="scard-change"><?php echo $totalTitles; ?> titles</span>
            </div>
            <div class="scard-val"><?php echo $totalBooks; ?></div>
            <div class="scard-label">Total Book Copies</div>
        </div>
        <div class="scard">
            <div class="scard-top">
                <div class="scard-icon si-red">&#128187;</div>
            </div>
            <div class="scard-val"><?php echo $csitBooks; ?></div>
            <div class="scard-label">BSc CSIT Titles</div>
        </div>
        <div class="scard">
            <div class="scard-top">
                <div class="scard-icon si-blue">&#128200;</div>
            </div>
            <div class="scard-val"><?php echo $bcaBooks; ?></div>
            <div class="scard-label">BCA Titles</div>
        </div>
    </div>
</div>

<!-- Circulation Stats -->
<div class="stats-section">
    <div class="stats-section-title">&#128196; Circulation</div>
    <div class="stats-grid-4">
        <div class="scard" style="cursor:pointer"
             onclick="location.href='<?php echo BASE_URL; ?>/admin/issued.php?filter=pending'">
            <div class="scard-top">
                <div class="scard-icon si-gold">&#9203;</div>
            </div>
            <div class="scard-val"><?php echo $pendingBooks; ?></div>
            <div class="scard-label">Pending Requests</div>
        </div>
        <div class="scard">
            <div class="scard-top">
                <div class="scard-icon si-green">&#10003;</div>
            </div>
            <div class="scard-val"><?php echo $issuedBooks; ?></div>
            <div class="scard-label">Currently Issued</div>
        </div>
        <div class="scard">
            <div class="scard-top">
                <div class="scard-icon si-pink">&#9888;</div>
            </div>
            <div class="scard-val"><?php echo $overdueBooks; ?></div>
            <div class="scard-label">Overdue Books</div>
        </div>
        <div class="scard">
            <div class="scard-top">
                <div class="scard-icon si-orange">&#128278;</div>
            </div>
            <div class="scard-val"><?php echo $pendingRes; ?></div>
            <div class="scard-label">Pending Reservations</div>
        </div>
        <div class="scard">
            <div class="scard-top">
                <div class="scard-icon si-red">&#128176;</div>
            </div>
            <div class="scard-val">Rs <?php echo number_format($unpaidFines,0); ?></div>
            <div class="scard-label">Unpaid Fines</div>
        </div>
    </div>
</div>

<!-- Two column: Books by semester + Recent activity -->
<div class="dash-grid">

    <!-- Books by semester chart -->
    <div class="tcard">
        <div class="tcard-header">
            <div class="tcard-title">&#128218; Books per Semester</div>
        </div>
        <div class="sem-bars">
            <?php
            $maxCSIT = max(array_values($csitSemData) ?: [1]);
            $maxBCA  = max(array_values($bcaSemData)  ?: [1]);
            $maxAll  = max($maxCSIT, $maxBCA, 1);
            ?>
            <div style="font-size:.72rem;font-weight:700;color:var(--text-light);
                        text-transform:uppercase;letter-spacing:.1em;margin-bottom:.75rem;">
                &#128187; BSc CSIT
            </div>
            <?php for ($s=1; $s<=8; $s++):
                $count = $csitSemData[$s];
                $pct   = $maxAll > 0 ? round(($count / $maxAll) * 100) : 0;
            ?>
            <div class="sem-bar-row">
                <div class="sem-bar-label">Sem <?php echo $s; ?></div>
                <div class="sem-bar-track">
                    <div class="sem-bar-fill"
                         style="width:<?php echo $pct; ?>%"></div>
                </div>
                <div class="sem-bar-count"><?php echo $count; ?></div>
            </div>
            <?php endfor; ?>

            <div style="font-size:.72rem;font-weight:700;color:var(--text-light);
                        text-transform:uppercase;letter-spacing:.1em;
                        margin-top:1rem;margin-bottom:.75rem;">
                &#128200; BCA
            </div>
            <?php for ($s=1; $s<=8; $s++):
                $count = $bcaSemData[$s];
                $pct   = $maxAll > 0 ? round(($count / $maxAll) * 100) : 0;
            ?>
            <div class="sem-bar-row">
                <div class="sem-bar-label">Sem <?php echo $s; ?></div>
                <div class="sem-bar-track">
                    <div class="sem-bar-fill bca"
                         style="width:<?php echo $pct; ?>%"></div>
                </div>
                <div class="sem-bar-count"><?php echo $count; ?></div>
            </div>
            <?php endfor; ?>
        </div>
    </div>

    <!-- Recent Borrows + Students -->
    <div style="display:flex;flex-direction:column;gap:1.5rem;">

        <!-- Recent borrow requests -->
        <div class="tcard">
            <div class="tcard-header">
                <div class="tcard-title">&#9203; Recent Requests</div>
                <a href="<?php echo BASE_URL; ?>/admin/issued.php"
                   class="btn btn-outline btn-sm">View All</a>
            </div>
            <?php if (empty($recentIssued)): ?>
            <div style="padding:1.5rem;text-align:center;color:var(--text-light);font-size:.88rem;">
                No borrow records yet.
            </div>
            <?php else: ?>
            <table class="table" style="font-size:.85rem;">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Book</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentIssued as $row):
                        $st = $row['status'];
                        if ($st==='issued' && strtotime($row['due_date']) < time())
                            $st = 'overdue';
                        $bc = ['pending'=>'badge-warning','issued'=>'badge-info',
                               'overdue'=>'badge-danger','returned'=>'badge-success',
                               'cancelled'=>'badge-secondary'][$st] ?? 'badge-secondary';
                    ?>
                    <tr>
                        <td><?php echo sanitize($row['uname']); ?></td>
                        <td style="max-width:130px;overflow:hidden;
                                   text-overflow:ellipsis;white-space:nowrap;">
                            <?php echo sanitize($row['btitle']); ?>
                        </td>
                        <td><span class="badge <?php echo $bc; ?>">
                            <?php echo ucfirst($st); ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- Recent registrations -->
        <div class="tcard">
            <div class="tcard-header">
                <div class="tcard-title">&#128100; New Students</div>
                <a href="<?php echo BASE_URL; ?>/admin/users.php"
                   class="btn btn-outline btn-sm">View All</a>
            </div>
            <?php if (empty($recentStudents)): ?>
            <div style="padding:1.5rem;text-align:center;color:var(--text-light);font-size:.88rem;">
                No students registered yet.
            </div>
            <?php else: ?>
            <table class="table" style="font-size:.85rem;">
                <thead>
                    <tr><th>Name</th><th>Course</th><th>Sem</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($recentStudents as $s): ?>
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:.5rem;">
                                <div style="width:28px;height:28px;border-radius:50%;
                                            background:var(--mahogany);
                                            display:flex;align-items:center;justify-content:center;
                                            color:#fff;font-size:.75rem;font-weight:700;flex-shrink:0;">
                                    <?php echo strtoupper(substr($s['name'],0,1)); ?>
                                </div>
                                <?php echo sanitize($s['name']); ?>
                            </div>
                        </td>
                        <td>
                            <span class="badge <?php echo $s['course']==='BCA' ? 'badge-info' : 'badge-danger'; ?>">
                                <?php echo $s['course']; ?>
                            </span>
                        </td>
                        <td style="font-weight:700;color:var(--mahogany);">
                            <?php echo $s['semester']; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- Quick Actions -->
<div style="background:#fff;border-radius:var(--radius-md);border:1px solid var(--border);
            padding:1.25rem 1.5rem;box-shadow:var(--shadow-sm);">
    <div class="stats-section-title" style="margin-bottom:1rem;">&#9889; Quick Actions</div>
    <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
        <a href="<?php echo BASE_URL; ?>/admin/books.php?action=add"
           class="btn btn-primary btn-sm">&#43; Add Book</a>
        <a href="<?php echo BASE_URL; ?>/admin/issued.php?filter=pending"
           class="btn btn-gold btn-sm">&#9203; Pending Requests</a>
        <a href="<?php echo BASE_URL; ?>/admin/users.php"
           class="btn btn-outline btn-sm">&#128100; Manage Students</a>
        <a href="<?php echo BASE_URL; ?>/admin/reservations.php"
           class="btn btn-outline btn-sm">&#128278; Reservations</a>
        <a href="<?php echo BASE_URL; ?>/admin/fines.php?filter=unpaid"
           class="btn btn-outline btn-sm">&#128176; Unpaid Fines</a>
        <a href="<?php echo BASE_URL; ?>/admin/books.php"
           class="btn btn-outline btn-sm">&#128218; All Books</a>
    </div>
</div>

<?php
mysqli_close($conn);
require_once '../includes/footer.php';
?>