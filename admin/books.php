<?php
// ============================================================
// admin/books.php
// Book Management — Add, Edit, Delete Books
// Gyansetu Library Management System
// ============================================================

require_once '../config/db.php';
require_once '../includes/auth.php';

requireAdmin();

$conn   = getDBConnection();
$action = $_GET['action'] ?? 'list';
$bookId = intval($_GET['id'] ?? 0);

// ============================================================
// HANDLE FORM SUBMISSIONS
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['post_action'] ?? '';

    // ── ADD or EDIT ──────────────────────────────────────────
    if ($postAction === 'save') {
        $editId    = intval($_POST['book_id'] ?? 0);
        $title     = sanitize($_POST['title'] ?? '');
        $author    = sanitize($_POST['author'] ?? '');
        $genre     = sanitize($_POST['genre'] ?? '');
        $isbn      = sanitize($_POST['isbn'] ?? '');
        $publisher = sanitize($_POST['publisher'] ?? '');
        $year      = intval($_POST['published_year'] ?? 0);
        $desc      = sanitize($_POST['description'] ?? '');
        $total     = max(1, intval($_POST['total_copies'] ?? 1));
        $available = max(0, intval($_POST['available_copies'] ?? 1));

        $course   = sanitize($_POST['course']   ?? 'Both');
        $semester = intval($_POST['semester']   ?? 0);

        // Validate
        if (empty($title) || empty($author) || empty($genre)) {
            setFlash('error', 'Title, Author, and Genre are required.');
        } else {
            // Handle image upload
            $imageName = '';
            if (!empty($_FILES['book_image']['name'])) {
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $fileType     = mime_content_type($_FILES['book_image']['tmp_name']);

                if (!in_array($fileType, $allowedTypes)) {
                    setFlash('error', 'Invalid image type. Use JPG, PNG, GIF, or WebP.');
                    redirect(BASE_URL . '/admin/books.php?action=' . ($editId ? 'edit&id=' . $editId : 'add'));
                }

                $ext       = pathinfo($_FILES['book_image']['name'], PATHINFO_EXTENSION);
                $imageName = 'book_' . time() . '_' . rand(1000,9999) . '.' . $ext;
                $uploadDir = __DIR__ . '/../uploads/';

                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                move_uploaded_file($_FILES['book_image']['tmp_name'], $uploadDir . $imageName);
            }

            if ($editId) {
                if ($imageName) {
                    $oldImg = mysqli_fetch_assoc(mysqli_query($conn, "SELECT image FROM books WHERE id=$editId"))['image'] ?? '';
                    if ($oldImg && file_exists('../uploads/' . $oldImg)) unlink('../uploads/' . $oldImg);
                    $sql = "UPDATE books SET title=?,author=?,genre=?,course=?,semester=?,isbn=?,publisher=?,published_year=?,description=?,total_copies=?,available_copies=?,image=? WHERE id=?";
                    $s   = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($s, 'ssssissisiisi', $title,$author,$genre,$course,$semester,$isbn,$publisher,$year,$desc,$total,$available,$imageName,$editId);
                } else {
                    $sql = "UPDATE books SET title=?,author=?,genre=?,course=?,semester=?,isbn=?,publisher=?,published_year=?,description=?,total_copies=?,available_copies=? WHERE id=?";
                    $s   = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($s, 'ssssissisiii', $title,$author,$genre,$course,$semester,$isbn,$publisher,$year,$desc,$total,$available,$editId);
                }
                mysqli_stmt_execute($s);
                setFlash('success', 'Book updated successfully.');
            } else {
                $sql = "INSERT INTO books (title,author,genre,course,semester,isbn,publisher,published_year,description,total_copies,available_copies,image) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)";
                $s   = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($s, 'ssssissisiis', $title,$author,$genre,$course,$semester,$isbn,$publisher,$year,$desc,$total,$available,$imageName);
                mysqli_stmt_execute($s);
                setFlash('success', 'Book added successfully.');
            }

            redirect(BASE_URL . '/admin/books.php');
        }
    }

    // ── DELETE ───────────────────────────────────────────────
    elseif ($postAction === 'delete') {
        $delId = intval($_POST['book_id'] ?? 0);
        if ($delId) {
            // Delete image file
            $img = mysqli_fetch_assoc(mysqli_query($conn, "SELECT image FROM books WHERE id=$delId"))['image'] ?? '';
            if ($img && file_exists('../uploads/' . $img)) unlink('../uploads/' . $img);

            mysqli_query($conn, "DELETE FROM books WHERE id=$delId");
            setFlash('success', 'Book deleted.');
        }
        redirect(BASE_URL . '/admin/books.php');
    }
}

// ============================================================
// FETCH DATA FOR VIEWS
// ============================================================

// Edit — load existing book
$editBook = null;
if ($action === 'edit' && $bookId) {
    $s = mysqli_prepare($conn, "SELECT * FROM books WHERE id=?");
    mysqli_stmt_bind_param($s, 'i', $bookId);
    mysqli_stmt_execute($s);
    $editBook = mysqli_fetch_assoc(mysqli_stmt_get_result($s));
}

// List all books
$books = [];
$search = sanitize($_GET['search'] ?? '');
if ($search) {
    $like = "%$search%";
    $s = mysqli_prepare($conn, "SELECT * FROM books WHERE title LIKE ? OR author LIKE ? OR genre LIKE ? ORDER BY title");
    mysqli_stmt_bind_param($s, 'sss', $like, $like, $like);
    mysqli_stmt_execute($s);
    $res = mysqli_stmt_get_result($s);
} else {
    $res = mysqli_query($conn, "SELECT * FROM books ORDER BY title ASC");
}
while ($row = mysqli_fetch_assoc($res)) $books[] = $row;

$pageTitle = 'Manage Books';
require_once '../includes/header.php';
?>

<?php if ($action === 'add' || $action === 'edit'): ?>
<!-- ══════════════════════════════════════════════════════════
     ADD / EDIT BOOK FORM
══════════════════════════════════════════════════════════ -->
<div class="page-header">
    <div>
        <h1><i class="fas fa-<?php echo $action === 'add' ? 'plus' : 'edit'; ?>"></i>
            <?php echo $action === 'add' ? 'Add New Book' : 'Edit Book'; ?>
        </h1>
    </div>
    <a href="<?php echo BASE_URL; ?>/admin/books.php" class="btn btn-outline btn-sm">
        <i class="fas fa-arrow-left"></i> Back to Books
    </a>
</div>

<div style="background:#fff;border-radius:var(--radius-lg);padding:2rem;box-shadow:var(--shadow-sm);border:1px solid var(--border);max-width:700px;">
    <form method="POST" action="books.php" enctype="multipart/form-data" data-validate>
        <input type="hidden" name="post_action" value="save">
        <input type="hidden" name="book_id" value="<?php echo $editBook['id'] ?? 0; ?>">

        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Title *</label>
                <input type="text" name="title" class="form-control" required
                    value="<?php echo sanitize($editBook['title'] ?? ''); ?>" placeholder="Book Title">
            </div>
            <div class="form-group">
                <label class="form-label">Author *</label>
                <input type="text" name="author" class="form-control" required
                    value="<?php echo sanitize($editBook['author'] ?? ''); ?>" placeholder="Author Name">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Genre *</label>
                <select name="genre" class="form-control" required>
                    <option value="">Select Genre</option>
                    <?php
                    $genres = ['Computer Science','Programming','Database','Operating Systems','Networking',
                               'Artificial Intelligence','Mathematics','Software Engineering',
                               'Computer Architecture','Data Science','Science','Fiction','Non-Fiction','Other'];
                    foreach ($genres as $g):
                        $sel = (isset($editBook['genre']) && $editBook['genre'] === $g) ? 'selected' : '';
                    ?>
                    <option value="<?php echo $g; ?>" <?php echo $sel; ?>><?php echo $g; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">ISBN</label>
                <input type="text" name="isbn" class="form-control"
                    value="<?php echo sanitize($editBook['isbn'] ?? ''); ?>" placeholder="978-...">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Publisher</label>
                <input type="text" name="publisher" class="form-control"
                    value="<?php echo sanitize($editBook['publisher'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Published Year</label>
                <input type="number" name="published_year" class="form-control" min="1800" max="<?php echo date('Y'); ?>"
                    value="<?php echo $editBook['published_year'] ?? ''; ?>">
            </div>
        </div>

        <!-- Course + Semester -->
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Course *</label>
                <select name="course" class="form-control" required>
                    <?php
                    $courses = ['BSc CSIT','BCA','Both'];
                    foreach ($courses as $c):
                        $sel = (($editBook['course'] ?? 'Both') === $c) ? 'selected' : '';
                    ?>
                    <option value="<?php echo $c; ?>" <?php echo $sel; ?>><?php echo $c; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Semester (0 = All)</label>
                <select name="semester" class="form-control">
                    <option value="0" <?php echo (($editBook['semester'] ?? 0) == 0) ? 'selected' : ''; ?>>All Semesters</option>
                    <?php for ($s=1; $s<=8; $s++): ?>
                    <option value="<?php echo $s; ?>" <?php echo (($editBook['semester'] ?? 0) == $s) ? 'selected' : ''; ?>>
                        Semester <?php echo $s; ?>
                    </option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Total Copies</label>
                <input type="number" name="total_copies" class="form-control" min="1" required
                    value="<?php echo $editBook['total_copies'] ?? 1; ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Available Copies</label>
                <input type="number" name="available_copies" class="form-control" min="0" required
                    value="<?php echo $editBook['available_copies'] ?? 1; ?>">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="3"
                placeholder="Brief description of the book..."><?php echo sanitize($editBook['description'] ?? ''); ?></textarea>
        </div>

        <!-- Image upload with FIXED preview -->
        <div class="form-group">
            <label class="form-label">Book Cover Image</label>
            <div id="previewWrap" style="margin-bottom:.75rem;<?php echo empty($editBook['image']) ? 'display:none;' : ''; ?>">
                <img id="imagePreview"
                     src="<?php echo !empty($editBook['image']) ? BASE_URL.'/uploads/'.sanitize($editBook['image']) : ''; ?>"
                     style="height:140px;border-radius:8px;object-fit:cover;border:2px solid var(--border);">
                <div style="font-size:.75rem;color:var(--text-light);margin-top:.3rem;" id="previewName"></div>
            </div>
            <div class="upload-area" id="uploadArea"
                 onclick="document.getElementById('book_image').click()"
                 style="cursor:pointer;border:2px dashed var(--border);border-radius:8px;
                        padding:1.5rem;text-align:center;background:var(--ivory);
                        transition:border-color .2s;">
                <div style="font-size:2rem;margin-bottom:.4rem;">&#128247;</div>
                <p style="color:var(--text-light);font-size:.88rem;margin:0;">
                    Click to upload cover image<br>
                    <small>JPG, PNG, WebP supported</small>
                </p>
                <input type="file" id="book_image" name="book_image"
                       accept="image/jpeg,image/png,image/webp,image/gif"
                       style="display:none;"
                       onchange="previewImage(this)">
            </div>
        </div>

        <script>
        function previewImage(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    var img   = document.getElementById('imagePreview');
                    var wrap  = document.getElementById('previewWrap');
                    var name  = document.getElementById('previewName');
                    img.src   = e.target.result;
                    wrap.style.display = 'block';
                    name.textContent   = input.files[0].name;
                    document.getElementById('uploadArea').style.borderColor = 'var(--mahogany)';
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
        document.getElementById('uploadArea').addEventListener('dragover', function(e) {
            e.preventDefault();
            this.style.borderColor = 'var(--mahogany)';
            this.style.background  = '#fff';
        });
        document.getElementById('uploadArea').addEventListener('dragleave', function() {
            this.style.borderColor = 'var(--border)';
            this.style.background  = 'var(--ivory)';
        });
        document.getElementById('uploadArea').addEventListener('drop', function(e) {
            e.preventDefault();
            var file = e.dataTransfer.files[0];
            if (file && file.type.startsWith('image/')) {
                var inp = document.getElementById('book_image');
                var dt  = new DataTransfer();
                dt.items.add(file);
                inp.files = dt.files;
                previewImage(inp);
            }
        });
        </script>

        <div class="d-flex gap-2 mt-3">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> <?php echo $action === 'add' ? 'Add Book' : 'Update Book'; ?>
            </button>
            <a href="<?php echo BASE_URL; ?>/admin/books.php" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>

<?php else: ?>
<!-- ══════════════════════════════════════════════════════════
     BOOK LIST
══════════════════════════════════════════════════════════ -->
<div class="page-header">
    <div>
        <h1><i class="fas fa-books"></i> Manage Books</h1>
        <p class="page-subtitle"><?php echo count($books); ?> books in catalog</p>
    </div>
    <a href="<?php echo BASE_URL; ?>/admin/books.php?action=add" class="btn btn-primary">
        <i class="fas fa-plus"></i> Add New Book
    </a>
</div>

<!-- Search -->
<div class="search-bar-wrapper">
    <form method="GET" class="search-form">
        <input type="hidden" name="action" value="list">
        <input type="text" name="search" class="form-control" placeholder="Search books..."
            value="<?php echo $search; ?>">
        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
        <?php if ($search): ?><a href="books.php" class="btn btn-outline"><i class="fas fa-times"></i> Clear</a><?php endif; ?>
    </form>
</div>

<?php if (empty($books)): ?>
<div class="empty-state">
    <i class="fas fa-books"></i>
    <h3>No books found</h3>
    <p>Add your first book to get started.</p>
</div>
<?php else: ?>
<div class="table-wrapper">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Cover</th>
                    <th>Title / Author</th>
                    <th>Course</th><th>Sem</th><th>Genre</th>
                    <th>Copies</th>
                    <th>Available</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php $i=1; foreach ($books as $book): ?>
                <tr>
                    <td><?php echo $i++; ?></td>
                    <td>
                        <?php if ($book['image'] && file_exists('../uploads/' . $book['image'])): ?>
                            <img src="<?php echo BASE_URL; ?>/uploads/<?php echo sanitize($book['image']); ?>"
                                 style="width:44px;height:55px;object-fit:cover;border-radius:4px;">
                        <?php else: ?>
                            <div style="width:44px;height:55px;background:var(--mahogany);border-radius:4px;display:flex;align-items:center;justify-content:center;color:rgba(255,255,255,.4);">
                                <i class="fas fa-book" style="font-size:.8rem;"></i>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong><?php echo sanitize($book['title']); ?></strong><br>
                        <small style="color:var(--text-light);"><?php echo sanitize($book['author']); ?></small>
                    </td>
                    <td><span class="badge badge-secondary"><?php echo sanitize($book["course"]); ?></span></td><td style="font-weight:600;color:var(--mahogany);"><?php echo $book["semester"] > 0 ? $book["semester"] : "All"; ?></td>
                    <td><?php echo $book['total_copies']; ?></td>
                    <td>
                        <span class="<?php echo $book['available_copies'] > 0 ? 'available' : 'unavailable'; ?>" style="font-weight:600;">
                            <?php echo $book['available_copies']; ?>
                        </span>
                    </td>
                    <td>
                        <div class="d-flex gap-1">
                            <a href="books.php?action=edit&id=<?php echo $book['id']; ?>" class="btn btn-outline btn-sm">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="post_action" value="delete">
                                <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                                <button type="submit" class="btn btn-danger btn-sm"
                                    data-confirm="Delete '<?php echo sanitize($book['title']); ?>'? This cannot be undone.">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<?php
mysqli_close($conn);
require_once '../includes/footer.php';
?>