<?php
/**
 * User Profile Component
 * 
 * @param array $profile User profile data (username, email, created_at)
 * @param array $userBooks Books associated with the user (borrowed and saved)
 * @param string $searchUrl Optional - URL for the browse books link (default: '/search')
 */

// Set default values for parameters
$profile = $profile ?? [];
$userBooks = $userBooks ?? ['borrowed' => [], 'saved' => []];
$searchUrl = $searchUrl ?? '/search';

// Parse MongoDB date format - handles both string format and MongoDB UTCDateTime object
function parseMongoDate($dateValue) {
    if (is_object($dateValue) && method_exists($dateValue, 'toDateTime')) {
        // Handle MongoDB UTCDateTime object
        return $dateValue->toDateTime()->format('F j, Y');
    } elseif (is_string($dateValue)) {
        // Handle ISO string format "2025-04-26T00:40:57.019+00:00"
        $date = new DateTime($dateValue);
        return $date->format('F j, Y');
    }
    return 'N/A';
}

// Make sure session data is available as fallback
$username = htmlspecialchars($profile['username'] ?? $_SESSION['username'] ?? 'User');
$email = htmlspecialchars($profile['email'] ?? $_SESSION['email'] ?? '');
$memberSince = isset($profile['createdAt']) ? parseMongoDate($profile['createdAt']) : 'N/A';
$firstLetter = substr($username, 0, 1);
?>

<div class="container my-5">
    <!-- Profile Header -->
    <div class="profile-header shadow-sm p-4 mb-4 bg-light rounded">
        <div class="row align-items-center">
            <div class="col-md-3 text-center">
                <div class="profile-avatar">
                    <?= $firstLetter ?>
                </div>
            </div>
            <div class="col-md-9">
                <h1 class="mb-3"><?= $username ?></h1>
                <p class="text-muted mb-2">
                    <i class="fas fa-envelope me-2"></i><?= $email ?>
                </p>
                <p class="text-muted">
                    <i class="fas fa-clock me-2"></i>Member since: <?= $memberSince ?>
                </p>
            </div>
        </div>
    </div>
    
    <!-- Books Tabs -->
    <ul class="nav nav-pills mb-4" id="booksTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="saved-tab" data-bs-toggle="pill" data-bs-target="#saved" type="button">
                <i class="fas fa-bookmark me-2"></i>Saved Books
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="borrowed-tab" data-bs-toggle="pill" data-bs-target="#borrowed" type="button">
                <i class="fas fa-book me-2"></i>Borrowed Books
            </button>
        </li>
    </ul>
    
    <div class="tab-content" id="booksTabContent">
        <!-- Saved Books Tab -->
        <div class="tab-pane fade show active" id="saved" role="tabpanel" aria-labelledby="saved-tab">
            <?php if (!empty($userBooks['saved'])): ?>
                <div class="row g-4">
                    <?php foreach($userBooks['saved'] as $book): ?>
                        <div class="col-md-4 col-lg-3">
                            <div class="card book-card h-100">
                                <img src="<?= htmlspecialchars($book['bookPdf'] ?? '/assets/uploads/thumbnails/placeholder-book.jpg') ?>" 
                                     class="card-img-top book-bookPdf" 
                                     alt="<?= htmlspecialchars($book['title']) ?>"
                                     onerror="this.src='/assets/uploads/thumbnails/placeholder-book.jpg'">
                                <div class="card-body">
                                    <h5 class="card-title"><?= htmlspecialchars($book['title']) ?></h5>
                                    <p class="card-text text-muted"><?= htmlspecialchars($book['author']) ?></p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <?php if (isset($book['copies']) && $book['copies'] > 0): ?>
                                            <span class="badge bg-success">Available</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Unavailable</span>
                                        <?php endif; ?>
                                        <a href="/book/<?= $book['id'] ?>" class="btn btn-sm btn-outline-primary">Details</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5" id="no-saved-books-message">
                    <i class="fas fa-bookmark fa-3x text-muted mb-3"></i>
                    <h4>No saved books</h4>
                    <p class="text-muted">You haven't saved any books to your list yet.</p>
                    <a href="<?= htmlspecialchars($searchUrl) ?>" class="btn btn-primary">Browse Books</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Borrowed Books Tab -->
        <div class="tab-pane fade" id="borrowed" role="tabpanel" aria-labelledby="borrowed-tab">
            <?php if (!empty($userBooks['borrowed'])): ?>
                <div class="row g-4">
                    <?php foreach($userBooks['borrowed'] as $book): ?>
                        <div class="col-md-4 col-lg-3">
                            <div class="card book-card h-100">
                                <img src="<?= htmlspecialchars($book['bookPdf'] ?? '/assets/uploads/thumbnails/placeholder-book.jpg') ?>" 
                                     class="card-img-top book-bookPdf" 
                                     alt="<?= htmlspecialchars($book['title']) ?>"
                                     onerror="this.src='/assets/uploads/thumbnails/placeholder-book.jpg'">
                                <div class="card-body">
                                    <h5 class="card-title"><?= htmlspecialchars($book['title']) ?></h5>
                                    <p class="card-text text-muted"><?= htmlspecialchars($book['author']) ?></p>
                                    <div class="d-flex justify-content-between">
                                        <small class="text-danger">
                                            <i class="fas fa-calendar-check me-1"></i>Due: <?= date('M j, Y', strtotime($book['due_date'])) ?>
                                        </small>
                                        <a href="/book/<?= $book['id'] ?>" class="btn btn-sm btn-outline-primary">Details</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-book fa-3x text-muted mb-3"></i>
                    <h4>No books borrowed</h4>
                    <p class="text-muted">You haven't borrowed any books yet.</p>
                    <a href="<?= htmlspecialchars($searchUrl) ?>" class="btn btn-primary">Browse Books</a>
                </div>
            <?php endif; ?>
        </div>
        
      
    </div>
</div>

<!-- Add Axios if not already included -->
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const savedTab = document.getElementById('saved-tab');
    const savedBooksContainer = document.getElementById('saved');
    loadSavedBooks();
    
    savedTab.addEventListener('click', function () {
        loadSavedBooks();
    });

    function loadSavedBooks() {
        savedBooksContainer.innerHTML = `
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-3">Loading your saved books...</p>
            </div>
        `;
        
        // Get saved books using Axios
        axios.get('/api/v1/saved-books', {
            headers: {
                'Authorization': 'Bearer ' + (localStorage.getItem('authToken') || sessionStorage.getItem('authToken') || '')
            }
        })
        .then(function(response) {
            books = response.data.data;
            if (books) {
                
                if (books.length > 0) {
                    try {
                        // Render the books using BookCard structure
                        let booksHTML = '<div class="row">';
                        
                        books.forEach(function(book, index) {
                            console.log(`Processing book ${index}:`, book);
                            const cardHTML = generateBookCardHTML(book);
                            console.log(`Card HTML for book ${index}:`, cardHTML.substring(0, 100) + '...');
                            booksHTML += cardHTML;
                        });
                        
                        booksHTML += '</div>';
                        console.log('Final HTML length:', booksHTML.length);
                        
                        // Set innerHTML and verify it worked
                        savedBooksContainer.innerHTML = booksHTML;
                        console.log('DOM updated with new content');
                        
                        // Attach event listeners to the new buttons
                        attachRemoveButtonListeners();
                    } catch (err) {
                        console.error('Error rendering books:', err);
                        showNoSavedBooksMessage();
                    }
                } else {
                    console.log('No saved books found');
                    showNoSavedBooksMessage();
                }
            } else {
                console.log('No saved books found in response');
                showNoSavedBooksMessage();
            }
        })
        .catch(function(error) {
            console.error('Error loading saved books:', error);
            savedBooksContainer.innerHTML = `
                <div class="text-center py-5">
                    <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                    <h4>Error loading books</h4>
                    <p class="text-muted">${error.response?.data?.message || 'There was a problem loading your saved books.'}</p>
                    <button class="btn btn-primary" onclick="loadSavedBooks()">Retry</button>
                </div>
            `;
        });
    }
    
    // Function to generate HTML for a book card based on BookCard.php structure
    function generateBookCardHTML(book) {
        const title = book.title || 'Unknown Title';
        const author = book.author || 'Unknown Author';
        const bookId = book._id.$oid || '';
        const thumbnailPath = book.thumbnail_path || '/assets/uploads/thumbnails/placeholder-book.jpg';
        const year = book.year || '';
        const categories = book.categories || [];
        const averageRating = book.average_rating || 0;
        
        // Build categories HTML
        let categoriesHTML = '';
        if (categories.length > 0) {
            categoriesHTML = '<div class="mb-2">';
            const maxCategoriesToShow = 2;
            const categoriesToShow = categories.slice(0, maxCategoriesToShow);
            
            categoriesToShow.forEach(function(category) {
                categoriesHTML += `<span class="badge bg-secondary me-1">${category}</span>`;
            });
            
            if (categories.length > maxCategoriesToShow) {
                categoriesHTML += `<span class="badge bg-secondary">+${categories.length - maxCategoriesToShow} more</span>`;
            }
            
            categoriesHTML += '</div>';
        }
        
        // Build rating HTML
        let ratingHTML = '';
        if (averageRating > 0) {
            ratingHTML = '<div class="mb-2">';
            const roundedRating = Math.round(averageRating);
            
            for (let i = 1; i <= 5; i++) {
                const starClass = i <= roundedRating ? 'text-warning' : 'text-muted';
                ratingHTML += `<i class="fas fa-star ${starClass}"></i>`;
            }
            
            ratingHTML += `<span class="small text-muted">(${averageRating})</span></div>`;
        }
        
        // Return the complete book card HTML
        return `
            <div class="col-md-4 mb-4">
                <div class="card h-100 shadow-sm position-relative">
                    <!-- Book thumbnail -->
                    <img src="${thumbnailPath}" 
                         class="card-img-top" style="height: 200px; object-fit: cover;"
                         alt="${title}"
                         onerror="this.src='/assets/uploads/thumbnails/placeholder-book.jpg'">
                    <div class="card-body d-flex flex-column">
                        <!-- Title -->
                        <h5 class="card-title text-truncate" title="${title}">
                            ${title}
                        </h5>
                        <!-- Author -->
                        <p class="card-text text-muted small text-truncate">
                            By ${author}
                        </p>
                        <!-- Year if available -->
                        ${year ? `<p class="card-text small mb-2">${year}</p>` : ''}
                        <!-- Categories -->
                        ${categoriesHTML}
                        <!-- Average Rating -->
                        ${ratingHTML}
                        <!-- Action Buttons -->
                        <div class="mt-auto d-flex justify-content-between">
                            <a href="/book/${bookId}" class="btn btn-sm btn-primary">
                                View Details
                            </a>
                            <button class="btn btn-sm btn-outline-danger remove-saved-book" data-book-id="${bookId}">
                                <i class="fas fa-times"></i> Remove
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    function showNoSavedBooksMessage() {
        savedBooksContainer.innerHTML = `
            <div class="text-center py-5">
                <i class="fas fa-bookmark fa-3x text-muted mb-3"></i>
                <h4>No saved books</h4>
                <p class="text-muted">You haven't saved any books to your list yet.</p>
                <a href="<?= htmlspecialchars($searchUrl) ?>" class="btn btn-primary">Browse Books</a>
            </div>
        `;
    }

    function attachRemoveButtonListeners() {
        const removeButtons = document.querySelectorAll('.remove-saved-book');

        removeButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                const bookId = this.getAttribute('data-book-id');
                if (confirm('Remove this book from your saved list?')) {
                    removeBook(bookId, this);
                }
            });
        });
    }

    function removeBook(bookId, buttonElement) {
        const originalButtonText = buttonElement.innerHTML;
        buttonElement.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Removing...';
        buttonElement.disabled = true;

        axios.post('/api/v1/remove-book', { book_id: bookId }, {
            headers: {
                'Authorization': 'Bearer ' + (localStorage.getItem('authToken') || sessionStorage.getItem('authToken') || '')
            }
        })
        .then(function (response) {
            console.log('Remove book response:', response.data);
            
            // Fix the success check to match the actual API response format
            if (response.data.success || response.data.status === 'success') { 
                const bookCard = buttonElement.closest('.col-md-4');
                bookCard.style.transition = 'all 0.3s ease';
                bookCard.style.opacity = '0';
                
                setTimeout(() => {
                    bookCard.remove();
                    
                    // Check for any remaining book cards using the correct selector
                    const remainingBooks = document.querySelectorAll('#saved .card');
                    console.log(`${remainingBooks.length} books remaining after removal`);
                    
                    if (remainingBooks.length === 0) {
                        showNoSavedBooksMessage();
                    }
                }, 300);
            } else {
                buttonElement.innerHTML = originalButtonText;
                buttonElement.disabled = false;
                alert('Failed to remove book: ' + (response.data.message || 'Unknown error'));
            }
        })
        .catch(function (error) {
            console.error('Error removing book:', error);
            buttonElement.innerHTML = originalButtonText;
            buttonElement.disabled = false;
            alert('An error occurred while removing the book. Please try again.');
        });
    }

    // Initial event listener attachment (for pre-loaded books)
    attachRemoveButtonListeners();
});
</script>
