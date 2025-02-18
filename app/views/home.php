<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Epictetus Library - Home of Knowledge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .hero-section {
            background: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7)), 
                        url('library-bg.jpg') center/cover;
            color: white;
            min-height: 60vh;
            display: flex;
            align-items: center;
        }
        
        .book-card {
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .book-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .book-cover {
            height: 300px;
            object-fit: cover;
            object-position: top;
        }
        
        footer {
            background: #2c3e50;
        }
        
        .loading-spinner {
            display: none;
            width: 3rem;
            height: 3rem;
        }
    </style>
</head>
<body class="d-flex flex-column min-vh-100">
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="/E-Lib/">
                <i class="fas fa-book-open me-2"></i>Epictetus Library
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="/E-Lib/">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/E-Lib/add-book">Add Book</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/E-Lib/search_results">Advanced Search</a>
                    </li>
                </ul>
                <form class="d-flex" id="searchForm">
                    <div class="input-group">
                        <input type="search" id="bookToSearch" class="form-control" 
                               placeholder="Search titles..." aria-label="Search">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
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

    <!-- Featured Books -->
    <main class="flex-grow-1 py-5" id="featured">
        <div class="container">
            <h2 class="text-center mb-5 fw-bold">Featured Collection</h2>
            
            <!-- Loading Spinner -->
            <div class="text-center my-5">
                <div class="loading-spinner spinner-border text-primary"></div>
            </div>

            <!-- Books Grid -->
            <div class="row g-4" id="booksGrid">
                <!-- Dynamic content loaded from API -->
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="py-4 mt-auto">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <h5 class="text-warning">Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-light text-decoration-none">About Us</a></li>
                        <li><a href="#" class="text-light text-decoration-none">Events</a></li>
                        <li><a href="#" class="text-light text-decoration-none">Membership</a></li>
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
    <script>
        // Sample API integration
        document.addEventListener('DOMContentLoaded', () => {
            const loader = document.querySelector('.loading-spinner');
            const booksGrid = document.getElementById('booksGrid');
            
            async function loadFeaturedBooks() {
                try {
                    loader.style.display = 'block';
                    // Replace with your actual API endpoint
                    const response = await axios.get('/E-Lib/api/featured-books');
                    booksGrid.innerHTML = response.data.map(book => `
                        <div class="col-md-4 col-lg-3">
                            <div class="card book-card h-100">
                                <img src="${book.cover || 'placeholder-book.jpg'}" 
                                     class="book-cover card-img-top" 
                                     alt="${book.title} cover">
                                <div class="card-body">
                                    <h5 class="card-title">${book.title}</h5>
                                    <p class="card-text text-muted">${book.author}</p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="badge bg-info">${book.genre}</span>
                                        <a href="/E-Lib/book/${book.id}" class="btn btn-sm btn-primary">
                                            Details <i class="fas fa-arrow-right ms-1"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `).join('');
                } catch (error) {
                    booksGrid.innerHTML = `
                        <div class="col-12 text-center text-danger">
                            <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
                            <p>Failed to load books. Please try again later.</p>
                        </div>
                    `;
                } finally {
                    loader.style.display = 'none';
                }
            }

            // Search functionality
            document.getElementById('searchForm').addEventListener('submit', async (e) => {
                e.preventDefault();
                const query = document.getElementById('bookToSearch').value;
                // Implement search logic
                window.location.href = `/E-Lib/search_results?q=${encodeURIComponent(query)}`;
            });

            loadFeaturedBooks();
        });
    </script>
</body>
</html>