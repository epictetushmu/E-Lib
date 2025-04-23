<div class="container mt-5">
    <?php if (!empty($book)): ?>
        <div class="row">
            <!-- Book Cover + Actions -->
            <div class="col-md-4">
                <img src="<?= htmlspecialchars($book['thumbnail_path'] ?? '/assets/uploads/thumbnails/placeholder-book.jpg') ?>"
                     alt="<?= htmlspecialchars($book['title']) ?> cover"
                     class="img-fluid rounded shadow-sm"
                     onerror="this.src='/assets/images/placeholder-book.jpg'">

                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="mt-3">
                        <button id="borrowBtn" class="btn btn-success w-100 <?= ($book['copies'] ?? 0) <= 0 ? 'disabled' : '' ?>">
                            <i class="fas fa-book me-2"></i>Borrow Book
                        </button>
                    </div>
                    <div class="mt-2">
                        <button id="saveBtn" class="btn btn-outline-primary w-100">
                            <i class="fas fa-bookmark me-2"></i>Save to List
                        </button>
                    </div>
                <?php endif; ?>
                
                <!-- Download Button -->
                <?php if (!empty($book['pdf_path'])): ?>
                <div class="mt-2">
                    <a href="<?= htmlspecialchars($book['pdf_path']) ?>" class="btn btn-outline-success w-100" download>
                        <i class="fas fa-file-download me-2"></i>Download PDF
                    </a>
                </div>
                <?php endif; ?>
                <!-- Read Online Button -->
                <div class="mt-2">
                    <a href="/read/<?= htmlspecialchars($book['_id']) ?>" class="btn btn-outline-info w-100">
                        <i class="fas fa-book-open me-2"></i>Read Online
                    </a>
                </div>  
            </div>

            <!-- Book Info -->
            <div class="col-md-8">
                <h1 class="fw-bold"><?= htmlspecialchars($book['title'] ?? 'Untitled') ?></h1>

                <!-- Genre & Availability -->
                <div class="mb-3">
                    <?php if (!empty($book['categories'])): ?>
                        <?php 
                        // Handle MongoDB BSON arrays properly
                        $categories = $book['categories'];
                        if ($categories instanceof \MongoDB\Model\BSONArray) {
                            // Convert BSON array to PHP array
                            $categories = $categories->getArrayCopy();
                            foreach ($categories as $category): ?>
                                <span class="badge bg-info me-1 mb-1"><?= htmlspecialchars((string)$category) ?></span>
                            <?php endforeach;
                        } elseif (is_array($categories)) {
                            // Regular PHP array
                            foreach ($categories as $category): ?>
                                <span class="badge bg-info me-1 mb-1"><?= htmlspecialchars((string)$category) ?></span>
                            <?php endforeach;
                        } else {
                            // Single category as string
                            ?>
                            <span class="badge bg-info"><?= htmlspecialchars((string)$categories) ?></span>
                        <?php } ?>
                    <?php else: ?>
                        <span class="badge bg-secondary">Uncategorized</span>
                    <?php endif; ?>
                </div>

                <!-- Description -->
                <p class="text-muted fst-italic">"<?= htmlspecialchars($book['description'] ?? 'No description available') ?>"</p>

                <!-- Metadata -->
                <div class="row mt-4">
                    <div class="col-md-6">
                        <p><strong>Author:</strong> <?= htmlspecialchars($book['author'] ?? 'Unknown') ?></p>
                        <?php if (!empty($book['published_date'])): ?>
                            <p><strong>Published:</strong> <?= htmlspecialchars($book['published_date']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($book['isbn'])): ?>
                            <p><strong>ISBN:</strong> <?= htmlspecialchars($book['isbn']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <?php if (!empty($book['language'])): ?>
                            <p><strong>Language:</strong> <?= htmlspecialchars($book['language']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($book['pages'])): ?>
                            <p><strong>Pages:</strong> <?= htmlspecialchars($book['pages']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($book['publisher'])): ?>
                            <p><strong>Publisher:</strong> <?= htmlspecialchars($book['publisher']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <a href="/" class="btn btn-secondary mt-4">
                    <i class="fas fa-arrow-left me-2"></i>Back to Home
                </a>
            </div>
        </div>

        <!-- Review Section -->
        <div class="mt-5">
            <h3 class="mb-4">Reviews</h3>

            <?php if (isset($_SESSION['user_id'])): ?>
                <!-- Add Review Form -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Add Your Review</h5>
                        <form id="reviewForm">
                            <input type="hidden" id="bookId" value="<?= htmlspecialchars($book['id']) ?>">
                            <div class="mb-3">
                                <label class="form-label">Rating</label>
                                <div class="star-rating" id="ratingStars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="far fa-star" data-rating="<?= $i ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <input type="hidden" id="rating" value="0">
                            </div>
                            <div class="mb-3">
                                <label for="comment" class="form-label">Comment</label>
                                <textarea class="form-control" id="comment" rows="3" required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Submit Review</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Display Reviews -->
            <div id="reviewsContainer">
                <?php if (!empty($reviews)): ?>
                    <?php foreach ($reviews as $review): ?>
                        <div class="card review-card mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="card-subtitle mb-0"><?= htmlspecialchars($review['username']) ?></h6>
                                    <div class="text-warning">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fa<?= $i <= $review['rating'] ? 's' : 'r' ?> fa-star"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <p class="card-text"><?= htmlspecialchars($review['comment']) ?></p>
                                <div class="text-muted small">
                                    <?= htmlspecialchars(date("F j, Y", strtotime($review['created_at']))) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="alert alert-info">
                        No reviews yet. Be the first to review this book!
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <!-- Book Not Found -->
        <div class="text-center py-5">
            <i class="fas fa-book fa-5x mb-3 text-muted"></i>
            <h3>Book not found</h3>
            <p class="text-muted">The book you are looking for does not exist or has been removed.</p>
            <a href="/" class="btn btn-primary mt-3">Return to Home</a>
        </div>
    <?php endif; ?>
</div>
