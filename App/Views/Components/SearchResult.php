<div class="row" id="resultsContainer">
            <?php if (!empty($books)): ?>
                <?php foreach ($books as $book): ?>
                    <div class="col-md-4 col-lg-3 mb-4">
                        <div class="card h-100 shadow-sm">
                            <img src="<?= htmlspecialchars($book['thumbnail'] ?: '/assets/uploads/thumbnails/placeholder-book.jpg') ?>" 
                                class="card-img-top book-bookPdf" 
                                alt="<?= htmlspecialchars($book['title']) ?>">
                            <div class="card-body">
                                <h5 class="card-title"><?= htmlspecialchars($book['title']) ?></h5>
                                <p class="card-text text-muted mb-1"><?= htmlspecialchars($book['author']) ?></p>
                                <?php if (!empty($book['genre'])): ?>
                                    <span class="badge bg-info"><?= htmlspecialchars($book['genre']) ?></span>
                                <?php endif; ?>
                                <?php if (isset($book['copies']) && $book['copies'] > 0): ?>
                                    <span class="badge bg-success">Available</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Unavailable</span>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer bg-white pt-0 border-0 text-end">
                                <a href="/book/<?= htmlspecialchars($book['id']) ?>" class="btn btn-sm btn-primary">Details</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center py-5">
                    <i class="fas fa-search fa-3x mb-3 text-muted"></i>
                    <h4>No books found</h4>
                    <p class="text-muted">Try adjusting your search criteria</p>
                </div>
            <?php endif; ?>
        </div>
