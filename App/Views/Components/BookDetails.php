<div class="container mt-5">
    <?php if (!empty($book)): ?>
        <div class="row">
            <!-- Book Cover + Actions Column -->
            <div class="col-md-4">
                <img src="<?= htmlspecialchars($book['thumbnail_path'] ?? '/assets/uploads/thumbnails/placeholder-book.jpg') ?>"
                     alt="<?= htmlspecialchars($book['title']) ?> cover"
                     class="img-fluid rounded shadow-sm"
                     onerror="this.src='/assets/images/placeholder-book.jpg'">

                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="mt-2">
                        <button id="saveBtn" class="btn btn-outline-primary w-100">
                            <i class="fas fa-bookmark me-2"></i>Save to List
                        </button>
                    </div>
               
                    <!-- Download Button -->
                    <?php if (!empty($book['pdf_path'])): ?>
                        <div class="mt-2">
                            <a href="/api/v1/download/<?= htmlspecialchars($book['_id']) ?>" class="btn btn-outline-success w-100" download>
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
                <?php else: ?>
                    <!-- Login to access -->
                    <div class="mt-2">
                        <a href="/login?redirect=<?= urlencode('/book/' . $book['_id']) ?>" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-lock me-2"></i>Login for Full Access
                        </a>
                    </div>
                <?php endif; ?>
                <!-- Share Button -->
                <div class="mt-2">
                    <button id="shareBtn" class="btn btn-outline-secondary w-100">
                        <i class="fas fa-share-alt me-2"></i>Share
                    </button>
                </div>
            </div>
            
            <!-- Book Info Column - MOVED OUT OF THE FIRST COLUMN -->
            <div class="col-md-8">
                <h1 class="fw-bold"><?= htmlspecialchars($book['title'] ?? 'Untitled') ?></h1>

                <!-- Categories -->
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

        <!-- Review Section - NOW OUTSIDE THE MAIN ROW -->
        <div class="row mt-5">
            <div class="col-12">
                <h3 class="mb-4">Reviews</h3>

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

                <?php if (isset($_SESSION['user_id'])): ?>
                    <!-- Add Review Form -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Add Your Review</h5>
                            <form id="reviewForm">
                                <input type="hidden" id="bookId" value="<?= htmlspecialchars($book['_id']) ?>">
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const shareBtn = document.getElementById('shareBtn');
    
    if (shareBtn) {
        shareBtn.addEventListener('click', function() {
            // Get the current URL
            const bookUrl = window.location.href;
            
            // Copy to clipboard
            navigator.clipboard.writeText(bookUrl)
                .then(() => {
                    // Change button text/appearance temporarily
                    const originalContent = shareBtn.innerHTML;
                    shareBtn.innerHTML = '<i class="fas fa-check me-2"></i>Copied to clipboard!';
                    shareBtn.classList.add('btn-success');
                    shareBtn.classList.remove('btn-outline-secondary');
                    
                    // Reset button after 2 seconds
                    setTimeout(() => {
                        shareBtn.innerHTML = originalContent;
                        shareBtn.classList.remove('btn-success');
                        shareBtn.classList.add('btn-outline-secondary');
                    }, 2000);
                })
                .catch(err => {
                    console.error('Failed to copy URL: ', err);
                    alert('Could not copy link. Please try again.');
                });
        });
    }

       // Setup star rating system
       const stars = document.querySelectorAll('#ratingStars i');
            const ratingInput = document.getElementById('rating');
            
            stars.forEach(star => {
                star.addEventListener('click', () => {
                    const rating = parseInt(star.getAttribute('data-rating'));
                    ratingInput.value = rating;
                    
                    // Update stars display
                    stars.forEach((s, index) => {
                        if (index < rating) {
                            s.classList.remove('far');
                            s.classList.add('fas');
                        } else {
                            s.classList.remove('fas');
                            s.classList.add('far');
                        }
                    });
                });
                
                star.addEventListener('mouseover', () => {
                    const rating = parseInt(star.getAttribute('data-rating'));
                    
                    // Temp highlight stars
                    stars.forEach((s, index) => {
                        if (index < rating) {
                            s.classList.add('text-warning');
                        } else {
                            s.classList.remove('text-warning');
                        }
                    });
                });
                
                star.addEventListener('mouseout', () => {
                    stars.forEach(s => s.classList.remove('text-warning'));
                });
            });
               

    // Review form submission handler
    const reviewForm = document.getElementById('reviewForm');
    if (reviewForm) {
        reviewForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Get form data
            const bookId = document.getElementById('bookId').value;
            const rating = document.getElementById('rating').value;
            const comment = document.getElementById('comment').value;
            
            // Validate form
            if (rating === '0') {
                alert('Please select a rating');
                return;
            }
            
            if (!comment.trim()) {
                alert('Please enter a comment');
                return;
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';
            
            // Send data to server
            axios.post(`/api/v1/reviews`, {
                book_id: bookId,    
                rating: parseInt(rating),
                comment: comment
            })
            .then(response => {
                if (response.data.status === 'success') {
                    // Reset form
                    document.getElementById('rating').value = '0';
                    document.getElementById('comment').value = '';
                    
                    // Reset stars display
                    const stars = document.querySelectorAll('#ratingStars i');
                    stars.forEach(s => {
                        s.classList.remove('fas', 'text-warning');
                        s.classList.add('far');
                    });
                    
                    // Show success message
                    alert('Your review has been submitted successfully!');
                    
                    // Refresh reviews without page reload
                    fetchReviews(bookId);
                } else {
                    alert(response.data.message || 'Error submitting review');
                }
            })
            .catch(error => {
                console.error('Review submission error:', error);
                alert('Error submitting review. Please try again.');
            })
            .finally(() => {
                // Reset button
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            });
        });
    }

    // Function to fetch and update reviews
    const bookId = document.getElementById('bookId')?.value;
    if (bookId) {
        fetchReviews(bookId);
    }
    function fetchReviews(bookId) {
        axios.get(`/api/v1/reviews/${bookId}`)
            .then(response => {
                if (response.data.status === 'success') {
                    const reviews = response.data.data || [];
                    const container = document.getElementById('reviewsContainer');
                    
                    if (reviews.length === 0) {
                        container.innerHTML = '<div class="alert alert-info">No reviews yet. Be the first to review this book!</div>';
                    } else {
                        container.innerHTML = '';
                        reviews.forEach(review => {
                            const reviewCard = document.createElement('div');
                            reviewCard.classList.add('card', 'review-card', 'mb-3');
                            reviewCard.innerHTML = `
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="card-subtitle mb-0">${review.username}</h6>
                                        <div class="text-warning">
                                            ${[...Array(5)].map((_, i) => `<i class="fa${i < review.rating ? 's' : 'r'} fa-star"></i>`).join('')}
                                        </div>
                                    </div>
                                    <p class="card-text">${review.comment}</p>
                                    <div class="text-muted small">${new Date(review.created_at).toLocaleDateString()}</div>
                                </div>
                            `;
                            container.appendChild(reviewCard);
                        });
                    }
                } else {
                    alert('Error fetching reviews');
                }
            })
            .catch(error => {
                console.error('Error fetching reviews:', error);
                alert('Error fetching reviews. Please try again.');
            });
    }
});
</script>
