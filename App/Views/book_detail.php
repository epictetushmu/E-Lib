<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/styles/book_details.css">
    <link rel="stylesheet" href="/styles/home.css"> 
</head>
<body class="d-flex flex-column min-vh-100">
    <?php 
        include 'Partials/Header.php';
    ?> 
    <div class="container mt-5">
        <div id="book-container">
            <!-- Loading indicator -->
            <div class="text-center py-5" id="loading-indicator">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Loading book details...</p>
            </div>
            
            <!-- Book details will be rendered here -->
            <div id="book-details" style="display: none;"></div>
            <?php
                // Include the book reviews section
                include 'Components/BookReview.php';
            ?>
        </div>
    </div>     
    <?php
        include 'Partials/Footer.php';
    ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script>
        // Get book ID from URL
        const pathParts = window.location.pathname.split('/');
        const bookId = pathParts[pathParts.length - 1];
        // Function to render book details
        function renderBookDetails(book) {
            const bookDetails = document.getElementById('book-details');
            
            // Create HTML structure for the book details
            let categoriesHtml = '';
            if (book.categories && book.categories.length > 0) {
                categoriesHtml = book.categories.map(category => 
                    `<span class="badge bg-info me-1 mb-1">${category}</span>`
                ).join('');
            } else {
                categoriesHtml = '<span class="badge bg-secondary">Uncategorized</span>';
            }
            
            // Format ISBN if present
            let isbnDisplay = '';
            if (book.isbn) {
                isbnDisplay = `
                    <p><strong>ISBN:</strong> <span class="formatted-isbn">${book.isbn}</span></p>
                `;
            }
            
            // Check if user is logged in
            const isLoggedIn = localStorage.getItem('authToken') || sessionStorage.getItem('authToken');
            
            // Action buttons based on login state
            let actionButtons = `
                <button id="shareBtn" class="btn btn-outline-secondary action-btn">
                    <i class="fas fa-share-alt me-1"></i> Share
                </button>
            `;
            
            if (isLoggedIn) {
                // Add save, preview, and download buttons for logged-in users
                actionButtons = `
                    <button id="saveBtn" class="btn btn-outline-primary me-2 action-btn">
                        <i class="fas fa-bookmark me-1"></i> Save to Reading List
                    </button>
                    
                    <button id="previewBtn" class="btn btn-info me-2 action-btn">
                        <i class="fas fa-eye me-1"></i> Online Preview
                    </button>
                    
                    ${(book.downloadable !== false) ? 
                      `<button id="downloadBtn" class="btn btn-success me-2 action-btn">
                          <i class="fas fa-download me-1"></i> Download PDF
                       </button>` :
                      `<button class="btn btn-outline-secondary me-2 action-btn" disabled title="This book is not available for download">
                          <i class="fas fa-ban me-1"></i> Download Disabled
                       </button>`
                    }
                    
                    ${actionButtons}
                `;
            }
            
            // Create book details HTML
            const html = `
                <div class="row">
                    <!-- Book Cover + Actions Column -->
                    <div class="col-md-4">
                        <img src="${book.thumbnail_path || '/assets/uploads/thumbnails/placeholder-book.jpg'}"
                             alt="${book.title} cover"
                             class="img-fluid rounded shadow-sm"
                             onerror="this.src='/assets/uploads/thumbnails/placeholder-book.jpg'">
                        
                        <!-- Action Buttons -->
                        <div class="action-buttons mt-4">
                            ${actionButtons}
                        </div>
                        <input type="hidden" id="bookId" value="${book._id.$oid}">
                    </div>
                    
                    <!-- Book Info Column -->
                    <div class="col-md-8">
                        <h1 class="fw-bold">${book.title || 'Untitled'}</h1>
                        
                        <!-- Categories -->
                        <div class="mb-3">
                            ${categoriesHtml}
                        </div>
                        
                        <!-- Description -->
                        <p class="text-muted fst-italic">"${book.description || 'No description available'}"</p>
                        
                        <!-- Metadata -->
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <p><strong>Author:</strong> ${book.author || 'Unknown'}</p>
                                ${book.published_date ? `<p><strong>Published:</strong> ${book.published_date}</p>` : ''}
                                ${isbnDisplay}
                            </div>
                            <div class="col-md-6">
                                ${book.language ? `<p><strong>Language:</strong> ${book.language}</p>` : ''}
                                ${book.pages ? `<p><strong>Pages:</strong> ${book.pages}</p>` : ''}
                                ${book.publisher ? `<p><strong>Publisher:</strong> ${book.publisher}</p>` : ''}
                            </div>
                        </div>
                        
                        <a href="/" class="btn btn-secondary mt-4">
                            <i class="fas fa-arrow-left me-2"></i>Back to Home
                        </a>
                    </div>
                </div>
            `;
            
            bookDetails.innerHTML = html;
            document.title = book.title || 'Book Details';
            
            // Show book details and hide loading indicator
            document.getElementById('loading-indicator').style.display = 'none';
            bookDetails.style.display = 'block';
            
            // Add event listeners to buttons
            setupEventListeners(book);
        }
        
        // Function to format ISBN numbers
        function formatISBN(isbn) {
            if (!isbn) return isbn;
            
            // Remove any existing non-alphanumeric characters
            isbn = isbn.replace(/[^0-9X]/gi, '');
            
            if (isbn.length === 10) {
                // Format ISBN-10: 1-234-56789-X
                return isbn.substring(0, 1) + '-' + 
                       isbn.substring(1, 4) + '-' + 
                       isbn.substring(4, 9) + '-' + 
                       isbn.substring(9, 10);
            } else if (isbn.length === 13) {
                // Format ISBN-13: 978-1-234-56789-7
                return isbn.substring(0, 3) + '-' + 
                       isbn.substring(3, 4) + '-' + 
                       isbn.substring(4, 7) + '-' + 
                       isbn.substring(7, 12) + '-' + 
                       isbn.substring(12, 13);
            }
            
            return isbn; // Return unformatted if not correct length
        }
        
        // Function to set up event listeners for buttons
        function setupEventListeners(book) {
            // Format ISBN numbers
            const formatIsbnElements = document.querySelectorAll('.formatted-isbn');
            formatIsbnElements.forEach(element => {
                const isbn = element.textContent.trim();
                element.textContent = formatISBN(isbn);
            });
            
            // Share button
            const shareBtn = document.getElementById('shareBtn');
            if (shareBtn) {
                shareBtn.addEventListener('click', function() {
                    // Get the current URL
                    const bookUrl = window.location.href;
                    
                    // Copy to clipboard
                    navigator.clipboard.writeText(bookUrl)
                        .then(() => {
                            // Change button text/appearance temporarily
                            const originalContent = shareBtn.innerHTML;
                            shareBtn.innerHTML = '<i class="fas fa-check me-2"></i>Copied to clipboard!';
                            shareBtn.classList.add('btn-success');
                            shareBtn.classList.remove('btn-outline-secondary');
                            
                            // Reset button after 2 seconds
                            setTimeout(() => {
                                shareBtn.innerHTML = originalContent;
                                shareBtn.classList.remove('btn-success');
                                shareBtn.classList.add('btn-outline-secondary');
                            }, 2000);
                        })
                        .catch(err => {
                            console.error('Failed to copy URL: ', err);
                            alert('Could not copy link. Please try again.');
                        });
                });
            }
            
            // Save button
            const saveBtn = document.getElementById('saveBtn');
            if (saveBtn) {
                saveBtn.addEventListener('click', async () => {
                    const bookId = document.getElementById('bookId').value;
                    
                    try {
                        const response = await axios.post('/api/v1/save-book', {
                            book_id: bookId
                        });
                        
                        if (response.data.status === 'success') {
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
            
            // Download button
            const downloadBtn = document.getElementById('downloadBtn');
            if (downloadBtn) {
                downloadBtn.addEventListener('click', async function() {
                    const token = localStorage.getItem('authToken') || sessionStorage.getItem('authToken');
                    if (!token) {
                        alert('You must be logged in to download this file.');
                        return;
                    }
                    
                    try {
                        const response = await axios.get(`/api/v1/books/${bookId}/download`, {
                            headers: {
                                'Authorization': `Bearer ${token}`
                            },
                            responseType: 'blob' // Important for downloading files
                        });
                        
                        // Create a link element to download the file
                        const url = window.URL.createObjectURL(new Blob([response.data]));
                        const link = document.createElement('a');
                        link.href = url;
                        link.setAttribute('download', `${book.title}.pdf`);
                        document.body.appendChild(link);
                        link.click();
                        link.remove();
                    } catch (error) {
                        console.error('Error downloading the file:', error);
                        alert('Failed to download the file. Please try again.');
                    }
                });
            }
            
            // Preview button
            const previewBtn = document.getElementById('previewBtn');
            if (previewBtn) {
                previewBtn.addEventListener('click', function() {
                    window.location.href = `/read/${bookId}`; // Navigate in the same tab
                });
            }
        }
        
        // Fetch book data from the API
        async function fetchBookData() {
            const token = localStorage.getItem('authToken') || sessionStorage.getItem('authToken') || '';
            
            try {
                const response = await axios.get(`/api/v1/books/${bookId}`, {
                    headers: {
                        'Authorization': `Bearer ${token}`
                    }
                });
                
                if (response.data && response.data.status === 'success' && response.data.data) {
                    renderBookDetails(response.data.data);
                } else {
                    throw new Error('Book data not found');
                }
            } catch (error) {
                console.error('Error fetching book data:', error);
                document.getElementById('loading-indicator').style.display = 'none';
                document.getElementById('book-not-found').style.display = 'block';
            }
        }
        
        // Fetch book data when the page loads
        document.addEventListener('DOMContentLoaded', fetchBookData);
    </script>
</body>
</html>
