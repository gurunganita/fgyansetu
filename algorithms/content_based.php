<?php
// ============================================================
// algorithms/content_based.php
// Content-Based Filtering — Proper TF-IDF + Cosine Similarity
// Gyansetu Library Management System
// ============================================================
//
// FORMULA 1: Term Frequency
//   TF(t,d) = count(t in d) / total_terms(d)
//
// FORMULA 2: Inverse Document Frequency
//   IDF(t,D) = log( N / (1 + df(t)) )
//   N = total docs, df(t) = docs containing term t
//
// FORMULA 3: TF-IDF
//   TF-IDF(t,d,D) = TF(t,d) x IDF(t,D)
//
// FORMULA 4: Cosine Similarity
//   cos(A,B) = (A.B) / (|A| x |B|)
//
// SIMILARITY THRESHOLD = 0.05
// ============================================================

require_once __DIR__ . '/../config/db.php';

// Stopwords to remove during preprocessing
$STOPWORDS = ['the','a','an','and','or','but','in','on','at','to','for',
    'of','with','by','from','is','are','was','were','be','been','being',
    'have','has','this','that','it','its','as','do','did','will','would',
    'could','should','not','no','all','each','every','few','more','most',
    'some','into','through','before','after','between','out','over','under'];

define('SIMILARITY_THRESHOLD', 0.05);

// ── STEP 2: Preprocess text ───────────────────────────────────
// a. Lowercase  b. Remove punctuation  c. Tokenize  d. Remove stopwords
function preprocessText($text) {
    global $STOPWORDS;
    $text   = strtolower($text);
    $text   = preg_replace('/[^a-z0-9\s]/', ' ', $text);
    $tokens = preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);
    $tokens = array_filter($tokens, function($w) use ($STOPWORDS) {
        return !in_array($w, $STOPWORDS) && strlen($w) > 1;
    });
    return array_values($tokens);
}

// ── STEP 3a: Term Frequency ──────────────────────────────────
// TF(t,d) = count(t in d) / total_terms(d)
function computeTF($tokens) {
    $tf    = [];
    $total = count($tokens);
    if ($total === 0) return [];
    foreach ($tokens as $token) {
        $tf[$token] = ($tf[$token] ?? 0) + 1;
    }
    foreach ($tf as $term => $count) {
        $tf[$term] = $count / $total;  // normalize
    }
    return $tf;
}

// ── STEP 3b: Inverse Document Frequency ─────────────────────
// IDF(t,D) = log( N / (1 + df(t)) )
function computeIDF($allTokens) {
    $N  = count($allTokens);
    $df = [];
    foreach ($allTokens as $tokens) {
        foreach (array_unique($tokens) as $token) {
            $df[$token] = ($df[$token] ?? 0) + 1;
        }
    }
    $idf = [];
    foreach ($df as $term => $docFreq) {
        $idf[$term] = log($N / (1 + $docFreq));  // smoothed IDF
    }
    return $idf;
}

// ── STEP 3c: TF-IDF ──────────────────────────────────────────
// TF-IDF(t,d,D) = TF(t,d) x IDF(t,D)
function computeTFIDF($tf, $idf) {
    $tfidf = [];
    foreach ($tf as $term => $tfScore) {
        $tfidf[$term] = $tfScore * ($idf[$term] ?? 0);
    }
    return $tfidf;
}

// ── STEP 4: Cosine Similarity ─────────────────────────────────
// cos(A,B) = (A.B) / (|A| x |B|)
function cosineSimilarity($vecA, $vecB) {
    $dot  = 0.0;
    foreach ($vecA as $term => $w) {
        if (isset($vecB[$term])) $dot += $w * $vecB[$term];
    }
    $magA = sqrt(array_sum(array_map(fn($v) => $v*$v, $vecA)));
    $magB = sqrt(array_sum(array_map(fn($v) => $v*$v, $vecB)));
    if ($magA == 0 || $magB == 0) return 0.0;
    return $dot / ($magA * $magB);
}

// ── MAIN: Content-Based Recommendations ──────────────────────
// Full TF-IDF pipeline for Related Books
function getContentBasedRecommendations($sourceBookId, $allBooks, $topN = 4) {
    if (empty($allBooks)) return [];

    // Step 1: Build document text = title + author + genre
    $documents = [];
    foreach ($allBooks as $book) {
        $documents[$book['id']] = $book['title'].' '.$book['author'].' '.$book['genre'];
    }
    if (!isset($documents[$sourceBookId])) return [];

    // Step 2: Preprocess
    $tokenized = [];
    foreach ($documents as $id => $text) {
        $tokenized[$id] = preprocessText($text);
    }

    // Step 3: Compute TF, IDF, TF-IDF
    $idf          = computeIDF(array_values($tokenized));
    $tfidfVectors = [];
    foreach ($tokenized as $id => $tokens) {
        $tfidfVectors[$id] = computeTFIDF(computeTF($tokens), $idf);
    }

    // Step 4: Compute cosine similarity against source book
    $sourceVector = $tfidfVectors[$sourceBookId];
    $similarities = [];
    foreach ($allBooks as $book) {
        if ($book['id'] == $sourceBookId) continue;
        $score = cosineSimilarity($sourceVector, $tfidfVectors[$book['id']]);

        // Step 5: Filter by threshold 0.05
        if ($score >= SIMILARITY_THRESHOLD) {
            $book['score'] = round($score, 4);
            $similarities[] = $book;
        }
    }

    // Step 6: Sort descending, return top N
    usort($similarities, fn($a, $b) => $b['score'] <=> $a['score']);
    return array_slice($similarities, 0, $topN);
}

// ── Personalized CB for Home Page ────────────────────────────
// Merges TF-IDF vectors of all borrowed books into user profile
function getPersonalizedContentRecs($userId, $allBooks, $conn, $topN = 6) {
    $stmt = mysqli_prepare($conn, "SELECT DISTINCT book_id FROM issued_books WHERE user_id=?");
    mysqli_stmt_bind_param($stmt, 'i', $userId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $borrowedIds = [];
    while ($r = mysqli_fetch_assoc($res)) $borrowedIds[] = $r['book_id'];
    if (empty($borrowedIds)) return [];

    // Build & compute TF-IDF for all books
    $documents = [];
    foreach ($allBooks as $book) {
        $documents[$book['id']] = $book['title'].' '.$book['author'].' '.$book['genre'];
    }
    $tokenized = [];
    foreach ($documents as $id => $text) $tokenized[$id] = preprocessText($text);
    $idf          = computeIDF(array_values($tokenized));
    $tfidfVectors = [];
    foreach ($tokenized as $id => $tokens) {
        $tfidfVectors[$id] = computeTFIDF(computeTF($tokens), $idf);
    }

    // Build user profile = sum of TF-IDF vectors of borrowed books
    $profile = [];
    foreach ($borrowedIds as $bid) {
        if (!isset($tfidfVectors[$bid])) continue;
        foreach ($tfidfVectors[$bid] as $term => $weight) {
            $profile[$term] = ($profile[$term] ?? 0) + $weight;
        }
    }
    if (empty($profile)) return [];

    // Score unread books against profile
    $scored = [];
    foreach ($allBooks as $book) {
        if (in_array($book['id'], $borrowedIds)) continue;
        $score = cosineSimilarity($profile, $tfidfVectors[$book['id']] ?? []);
        if ($score >= SIMILARITY_THRESHOLD) {
            $book['cb_score'] = round($score, 4);
            $scored[]         = $book;
        }
    }
    usort($scored, fn($a, $b) => $b['cb_score'] <=> $a['cb_score']);
    return array_slice($scored, 0, $topN);
}
?>
//