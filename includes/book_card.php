<?php
// ============================================================
// includes/book_card.php
// Reusable Book Card — included in home.php
// Variable: $book (array from books table)
// ============================================================
?>
<a href="<?php echo BASE_URL; ?>/user/book_detail.php?id=<?php echo $book['id']; ?>"
   class="book-card" style="text-decoration:none;">

    <?php if (!empty($book['image']) && file_exists(__DIR__ . '/../uploads/' . $book['image'])): ?>
        <img src="<?php echo BASE_URL; ?>/uploads/<?php echo sanitize($book['image']); ?>"
             class="book-cover" alt="<?php echo sanitize($book['title']); ?>">
    <?php else: ?>
        <div class="book-cover-placeholder">&#128218;</div>
    <?php endif; ?>

    <div class="book-info">
        <!-- Course badge -->
        <span style="font-size:.68rem;font-weight:700;
                     background:<?php echo $book['course']==='BCA' ? '#d1ecf1' : ($book['course']==='BSc CSIT' ? '#f8d7da' : '#fff3cd'); ?>;
                     color:<?php echo $book['course']==='BCA' ? '#0c5460' : ($book['course']==='BSc CSIT' ? '#721c24' : '#856404'); ?>;
                     padding:.15rem .5rem;border-radius:4px;
                     text-transform:uppercase;letter-spacing:.05em;">
            <?php echo sanitize($book['course']); ?>
        </span>

        <div class="book-title mt-1"><?php echo sanitize($book['title']); ?></div>
        <div class="book-author"><?php echo sanitize($book['author']); ?></div>
        <span class="book-genre"><?php echo sanitize($book['genre']); ?></span>

        <div class="book-availability mt-1
             <?php echo $book['available_copies'] > 0 ? 'available' : 'unavailable'; ?>">
            <?php echo $book['available_copies'] > 0
                ? '&#10003; ' . $book['available_copies'] . ' available'
                : '&#10007; Not available'; ?>
        </div>
    </div>
</a>