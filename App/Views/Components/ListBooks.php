<?php
// ListBooks.php Component

// Include necessary files
require_once __DIR__ . '/BookCard.php'; // Include the BookCard component
class ListBooks{
    public static function renderListBooks($books) {
        ?>
        <div class="list-books container mt-4">
            <h1 class="mb-4">All Books</h1>
            <div class="row">
                <?php if (empty($books)): ?>
                    <p class="text-center">No books available at the moment.</p>
                <?php else: ?>
                    <?php foreach ($books as $book): ?>
                        <?= BookCard::render($book); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
?>
