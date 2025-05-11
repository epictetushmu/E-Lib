<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Books | Epictetus Library</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/styles/add_book.css">
    <link rel="stylesheet" href="/styles/home.css"> 
</head>
<body class="d-flex flex-column min-vh-100">
    <?php
        include 'Partials/Header.php';
    ?>
    
    <div class="container mt-5" id="books-container">
        <!-- Loading indicator -->
        <div class="text-center py-5" id="loading-indicator">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading books...</p>
        </div>
        
        <!-- No books message - will be shown when necessary -->
        <div class="text-center py-5" id="no-books" style="display: none;">
            <i class="fas fa-book fa-5x mb-3 text-muted"></i>
            <p class="text-muted">No books available.</p>
        </div>
        
        <!-- Books list will be rendered here -->
        <div id="books-list" style="display: none;"></div>
    </div>
    
    <?php
        include 'Partials/Footer.php';
    ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', fetchBooks);
        
        async function fetchBooks() {
            try {
                const token = localStorage.getItem('authToken') || sessionStorage.getItem('authToken') || '';
                const response = await axios.get('/api/v1/books/list', {
                    headers: {
                        'Authorization': `Bearer ${token}`
                    }
                });
                
                if (response.data && response.data.status === 'success' && response.data.data) {
                    renderBooks(response.data.data);
                } else {
                    showNoBooks();
                }
            } catch (error) {
                console.error('Error fetching books:', error);
                showNoBooks();
            }
        }
        
        function renderBooks(books) {
            const booksContainer = document.getElementById('books-list');
            
            if (!books || books.length === 0) {
                showNoBooks();
                return;
            }
            
            // Generate HTML for the book cards
            let html = `
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4">
            `;
            
            books.forEach(book => {
                const categoryBadges = book.categories && book.categories.length > 0 
                    ? book.categories.map(category => 
                        `<span class="badge bg-info me-1 mb-1">${category}</span>`
                      ).join('') 
                    : '<span class="badge bg-secondary">Uncategorized</span>';
                
                html += `
                    <div class="col animate__animated animate__fadeIn">
                        <div class="card h-100 book-card">
                            <div class="card-img-top book-cover-container">
                                <img src="${book.thumbnail || '/assets/uploads/thumbnails/placeholder-book.jpg'}" 
                                     class="card-img-top book-cover" 
                                     alt="${book.title} cover"
                                     onerror="this.src='/assets/uploads/thumbnails/placeholder-book.jpg'">
                            </div>
                            <div class="card-body">
                                <h5 class="card-title">${book.title || 'Untitled'}</h5>
                                <p class="card-text">
                                    <small class="text-muted">By ${book.author || 'Unknown author'}</small>
                                </p>
                                <div class="mb-2 category-badges">
                                    ${categoryBadges}
                                </div>
                                <p class="card-text book-description">
                                    ${book.description ? book.description.substring(0, 100) + '...' : 'No description available'}
                                </p>
                            </div>
                            <div class="card-footer bg-transparent">
                                <a href="/book/${book._id.$oid}" class="btn btn-sm btn-primary">Details</a>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += `</div>`;
            
            // Update the DOM
            booksContainer.innerHTML = html;
            document.getElementById('loading-indicator').style.display = 'none';
            booksContainer.style.display = 'block';
        }
        
        function showNoBooks() {
            document.getElementById('loading-indicator').style.display = 'none';
            document.getElementById('no-books').style.display = 'block';
        }
    </script>
</body>
</html>