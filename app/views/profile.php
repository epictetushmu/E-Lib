<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | Epictetus Library</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="./styles/profile.css"> 
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

    <div class="container my-5">
        <!-- Profile Header -->
        <div class="profile-header shadow-sm">
            <div class="row align-items-center">
                <div class="col-md-3 text-center">
                    <div class="profile-avatar">
                        <?= substr($_SESSION['username'] ?? 'U', 0, 1) ?>
                    </div>
                </div>
                <div class="col-md-9">
                    <h1 class="mb-3"><?= htmlspecialchars($profile['username'] ?? $_SESSION['username'] ?? 'User') ?></h1>
                    <p class="text-muted mb-2">
                        <i class="fas fa-envelope me-2"></i><?= htmlspecialchars($profile['email'] ?? $_SESSION['email'] ?? '') ?>
                    </p>
                    <p class="text-muted">
                        <i class="fas fa-clock me-2"></i>Member since: <?= isset($profile['created_at']) ? date('F j, Y', strtotime($profile['created_at'])) : 'N/A' ?>
                    </p>
                    <button class="btn btn-sm btn-outline-secondary">
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
                                    <img src="<?= htmlspecialchars($book['cover'] ?? '/assets/images/placeholder-book.jpg') ?>" 
                                         class="card-img-top book-cover" 
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
                        <a href="/search" class="btn btn-primary">Browse Books</a>
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
                                    <img src="<?= htmlspecialchars($book['cover'] ?? '/assets/images/placeholder-book.jpg') ?>" 
                                         class="card-img-top book-cover" 
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
                        <a href="/search" class="btn btn-primary">Browse Books</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="py-4 mt-auto bg-dark text-white">
        <div class="container">
            <div class="text-center">
                <p class="mb-0">&copy; 2025 Epictetus Library. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
