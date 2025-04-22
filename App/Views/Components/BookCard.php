<div class="col-md-4 mb-4">
    <div class="card h-100 shadow-sm">
        <!-- Book thumbnail -->
        <img src="<?= htmlspecialchars($book['thumbnail_path'] ?? '/assets/images/placeholder-book.jpg') ?>" 
             class="card-img-top book-thumbnail" 
             alt="<?= htmlspecialchars($book['title'] ?? 'Book cover') ?>">
        
        <div class="card-body d-flex flex-column">
            <!-- Title -->
            <h5 class="card-title"><?= htmlspecialchars($book['title'] ?? 'Unknown Title') ?></h5>
            
            <!-- Author -->
            <p class="card-text text-muted">
                By <?= htmlspecialchars($book['author'] ?? 'Unknown Author') ?>
            </p>
            
            <!-- Action Button -->
            <a href="/book/<?= htmlspecialchars($book['_id'] ?? '') ?>" class="btn btn-primary mt-auto">
                View Details
            </a>
        </div>
    </div>
</div>