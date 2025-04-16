<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Epictetus Library - Home of Knowledge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/styles/home.css"> 
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
                    <li class="nav-item">
                        <a class="nav-link active" href="/">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/view-books">Books</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/add-book">Add Book</a>
                    </li>
                </ul>
                <form class="d-flex me-3" id="searchForm" action="/search_results" method="GET">
                    <div class="input-group">
                        <input type="search" name="q" id="bookToSearch" class="form-control" 
                               placeholder="Search titles..." aria-label="Search">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
                
                <?php if (isset($_SESSION['user_id'])): ?>
                <!-- User is logged in -->
                <div class="dropdown">
                    <button class="btn btn-outline-light dropdown-toggle d-flex align-items-center" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="user-avatar me-2"><?= substr($_SESSION['username'] ?? 'U', 0, 1) ?></div>
                        <span class="d-none d-md-inline"><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <li><a class="dropdown-item" href="/profile"><i class="fas fa-user me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="/book"><i class="fas fa-bookmark me-2"></i>My Books</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="/api/v1/logout"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
                <?php else: ?>
                <!-- User is not logged in -->
                <div class="d-flex">
                    <a href="/login" class="btn btn-outline-light me-2">Login</a>
                    <a href="/signup" class="btn btn-primary">Sign Up</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container text-center">
            <h1 class="display-4 fw-bold mb-4">Welcome to Epictetus Library</h1>
            <p class="lead mb-4">Explore our collection of over 50,000 books and digital resources</p>
            <a href="#featured" class="btn btn-light btn-lg px-5">
                Browse Collection <i class="fas fa-arrow-down ms-2"></i>
            </a>
        </div>
    </section>

    <!--Error Section-->
    <div class="container text-center">
        <div class="error-container">
            <div class="error-code">404</div>
            <p class="error-message">Oops! The page you are looking for does not exist.</p>
            <a href="/" class="btn btn-primary">
                <i class="fas fa-home"></i> Return to Home
            </a>
        </div>
    </div>

    <!-- Footer -->
    <footer class="py-4 mt-auto">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <h5 class="text-warning">Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="/" class="text-light text-decoration-none">Home</a></li>
                        <li><a href="/book" class="text-light text-decoration-none">Books</a></li>
                        <li><a href="/login" class="text-light text-decoration-none">Login</a></li>
                    </ul>
                </div>
                <div class="col-md-4 mb-3">
                    <h5 class="text-warning">Contact</h5>
                    <ul class="list-unstyled">
                        <li class="text-light">123 Knowledge Street</li>
                        <li class="text-light">info@epictetuslibrary.org</li>
                        <li class="text-light">(555) 123-4567</li>
                    </ul>
                </div>
                <div class="col-md-4 mb-3">
                    <h5 class="text-warning">Follow Us</h5>
                    <div class="d-flex gap-3">
                        <a href="#" class="text-light"><i class="fab fa-facebook fa-2x"></i></a>
                        <a href="#" class="text-light"><i class="fab fa-twitter fa-2x"></i></a>
                        <a href="#" class="text-light"><i class="fab fa-instagram fa-2x"></i></a>
                    </div>
                </div>
            </div>
            <div class="text-center mt-4 pt-3 border-top">
                <p class="mb-0">&copy; 2025 Epictetus Library. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
</body>
</html>