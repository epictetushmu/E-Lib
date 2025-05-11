<?php
/**
 * User Profile Component
 * This component now uses client-side API calls for data fetching
 * instead of relying on server-side variables passed from the controller
 */
?>

<div class="container my-5">
    <!-- Profile Header -->
    <div class="profile-header shadow-sm p-4 mb-4 bg-light rounded">
        <div class="row align-items-center">
            <div class="col-md-3 text-center">
                <div class="profile-avatar" id="profile-avatar">
                    <!-- Will be set via JavaScript -->
                </div>
            </div>
            <div class="col-md-9">
                <div class="d-flex align-items-center mb-3">
                    <!-- Username with edit functionality -->
                    <div id="username-display">
                        <h1 id="current-username" class="mb-0 me-2">Loading...</h1>
                        <button id="edit-username-btn" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                    </div>
                    
                    <!-- Username edit form (initially hidden) -->
                    <div id="username-edit-form" class="d-none">
                        <div class="input-group">
                            <input type="text" id="new-username" class="form-control" value="">
                            <button id="save-username-btn" class="btn btn-primary">Save</button>
                            <button id="cancel-edit-btn" class="btn btn-outline-secondary">Cancel</button>
                        </div>
                        <small id="username-error" class="text-danger d-none">Error message will appear here</small>
                    </div>
                </div>
                <p class="text-muted mb-2">
                    <i class="fas fa-envelope me-2"></i><span id="user-email">Loading...</span>
                </p>
                <p class="text-muted">
                    <i class="fas fa-clock me-2"></i>Member since: <span id="member-since">Loading...</span>
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
    </ul>
    
    <div class="tab-content" id="booksTabContent">
        <!-- Saved Books Tab -->
        <div class="tab-pane fade show active" id="saved" role="tabpanel" aria-labelledby="saved-tab">
            <!-- Content will be loaded via API -->
            <div class="text-center py-5" id="saved-books-loading">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-3">Loading your saved books...</p>
            </div>
        </div>    
    </div>
</div>

<!-- Add Axios if not already included -->
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Username edit functionality
    const usernameDisplay = document.getElementById('username-display');
    const usernameEditForm = document.getElementById('username-edit-form');
    const currentUsername = document.getElementById('current-username');
    const editUsernameBtn = document.getElementById('edit-username-btn');
    const newUsernameInput = document.getElementById('new-username');
    const saveUsernameBtn = document.getElementById('save-username-btn');
    const cancelEditBtn = document.getElementById('cancel-edit-btn');
    const usernameError = document.getElementById('username-error');
    const profileAvatar = document.querySelector('.profile-avatar');
    const userEmail = document.getElementById('user-email');
    const memberSince = document.getElementById('member-since');
    
    // Load user profile data
    loadUserProfile();
    
    function loadUserProfile() {
        const token = localStorage.getItem('authToken') || sessionStorage.getItem('authToken') || '';
        
        axios.get('/api/v1/user/profile', {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        })
        .then(function(response) {
            if (response.data && (response.data.success || response.data.status === 'success')) {
                const user = response.data.data;
                
                // Update profile information
                currentUsername.textContent = user.username || 'User';
                userEmail.textContent = user.email || '';
                
                // Format the date
                if (user.createdAt) {
                    const date = new Date(user.createdAt);
                    memberSince.textContent = date.toLocaleDateString('en-US', { 
                        year: 'numeric', 
                        month: 'long', 
                        day: 'numeric' 
                    });
                } else {
                    memberSince.textContent = 'N/A';
                }
                
                // Update avatar
                profileAvatar.textContent = (user.username ? user.username.charAt(0).toUpperCase() : 'U');
                
                // Update form field
                newUsernameInput.value = user.username || '';
                
                // Also update any session value if needed
                if (typeof updateSessionValue === 'function') {
                    updateSessionValue('username', user.username);
                    updateSessionValue('email', user.email);
                }
            } else {
                console.error('Failed to load profile:', response.data);
                showError('Failed to load profile information. Please try refreshing the page.');
            }
        })
        .catch(function(error) {
            console.error('Error loading profile:', error);
            showError('An error occurred while loading your profile information.');
            
            // Redirect if unauthorized
            if (error.response && (error.response.status === 401 || error.response.status === 403)) {
                window.location.href = '/?showLogin=1&redirect=' + encodeURIComponent(window.location.pathname);
            }
        });
    }
    
    function showError(message) {
        // You could add a more sophisticated error display here
        alert(message);
    }
    
    // Edit button click handler
    editUsernameBtn.addEventListener('click', function() {
        usernameDisplay.classList.add('d-none');
        usernameEditForm.classList.remove('d-none');
        newUsernameInput.focus();
        newUsernameInput.select();
    });
    
    // Cancel button click handler
    cancelEditBtn.addEventListener('click', function() {
        usernameDisplay.classList.remove('d-none');
        usernameEditForm.classList.add('d-none');
        newUsernameInput.value = currentUsername.textContent;
        usernameError.classList.add('d-none');
    });
    
    // Save button click handler
    saveUsernameBtn.addEventListener('click', updateUsername);
    
    // Enter key press handler for the input field
    newUsernameInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            updateUsername();
        }
    });
    
    function updateUsername() {
        const newUsername = newUsernameInput.value.trim();
        
        // Basic validation
        if (!newUsername) {
            showUsernameError('Username cannot be empty');
            return;
        }
        
        if (newUsername.length < 3) {
            showUsernameError('Username must be at least 3 characters');
            return;
        }
        
        if (newUsername === currentUsername.textContent) {
            // No change, just cancel the edit
            cancelEditBtn.click();
            return;
        }
        
        // Show loading state
        saveUsernameBtn.disabled = true;
        saveUsernameBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';
        
        // Send request to update username
        axios.post('/api/v1/update-profile', {
            username: newUsername
        }, {
            headers: {
                'Authorization': 'Bearer ' + (localStorage.getItem('authToken') || sessionStorage.getItem('authToken') || '')
            }
        })
        .then(function(response) {
            if (response.data.success || response.data.status === 'success') {
                // Update the UI
                currentUsername.textContent = newUsername;
                profileAvatar.textContent = newUsername.charAt(0);
                
                // Exit edit mode
                usernameDisplay.classList.remove('d-none');
                usernameEditForm.classList.add('d-none');
                
                // Show success notification
                showNotification('Username updated successfully', 'success');
                
                // Also update the session if needed
                if (typeof updateSessionValue === 'function') {
                    updateSessionValue('username', newUsername);
                }
            } else {
                showUsernameError(response.data.message || 'Failed to update username');
            }
        })
        .catch(function(error) {
            console.error('Error updating username:', error);
            showUsernameError(error.response?.data?.message || 'An error occurred. Please try again.');
        })
        .finally(function() {
            saveUsernameBtn.disabled = false;
            saveUsernameBtn.innerHTML = 'Save';
        });
    }
    
    function showUsernameError(message) {
        usernameError.textContent = message;
        usernameError.classList.remove('d-none');
    }
    
    function showNotification(message, type = 'info') {
        // Create toast notification container if it doesn't exist
        let toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
            document.body.appendChild(toastContainer);
        }
        
        // Create toast element
        const toastId = 'toast-' + Date.now();
        const bgClass = type === 'success' ? 'bg-success' : 'bg-danger';
        const toastHtml = `
            <div id="${toastId}" class="toast align-items-center ${bgClass} text-white border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        `;
        
        toastContainer.insertAdjacentHTML('beforeend', toastHtml);
        
        // Initialize and show the toast
        const toastElement = document.getElementById(toastId);
        const toast = new bootstrap.Toast(toastElement, { delay: 3000 });
        toast.show();
        
        // Remove the toast element after it's hidden
        toastElement.addEventListener('hidden.bs.toast', function () {
            toastElement.remove();
        });
    }
    
    // Existing saved books functionality
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
            if (error.response?.data?.status === "success") {
                showNoSavedBooksMessage();
            } else {
                console.error('Error loading saved books:', error);  
                savedBooksContainer.innerHTML = `
                    <div class="text-center py-5">
                        <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                        <h4>Error loading books</h4>
                        <p class="text-muted">${error.response?.data?.message || 'There was a problem loading your saved books.'}</p>
                        <button class="btn btn-primary" onclick="loadSavedBooks()">Retry</button>
                    </div>
                `;
            }
        });
    }
    
    // Function to generate HTML for a book card based on BookCard.php structure
    function generateBookCardHTML(book) {
        const title = book.title || 'Unknown Title';
        const author = book.author || 'Unknown Author';
        const bookId = book._id.$oid || book._id || '';
        const thumbnailPath = book.thumbnail || '/assets/uploads/thumbnails/placeholder-book.jpg';
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
                <a href="/search" class="btn btn-primary">Browse Books</a>
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

<!-- Add styles for the profile avatar and edit functionality -->
<style>
    .profile-avatar {
        width: 100px;
        height: 100px;
        background-color: #007bff;
        color: white;
        font-size: 3rem;
        font-weight: bold;
        display: flex;
        justify-content: center;
        align-items: center;
        border-radius: 50%;
        margin: 0 auto;
    }
    
    #username-display {
        display: flex;
        align-items: center;
    }
    
    #edit-username-btn {
        margin-left: 10px;
    }
    
    #username-edit-form .input-group {
        max-width: 400px;
    }
</style>
