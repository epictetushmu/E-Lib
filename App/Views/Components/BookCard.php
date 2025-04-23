
<?php
class BookCard {
    public static function render($book) {
        ob_start();
        ?>
        <div class="col-md-4 mb-4">
            <div class="card h-100 shadow-sm">
                <!-- Book thumbnail -->
                <img src="<?= htmlspecialchars($book['thumbnail_path'] ?? '/assets/images/placeholder-book.jpg') ?>" 
                     class="card-img-top" style="height: 200px; object-fit: cover;"
                     alt="<?= htmlspecialchars($book['title'] ?? 'Book cover') ?>">
                
                <div class="card-body d-flex flex-column">
                    <!-- Title -->
                    <h5 class="card-title text-truncate" title="<?= htmlspecialchars($book['title'] ?? 'Unknown Title') ?>">
                        <?= htmlspecialchars($book['title'] ?? 'Unknown Title') ?>
                    </h5>
                    
                    <!-- Author -->
                    <p class="card-text text-muted small text-truncate">
                        By <?= htmlspecialchars($book['author'] ?? 'Unknown Author') ?>
                    </p>
                    
                    <!-- Year if available -->
                    <?php if (!empty($book['year'])): ?>
                        <p class="card-text small mb-2"><?= htmlspecialchars($book['year']) ?></p>
                    <?php endif; ?>
                    
                    <!-- Action Button -->
                    <a href="/book/<?= htmlspecialchars($book['_id'] ?? '') ?>" class="btn btn-sm btn-primary mt-auto">
                        View Details
                    </a>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}