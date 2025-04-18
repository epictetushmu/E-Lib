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

// Make sure session data is available as fallback
$username = htmlspecialchars($profile['username'] ?? $_SESSION['username'] ?? 'User');
$email = htmlspecialchars($profile['email'] ?? $_SESSION['email'] ?? '');
$memberSince = isset($profile['created_at']) ? date('F j, Y', strtotime($profile['created_at'])) : 'N/A';
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
                <button class="btn btn-sm btn-outline-secondary" id="editProfileBtn">
                    <i class="fas fa-edit me-2"></i>Edit Profile
                </button>
            </div>
        </div>
    </div>
    
    <!-- Books Tabs -->
    <ul class="nav nav-pills mb-4" id="booksTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="borrowed-tab" data-bs-toggle="pill" data-bs-target="#borrowed" type="button">
                <i class="fas fa-book me-2"></i>Borrowed Books
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="saved-tab" data-bs-toggle="pill" data-bs-target="#saved" type="button">
                <i class="fas fa-bookmark me-2"></i>Saved Books
            </button>
        </li>
    </ul>
    
    <div class="tab-content" id="booksTabContent">
        <!-- Borrowed Books Tab -->
        <div class="tab-pane fade show active" id="borrowed" role="tabpanel" aria-labelledby="borrowed-tab">
            <?php if (!empty($userBooks['borrowed'])): ?>
                <div class="row g-4">
                    <?php foreach($userBooks['borrowed'] as $book): ?>
                        <div class="col-md-4 col-lg-3">
                            <div class="card book-card h-100">
                                <img src="<?= htmlspecialchars($book['bookPdf'] ?? '/assets/images/placeholder-book.jpg') ?>" 
                                     class="card-img-top book-bookPdf" 
                                     alt="<?= htmlspecialchars($book['title']) ?>"
                                     onerror="this.src='/assets/images/placeholder-book.jpg'">
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
        
        <!-- Saved Books Tab -->
        <div class="tab-pane fade" id="saved" role="tabpanel" aria-labelledby="saved-tab">
            <?php if (!empty($userBooks['saved'])): ?>
                <div class="row g-4">
                    <?php foreach($userBooks['saved'] as $book): ?>
                        <div class="col-md-4 col-lg-3">
                            <div class="card book-card h-100">
                                <img src="<?= htmlspecialchars($book['bookPdf'] ?? '/assets/images/placeholder-book.jpg') ?>" 
                                     class="card-img-top book-bookPdf" 
                                     alt="<?= htmlspecialchars($book['title']) ?>"
                                     onerror="this.src='/assets/images/placeholder-book.jpg'">
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
                <div class="text-center py-5">
                    <i class="fas fa-bookmark fa-3x text-muted mb-3"></i>
                    <h4>No saved books</h4>
                    <p class="text-muted">You haven't saved any books to your list yet.</p>
                    <a href="<?= htmlspecialchars($searchUrl) ?>" class="btn btn-primary">Browse Books</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>