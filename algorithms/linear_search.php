<?php
// ============================================================
// algorithms/linear_search.php
// Linear Search Algorithm — Course-Filtered
// Gyansetu Library Management System
// ============================================================
//
// TIME COMPLEXITY:
//   Worst case:  O(n)  — traverse all n books
//   Best case:   O(1)  — match found at position 0
//   Average:     O(n/2)
//
// COURSE FILTER:
//   Only fetches books for the student's course
//   (BSc CSIT or BCA) — not all books
// ============================================================

require_once __DIR__ . '/../config/db.php';

/**
 * linearSearch()
 * Traverses books array manually, comparing query
 * against title, author, genre using strpos()
 *
 * @param string $query    Search keyword
 * @param array  $books    Array of books (already course-filtered)
 * @return array           Matched books with 'match_field' key
 */
function linearSearch($query, $books) {
    // Step 1: Normalize query
    $query = strtolower(trim($query));
    if ($query === '') return [];

    $results = [];

    // Step 2: Traverse each book ONE BY ONE — O(n)
    for ($i = 0; $i < count($books); $i++) {
        $book   = $books[$i];
        $title  = strtolower($book['title']);
        $author = strtolower($book['author']);
        $genre  = strtolower($book['genre']);

        $matched = false;
        $field   = '';

        // Step 3: Compare against each field
        if (strpos($title, $query) !== false) {
            $matched = true; $field = 'title';
        } elseif (strpos($author, $query) !== false) {
            $matched = true; $field = 'author';
        } elseif (strpos($genre, $query) !== false) {
            $matched = true; $field = 'genre';
        }

        // Step 4: Add to results if matched
        if ($matched) {
            $book['match_field'] = $field;
            $results[] = $book;
        }
    }

    return $results;
}

/**
 * getAllBooksForSearch()
 * Fetches only student's course books for linear search
 * BSc CSIT student → CSIT + Both books only
 * BCA student → BCA + Both books only
 *
 * @param mysqli $conn
 * @param string $course  'BSc CSIT' or 'BCA'
 * @return array
 */
function getAllBooksForSearch($conn, $course = 'BSc CSIT') {
    $books = [];
    $stmt  = mysqli_prepare($conn,
        "SELECT * FROM books
         WHERE course=? OR course='Both'
         ORDER BY semester ASC, title ASC");
    mysqli_stmt_bind_param($stmt, 's', $course);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $books[] = $row;
    }
    return $books;
}
?>