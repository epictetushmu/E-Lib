<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($book['title'] ?? 'Book Details') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../public/styles/book_details.css">
</head>
<body class="d-flex flex-column min-vh-100">
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="/">
                <i class="fas fa-book-open me-2"></i>Epictetus Library
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="/">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="/add-book">Add Book</a></li>
                    <li class="nav-item"><a class="nav-link" href="/search">Search</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <?php if (!empty($book)): ?>
            <div class="row">
                <div class="col-md-4">
                    <img src="<?= htmlspecialchars($book['cover'] ?? '/assets/images/placeholder-book.jpg') ?>" 
                         alt="<?= htmlspecialchars($book['title']) ?> cover" 
                         class="img-fluid book-cover"
                         onerror="this.src='/assets/images/placeholder-book.jpg'">
                    
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <div class="mt-3">
                            <button id="borrowBtn" class="btn btn-success w-100 <?= (!isset($book['copies']) || $book['copies'] <= 0) ? 'disabled' : '' ?>">
                                <i class="fas fa-book me-2"></i>Borrow Book
                            </button>
                        </div>
                        <div class="mt-2">
                            <button id="saveBtn" class="btn btn-outline-primary w-100">
                                <i class="fas fa-bookmark me-2"></i>Save to List
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-8 book-details">
                    <h1 class="fw-bold"><?= htmlspecialchars($book['title'] ?? 'Untitled') ?></h1>
                    
                    <?php if (!empty($book['genre'])): ?>
                        <div class="mb-2">
                            <span class="badge bg-info"><?= htmlspecialchars($book['genre']) ?></span>
                            <?php if (isset($book['copies']) && $book['copies'] > 0): ?>
                                <span class="badge bg-success">Available (<?= $book['copies'] ?> copies)</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Unavailable</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <p class="text-muted fst-italic">"<?= htmlspecialchars($book['description'] ?? 'No description available') ?>"</p>
                    
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
                    
                    <a href="/" class="btn btn-secondary mt-3">
                        <i class="fas fa-arrow-left me-2"></i>Back to Home
                    </a>
                </div>
            </div>

            <!-- Reviews Section -->
            <div class="mt-5">
                <h3 class="mb-4">Reviews</h3>
                
                <?php if (isset($_SESSION['user_id'])): ?>
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

                <div id="reviewsContainer">
                    <?php if (!empty($reviews)): ?>
                        <?php foreach ($reviews as $review): ?>
                            <div class="card review-card mb-3">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="card-subtitle mb-0"><?= htmlspecialchars($review['username']) ?></h6>
                                        <div class="text-warning">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fa<?= ($i <= $review['rating']) ? 's' : 'r' ?> fa-star"></i>
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
            <div class="text-center py-5">
                <i class="fas fa-book fa-5x mb-3 text-muted"></i>
                <h3>Book not found</h3>
                <p class="text-muted">The book you are looking for does not exist or has been removed.</p>
                <a href="/" class="btn btn-primary mt-3">Return to Home</a>
            </div>
        <?php endif; ?>
    </div>
    
    <footer class="mt-auto py-3 bg-light text-center">
        <p class="mb-0">&copy; 2025 Epictetus Library. All rights reserved.</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', () => {
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
            
            // Review submission
            const reviewForm = document.getElementById('reviewForm');
            if (reviewForm) {
                reviewForm.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    
                    const bookId = document.getElementById('bookId').value;
                    const rating = document.getElementById('rating').value;
                    const comment = document.getElementById('comment').value;
                    
                    if (rating === '0') {
                        alert('Please select a rating');
                        return;
                    }
                    
                    try {
                        const response = await axios.post('/api/review', {
                            book_id: bookId,
                            rating: rating,
                            comment: comment
                        });
                        
                        if (response.data.status === 'success') {
                            alert('Review submitted successfully');
                            location.reload();
                        } else {
                            alert(response.data.message || 'Failed to submit review');
                        }
                    } catch (error) {
                        console.error('Error submitting review:', error);
                        alert('An error occurred while submitting your review');
                    }
                });
            }
            
            // Borrow book functionality
            const borrowBtn = document.getElementById('borrowBtn');
            if (borrowBtn) {
                borrowBtn.addEventListener('click', async () => {
                    const bookId = document.getElementById('bookId').value;
                    
                    try {
                        const response = await axios.post('/api/borrow', {
                            book_id: bookId
                        });
                        
                        if (response.data.status === 'success') {
                            alert('Book borrowed successfully');
                            borrowBtn.classList.add('disabled');
                            borrowBtn.textContent = 'Borrowed';
                        } else {
                            alert(response.data.message || 'Failed to borrow book');
                        }
                    } catch (error) {
                        console.error('Error borrowing book:', error);
                        alert('An error occurred while borrowing the book');
                    }
                });
            }
            
            // Save to list functionality
            const saveBtn = document.getElementById('saveBtn');
            if (saveBtn) {
                saveBtn.addEventListener('click', async () => {
                    const bookId = document.getElementById('bookId').value;
                    
                    try {
                        const response = await axios.post('/api/save-book', {
                            book_id: bookId
                        });
                        
                        if (response.data.status === 'success') {
                            alert('Book saved to your list');
                            saveBtn.textContent = 'Saved to List';
                            saveBtn.disabled = true;
                        } else {
                            alert(response.data.message || 'Failed to save book');
                        }
                    } catch (error) {
                        console.error('Error saving book:', error);
                        alert('An error occurred while saving the book');
                    }
                });
            }
        });
    </script>
</body>
</html>
