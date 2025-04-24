<main class="flex-grow-1 py-5">
    <div class="container">
        <h2 class="text-center mb-5 fw-bold">All Books</h2>

        <div class="row g-4">
            <?php
            if (!empty($books)) {
                foreach ($books as $book) {
                    // Include the BookCard component for each book
                    include __DIR__ . '/BookCard.php';
                }
            } else {
                echo '<div class="col-12 text-center"><p class="text-muted">No books available.</p></div>';
            }
            ?>
        </div>
    </div>
</main>