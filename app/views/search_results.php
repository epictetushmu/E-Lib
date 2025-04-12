<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Results</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="./styles/searchResults.css"> 
</head>
<body>
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
                    <li class="nav-item"><a class="nav-link active" href="/search">Search</a></li>
                </ul>
                <form class="d-flex" id="searchForm">
                    <div class="input-group">
                        <input type="search" id="quickSearch" class="form-control" placeholder="Search titles..." value="<?= htmlspecialchars($searchQuery ?? '') ?>">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row mb-4">
            <div class="col">
                <h2>Search Results <?= !empty($searchQuery) ? 'for "' . htmlspecialchars($searchQuery) . '"' : '' ?></h2>
            </div>
        </div>

        <!-- Advanced Search Form -->
        <div class="card mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">Advanced Search</h5>
            </div>
            <div class="card-body">
                <form id="advancedSearchForm">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="title" class="form-label">Title</label>
                            <input type="text" class="form-control" id="title" name="title" value="<?= htmlspecialchars($filters['title'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="author" class="form-label">Author</label>
                            <input type="text" class="form-control" id="author" name="author" value="<?= htmlspecialchars($filters['author'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="category" class="form-label">Category</label>
                            <select class="form-select" id="category" name="category">
                                <option value="">All Categories</option>
                                <?php foreach ($categories ?? [] as $category): ?>
                                    <option value="<?= htmlspecialchars($category['id']) ?>" <?= ($filters['category'] ?? '') == $category['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($category['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 text-center">
                            <button type="submit" class="btn btn-primary">Search</button>
                            <button type="reset" class="btn btn-outline-secondary">Reset</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Search Results -->
        <div class="row" id="resultsContainer">
            <?php if (!empty($books)): ?>
                <?php foreach ($books as $book): ?>
                    <div class="col-md-4 col-lg-3 mb-4">
                        <div class="card h-100 shadow-sm">
                            <img src="<?= htmlspecialchars($book['cover'] ?: '/assets/images/placeholder-book.jpg') ?>" 
                                class="card-img-top book-cover" 
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

        <!-- Pagination -->
        <?php if (!empty($totalPages) && $totalPages > 1): ?>
        <nav aria-label="Search results pages" class="my-4">
            <ul class="pagination justify-content-center">
                <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= $paginationBaseUrl ?>&page=<?= $currentPage - 1 ?>">Previous</a>
                </li>
                
                <?php for($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $i == $currentPage ? 'active' : '' ?>">
                        <a class="page-link" href="<?= $paginationBaseUrl ?>&page=<?= $i ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                
                <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= $paginationBaseUrl ?>&page=<?= $currentPage + 1 ?>">Next</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="py-4 bg-dark text-white mt-5">
        <div class="container">
            <div class="text-center">
                <p class="mb-0">&copy; 2025 Epictetus Library. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('searchForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const query = document.getElementById('quickSearch').value.trim();
            if (query) {
                window.location.href = `/search?q=${encodeURIComponent(query)}`;
            }
        });

        document.getElementById('advancedSearchForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const title = document.getElementById('title').value.trim();
            const author = document.getElementById('author').value.trim();
            const category = document.getElementById('category').value;
            
            let url = '/search?';
            let params = [];
            
            if (title) params.push(`title=${encodeURIComponent(title)}`);
            if (author) params.push(`author=${encodeURIComponent(author)}`);
            if (category) params.push(`category=${encodeURIComponent(category)}`);
            
            window.location.href = url + params.join('&');
        });
    </script>
</body>
</html>