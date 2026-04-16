<?php
// ============================================================
// algorithms/collaborative.php
// Collaborative Filtering — Item-Based using Cosine Similarity
// Gyansetu Library Management System
// ============================================================
//
// CONCEPT:
//   Recommends books based on BORROWING PATTERNS of all users.
//   "Users who borrowed the same books as you also borrowed..."
//
// TYPE: Item-Based Collaborative Filtering
//
// ── USER-BOOK MATRIX ─────────────────────────────────────────
//
//            Book1  Book2  Book3  Book4
//   Anita  →   1      0      0      0
//   user1  →   1      1      0      0
//   user2  →   1      0      1      0
//
// ── FORMULA 1: Cosine Similarity between two books ───────────
//
//                    A · B
//   cos(A, B) = ─────────────
//                |A| × |B|
//
//   Where A and B are COLUMN vectors of the user-book matrix:
//   Each element = 1 if user borrowed that book, 0 otherwise
//   Dot product  = number of users who borrowed BOTH books
//
//   Example:
//   Book1 = [1,1,1]  (borrowed by Anita, user1, user2)
//   Book2 = [0,1,0]  (borrowed by user1 only)
//   cos(Book1, Book2) = (0+1+0) / (sqrt(3) x sqrt(1)) = 0.577
//
// ── FORMULA 2: Recommendation Score ─────────────────────────
//
//   Score(u, j) = SUM of similarity(j, i) for all i in liked_by_u
//
//   Where:
//   u           = target user
//   j           = candidate book (not yet borrowed)
//   liked_by_u  = books already borrowed by user u
//
//   = For every book the user HAS borrowed (i),
//     add the similarity between candidate book j and book i
//   = Higher score = more users who borrowed same books
//     as the target user ALSO borrowed this candidate
//
// WORKED EXAMPLE (from IdeaFlux standard):
//
//   User-Book matrix:
//          B1   B2   B3
//   Anita   1    0    0
//   user1   1    1    0
//   user2   0    1    1
//
//   Anita borrowed B1. Candidate books: B2, B3
//
//   similarity(B1, B2):
//     B1=[1,1,0], B2=[0,1,1]
//     dot = 0+1+0 = 1
//     |B1|= sqrt(2), |B2|= sqrt(2)
//     cos = 1/2 = 0.50
//
//   similarity(B1, B3):
//     B1=[1,1,0], B3=[0,0,1]
//     dot = 0
//     cos = 0.00
//
//   Score(Anita, B2) = similarity(B2,B1) = 0.50 → RECOMMEND
//   Score(Anita, B3) = similarity(B3,B1) = 0.00 → skip
//
// STEP-BY-STEP PROCESS:
//   1. Fetch all borrow records from issued_books
//   2. Build User-Book matrix (user_id → book_id → 1)
//   3. For each book, extract its "column" (who borrowed it)
//   4. Compute cosine similarity between all book pairs
//   5. Get target user's borrowed books
//   6. For each unread candidate book, compute recommendation score
//      = sum of similarities to all user's borrowed books
//   7. Sort by score, filter threshold >= 0.05, return top N
// ============================================================

// ── STEP 1: Build User-Book Matrix ───────────────────────────
/**
 * buildUserBookMatrix()
 *
 * Fetches all borrow records and builds a 2D matrix:
 * $matrix[userId][bookId] = 1
 *
 * @param mysqli $conn
 * @return array   Sparse matrix indexed by [userId][bookId]
 */
function buildUserBookMatrix($conn) {
    $matrix = [];
    $result = mysqli_query($conn, "SELECT DISTINCT user_id, book_id FROM issued_books");
    while ($row = mysqli_fetch_assoc($result)) {
        $matrix[$row['user_id']][$row['book_id']] = 1;
    }
    return $matrix;
}

// ── STEP 3: Extract book column vector ───────────────────────
/**
 * getBookVector()
 *
 * Extracts the column for a given book from the matrix.
 * Returns which users borrowed this book.
 * [userId => 1 or 0]
 *
 * @param array $matrix
 * @param int   $bookId
 * @return array
 */
function getBookVector($matrix, $bookId) {
    $vector = [];
    foreach ($matrix as $userId => $books) {
        $vector[$userId] = isset($books[$bookId]) ? 1 : 0;
    }
    return $vector;
}

// ── STEP 4: Cosine Similarity between book vectors ───────────
/**
 * itemCosineSimilarity()
 *
 * cos(A,B) = (A.B) / (|A| x |B|)
 *
 * A and B are book column vectors (who borrowed each book)
 * Dot product = users who borrowed BOTH books
 *
 * @param array $vecA
 * @param array $vecB
 * @return float  0.0 to 1.0
 */
function itemCosineSimilarity($vecA, $vecB) {
    $dot  = 0.0;
    foreach ($vecA as $userId => $val) {
        $dot += $val * ($vecB[$userId] ?? 0);
    }
    $magA = sqrt(array_sum(array_map(fn($v) => $v*$v, $vecA)));
    $magB = sqrt(array_sum(array_map(fn($v) => $v*$v, $vecB)));
    if ($magA == 0 || $magB == 0) return 0.0;
    return $dot / ($magA * $magB);
}

// ── MAIN: Collaborative Filtering Recommendations ────────────
/**
 * getCollaborativeRecommendations()
 *
 * STEP-BY-STEP:
 * 1. Build User-Book matrix
 * 2. Get target user's borrowed books
 * 3. For each unread candidate book:
 *    Score(u,j) = SUM similarity(j, i) for i in user_borrowed
 * 4. Filter by threshold >= 0.05
 * 5. Sort descending, return top N
 *
 * @param int    $targetUserId
 * @param mysqli $conn
 * @param int    $topN
 * @return array
 */
function getCollaborativeRecommendations($targetUserId, $conn, $topN = 6, $course = '') {

    // Step 1: Build matrix
    $matrix = buildUserBookMatrix($conn);
    if (empty($matrix)) return [];

    // Step 2: Get target user's borrowed books
    $userBorrowed = [];
    if (isset($matrix[$targetUserId])) {
        $userBorrowed = array_keys($matrix[$targetUserId]);
    }
    if (empty($userBorrowed)) return [];

    // Get ALL book IDs in system
    $result     = mysqli_query($conn, "SELECT id FROM books");
    $allBookIds = [];
    while ($r = mysqli_fetch_assoc($result)) $allBookIds[] = $r['id'];

    // Candidate books = books user has NOT borrowed
    $candidates = array_diff($allBookIds, $userBorrowed);
    if (empty($candidates)) return [];

    // Step 3: Compute recommendation score for each candidate
    // Score(u, j) = SUM of similarity(j, i) for all i in userBorrowed
    $scores = [];
    foreach ($candidates as $candidateId) {
        $candidateVec = getBookVector($matrix, $candidateId);
        $totalScore   = 0.0;

        foreach ($userBorrowed as $borrowedId) {
            $borrowedVec  = getBookVector($matrix, $borrowedId);
            $totalScore  += itemCosineSimilarity($candidateVec, $borrowedVec);
        }

        // Step 4: Filter by threshold
        if ($totalScore >= 0.05) {
            $scores[$candidateId] = $totalScore;
        }
    }

    if (empty($scores)) return [];

    // Step 5: Sort by score descending
    arsort($scores);
    $topIds = array_slice(array_keys($scores), 0, $topN);

    // Fetch full book details — filter by course if specified
    $ids    = implode(',', array_map('intval', $topIds));
    if ($course) {
        $cStmt = mysqli_prepare($conn,
            "SELECT * FROM books
             WHERE id IN ($ids)
             AND (course=? OR course='Both')");
        mysqli_stmt_bind_param($cStmt, 's', $course);
        mysqli_stmt_execute($cStmt);
        $result = mysqli_stmt_get_result($cStmt);
    } else {
        $result = mysqli_query($conn, "SELECT * FROM books WHERE id IN ($ids)");
    }
    $recs   = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $row['cf_score'] = round($scores[$row['id']], 4);
        $recs[]          = $row;
    }

    // Sort by score (SQL IN doesn't guarantee order)
    usort($recs, fn($a, $b) => $b['cf_score'] <=> $a['cf_score']);
    return $recs;
}
?>