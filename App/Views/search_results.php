<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Results</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/styles/searchResults.css"> 
    <link rel="stylesheet" href="/styles/home.css"> 
</head>
<body class="d-flex flex-column min-vh-100">
    <?php 
        include 'Partials/Header.php'; 
        include 'Components/SearchForm.php';
    ?>
    
    <div class="container mt-5">
        <div class="row mb-4">
            <div class="col">
                <h2 id="search-title">Search Results</h2>
            </div>
        </div>
        
        <!-- Loading indicator -->
        <div class="text-center py-5" id="loading-indicator">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Searching for books...</p>
        </div>
        
        <!-- Results container -->
        <div id="search-results" class="row"></div>
    </div>
    
    <?php include 'Partials/Footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Parse URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            const title = urlParams.get('title') || '';
            const author = urlParams.get('author') || '';
            const category = urlParams.get('category') || '';
            
            // Set search title
            if (title || author || category) {
                let searchTerms = [];
                if (title) searchTerms.push(`title: "${title}"`);
                if (author) searchTerms.push(`author: "${author}"`);
                if (category) searchTerms.push(`category: "${category}"`);
                
                document.getElementById('search-title').textContent = `Search Results for ${searchTerms.join(', ')}`;
            }
            
            // Only search if we have at least one parameter
            if (title || author || category) {
                searchBooks(title, author, category);
            } else {
                showNoSearchParams();
            }
        });
        
        async function searchBooks(title, author, category) {
            try {
                // Show loading indicator
                document.getElementById('loading-indicator').style.display = 'block';
                document.getElementById('search-results').innerHTML = '';
                
                // Build search query
                let params = new URLSearchParams();
                if (title) params.append('title', title);
                if (author) params.append('author', author);
                if (category) params.append('category', category);
                
                // Get authentication token if available
                const token = localStorage.getItem('authToken') || sessionStorage.getItem('authToken') || '';
                
                // Fetch search results
                const response = await axios.get(`/api/v1/search?${params.toString()}`, {
                    headers: {
                        'Authorization': `Bearer ${token}`
                    }
                });
                
                // Hide loading indicator
                document.getElementById('loading-indicator').style.display = 'none';
                
                // Process and display results
                if (response.data && response.data.status === 'success') {
                    const books = response.data.data || [];
                    
                    if (books.length > 0) {
                        displayResults(books);
                    } else {
                        showNoResults(title, author, category);
                    }
                } else {
                    throw new Error(response.data?.message || 'Failed to search books');
                }
            } catch (error) {
                console.error('Error searching books:', error);
                document.getElementById('loading-indicator').style.display = 'none';
                document.getElementById('search-results').innerHTML = `
                    <div class="col-12">
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Error fetching search results: ${error.message || 'Unknown error'}
                        </div>
                    </div>
                `;
            }
        }
        
        function displayResults(books) {
            const resultsContainer = document.getElementById('search-results');
            let html = '';
            
            books.forEach(book => {
                // Prepare book data
                const id = book._id;
                const title = book.title || 'Untitled';
                const author = book.author || 'Unknown author';
                const thumbnailPath = book.thumbnail || '/assets/uploads/thumbnails/placeholder-book.jpg';
                const description = book.description || 'No description available';
                const truncatedDescription = description.length > 150 ? description.substring(0, 150) + '...' : description;
                
                // Build categories HTML
                const categories = book.categories || [];
                let categoriesHtml = '';
                if (categories.length > 0) {
                    categoriesHtml = categories.map(category => 
                        `<span class="badge bg-secondary me-1 mb-1">${category}</span>`
                    ).join('');
                } else {
                    categoriesHtml = '<span class="badge bg-secondary">Uncategorized</span>';
                }
                
                // Calculate star rating if available
                const rating = book.average_rating || 0;
                let starsHtml = '<div class="book-rating mb-2">';
                for (let i = 1; i <= 5; i++) {
                    const starClass = i <= Math.round(rating) ? 'fas fa-star text-warning' : 'far fa-star text-muted';
                    starsHtml += `<i class="${starClass}"></i>`;
                }
                starsHtml += rating > 0 ? ` <small class="text-muted">(${rating.toFixed(1)})</small></div>` : '</div>';
                
                // Create book card
                html += `
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100 shadow-sm book-card">
                            <div class="row g-0">
                                <div class="col-md-4">
                                    <div class="book-cover-container">
                                        <img src="${thumbnailPath}" 
                                             class="book-cover img-fluid rounded-start" 
                                             alt="${title} cover"
                                             onerror="this.src='/assets/uploads/thumbnails/placeholder-book.jpg'">
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <div class="card-body d-flex flex-column h-100">
                                        <h5 class="card-title">${title}</h5>
                                        <p class="card-text text-muted mb-1">by ${author}</p>
                                        ${starsHtml}
                                        <div class="mb-2">${categoriesHtml}</div>
                                        <p class="card-text flex-grow-1">${truncatedDescription}</p>
                                        <div class="mt-auto">
                                            <a href="/book/${id}" class="btn btn-primary btn-sm">View Details</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            resultsContainer.innerHTML = html;
        }
        
        function showNoResults(title, author, category) {
            // Create a message showing what was searched for
            let searchTerms = [];
            if (title) searchTerms.push(`"${title}"`);
            if (author) searchTerms.push(`author: "${author}"`);
            if (category) searchTerms.push(`category: "${category}"`);
            
            const searchDescription = searchTerms.join(', ');
            
            document.getElementById('search-results').innerHTML = `
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        No books found matching ${searchDescription}.
                    </div>
                </div>
            `;
        }
        
        function showNoSearchParams() {
            document.getElementById('loading-indicator').style.display = 'none';
            document.getElementById('search-results').innerHTML = `
                <div class="col-12">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Please enter at least one search term.
                    </div>
                </div>
            `;
        }
    </script>
</body>
</html>