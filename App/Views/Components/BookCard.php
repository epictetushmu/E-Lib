<div class="col-md-4 mb-4">
    <a href="/book/<?= htmlspecialchars($book['_id'] ?? '') ?>" class="text-decoration-none text-dark">
        <div class="card h-100 shadow-sm position-relative">
            <!-- Book thumbnail -->
            <img src="<?= htmlspecialchars($book['thumbnail'] ?? '/assets/uploads/thumbnails/placeholder-book.jpg') ?>" 
                 class="card-img-top" style="height: 200px; object-fit: cover;"
                 alt="<?= htmlspecialchars($book['title'] ?? 'Book cover') ?>"
                 onerror="this.src='/assets/uploads/thumbnails/placeholder-book.jpg'">
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
                <!-- Categories -->
                <?php if (!empty($book['categories'])): ?>
                    <div class="mb-2">
                        <?php
                        $categoriesArray = is_object($book['categories']) && method_exists($book['categories'], 'getArrayCopy')
                            ? $book['categories']->getArrayCopy()
                            : (array) $book['categories'];
                        $categoriesToShow = array_slice($categoriesArray, 0, 2);
                        foreach ($categoriesToShow as $category): ?>
                            <span class="badge bg-secondary me-1"><?= htmlspecialchars($category) ?></span>
                        <?php endforeach; ?>
                        <?php if (count($book['categories']) > 2): ?>
                            <span class="badge bg-secondary">+<?= count($book['categories']) - 2 ?> more</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <!-- Average Rating -->
                <?php if (!empty($book['average_rating'])): ?>
                    <div class="mb-2">
                        <?php 
                        $averageRating = round($book['average_rating']);
                        for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star <?= $i <= $averageRating ? 'text-warning' : 'text-muted' ?>"></i>
                        <?php endfor; ?>
                        <span class="small text-muted">(<?= htmlspecialchars($book['average_rating']) ?>)</span>
                    </div>
                <?php endif; ?>
                <!-- Action Button -->
                <a href="/book/<?= htmlspecialchars($book['_id'] ?? '') ?>" 
                   class="btn btn-sm btn-primary mt-auto">
                    View Details
                </a>
            </div>
        </div>
    </a>
</div>