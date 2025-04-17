<?php
/**
 * Navbar Component
 * 
 * @param string $activePage Optional - current active page for nav highlight
 * @param string $searchUrl Optional - search form submission URL (default: '/search_results')
 */

// Default values
$activePage = $activePage ?? '';
$searchUrl = $searchUrl ?? '/search_results';
?>

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
                    <a class="nav-link <?= $activePage === 'home' ? 'active' : '' ?>" href="/">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $activePage === 'books' ? 'active' : '' ?>" href="/view-books">Books</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $activePage === 'add' ? 'active' : '' ?>" href="/add-book">Add Book</a>
                </li>
            </ul>

            <form class="d-flex me-3" id="searchForm" action="<?= htmlspecialchars($searchUrl) ?>" method="GET">
                <div class="input-group">
                    <input type="search" name="q" id="bookToSearch" class="form-control" 
                           placeholder="Search titles..." aria-label="Search">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>

            <?php if (!empty($_SESSION['user_id'])): ?>
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
