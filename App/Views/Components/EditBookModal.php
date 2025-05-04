<!-- Edit Book Modal Component -->
<div class="modal fade" id="editBookModal" tabindex="-1" aria-labelledby="editBookModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="editBookModalLabel">Edit Book</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="editBookFormContainer">
                <!-- Form will be dynamically inserted here -->
            </div>
        </div>
    </div>
</div>

<script>
function editBook(bookId) {
    const authToken = localStorage.getItem('authToken') || sessionStorage.getItem('authToken');
    axios.get(`/api/v1/books/${bookId}`, {
        headers: { Authorization: 'Bearer ' + authToken }
    })
    .then(response => {
        const book = response.data.data;
        if (book) {
            const id = book._id?.$oid || book._id;
            const title = escapeHtml(book.title);
            const author = escapeHtml(book.author);
            const description = escapeHtml(book.description);
            const status = book.status || 'available';
            const categories = book.categories ? (Array.isArray(book.categories) ? book.categories.join(', ') : book.categories) : '';
            const featured = book.featured || false;
            const isbn = book.isbn || '';
            const downloadable = book.downloadable !== false; // Default to true if not set
            
            // Format ISBN for display with hyphens
            const formattedIsbn = formatISBN(isbn);

            const editForm = `
                <form id="editForm-${id}" onsubmit="submitEdit(event, '${id}')">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="title-${id}" class="form-label">Title</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-book"></i></span>
                                <input type="text" class="form-control" id="title-${id}" name="title" value="${title}" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="author-${id}" class="form-label">Author</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                <input type="text" class="form-control" id="author-${id}" name="author" value="${author}">
                            </div>
                        </div>
                        <div class="col-md-8">
                            <label for="description-${id}" class="form-label">Description</label>
                            <textarea class="form-control" id="description-${id}" name="description" rows="2">${description}</textarea>
                        </div>
                        <div class="col-md-4">
                            <label for="status-${id}" class="form-label">Status</label>
                            <select class="form-select" id="status-${id}" name="status">
                                <option value="draft" ${status === 'draft' ? 'selected' : ''}>Draft</option>
                                <option value="public" ${status === 'public' ? 'selected' : ''}>Public</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="featured-${id}" class="form-label">Featured</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="featured-${id}" name="featured" value="true" ${book.featured ? 'checked' : ''}>
                                <label class="form-check-label" for="featured-${id}">
                                    Mark as Featured
                                </label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label for="isbn-${id}" class="form-label">ISBN</label>
                            <input type="text" class="form-control" id="isbn-${id}" name="isbn" value="${formattedIsbn}" placeholder="Enter ISBN-10 or ISBN-13">
                            <div id="isbnHelp-${id}" class="form-text">
                                <span id="isbnValidation-${id}" class="text-${validateISBN(isbn).startsWith('Valid') ? 'success' : 'danger'}">${validateISBN(isbn)}</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Downloadable</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="downloadable" id="downloadableYes-${id}" value="true" ${downloadable ? 'checked' : ''}>
                                <label class="form-check-label" for="downloadableYes-${id}">Yes</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="downloadable" id="downloadableNo-${id}" value="false" ${!downloadable ? 'checked' : ''}>
                                <label class="form-check-label" for="downloadableNo-${id}">No</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label for="categories-${id}" class="form-label">Categories</label>
                            <input type="text" class="form-control" id="categories-${id}" name="categories"
                                value="${categories}" placeholder="Ex: Fiction, Fantasy, Adventure">
                            <div class="form-text">Separate categories with commas.</div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-4">
                        <button type="button" class="btn btn-outline-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Save Changes</button>
                    </div>
                </form>
            `;

            document.getElementById('editBookFormContainer').innerHTML = editForm;
            const editBookModal = new bootstrap.Modal(document.getElementById('editBookModal'));
            editBookModal.show();
            
            // Add ISBN validation event listeners after the form is created
            setupIsbnValidation(id, isbn);
        } else {
            Swal.fire('Error', 'Book not found.', 'error');
        }
    })
    .catch(error => {
        console.error('Error fetching book details:', error);
        Swal.fire('Error', 'Failed to fetch book details.', 'error');
    });
}

// Set up ISBN validation for the form
function setupIsbnValidation(id, initialIsbn) {
    const isbnField = document.getElementById(`isbn-${id}`);
    const isbnValidation = document.getElementById(`isbnValidation-${id}`);
    
    // Store raw ISBN value (without hyphens) to use for validation
    let rawIsbn = initialIsbn || "";
    
    isbnField.addEventListener("input", function(e) {
        const cursorPosition = this.selectionStart;
        
        // Get input value and clean it
        let value = this.value.replace(/[^0-9X]/gi, '');
        
        // Force uppercase X (for ISBN-10)
        value = value.replace(/x/g, 'X');
        
        // Update raw ISBN for validation
        rawIsbn = value;
        
        // Limit length
        if (value.length > 13) {
            value = value.slice(0, 13);
            rawIsbn = value;
        }
        
        // Format for display
        let formatted = formatISBN(value);
        
        // Only update the field value if it's different (to avoid cursor issues)
        if (this.value !== formatted) {
            this.value = formatted;
            
            // Try to maintain cursor position after formatting
            // This is approximate since formatting changes the string length
            let newPosition = cursorPosition;
            // Add adjustment for hyphen positions
            if (value.length >= 1 && cursorPosition > 1) newPosition++;
            if (value.length >= 4 && cursorPosition > 4) newPosition++;
            if (value.length >= 7 && cursorPosition > 7) newPosition++;
            if (value.length >= 12 && cursorPosition > 12) newPosition++;
            
            this.setSelectionRange(newPosition, newPosition);
        }
        
        // Validate ISBN
        let validationMessage = validateISBN(rawIsbn);
        isbnValidation.textContent = validationMessage;
        isbnValidation.className = validationMessage.startsWith('Valid') ? 'text-success' : 'text-danger';
        
        // Update hidden field with raw ISBN for submission
        document.getElementById(`isbn-${id}`).setAttribute('data-raw-isbn', rawIsbn);
    });
}

function submitEdit(event, bookId) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    const bookData = {};

    // Process form data
    formData.forEach((value, key) => {
        if (key === 'isbn') {
            // Get the raw ISBN without formatting
            const isbnField = document.getElementById(`isbn-${bookId}`);
            const rawIsbn = isbnField.getAttribute('data-raw-isbn') || value.replace(/[^0-9X]/gi, '');
            
            // Validate ISBN before submission
            const isbnError = validateISBN(rawIsbn);
            if (rawIsbn && !isbnError.startsWith('Valid')) {
                Swal.fire('Validation Error', 'Please enter a valid ISBN.', 'error');
                return;
            }
            
            bookData[key] = rawIsbn;
        } else if (key === 'categories') {
            bookData[key] = value.split(',').map(v => v.trim());
        } else {
            bookData[key] = value;
        }
    });

    const authToken = localStorage.getItem('authToken') || sessionStorage.getItem('authToken');
    axios.put(`/api/v1/books/${bookId}`, bookData, {
        headers: {
            'Authorization': 'Bearer ' + authToken,
            'Content-Type': 'application/json'
        }
    })
    .then(response => {
        if (response.data.status === 'success') {
            Swal.fire('Success', 'Book updated successfully.', 'success').then(() => {
                // Check if getBooks function exists (from parent component)
                if (typeof getBooks === 'function') {
                    getBooks();
                } else {
                    window.location.reload(); // Fallback to page reload if getBooks isn't available
                }
                const editBookModal = bootstrap.Modal.getInstance(document.getElementById('editBookModal'));
                editBookModal.hide();
            });
        } else {
            Swal.fire('Error', response.data.message || 'Update failed', 'error');
        }
    })
    .catch(err => {
        console.error('Error updating book:', err);
        Swal.fire('Error', 'An error occurred during update.', 'error');
    });
}

// Format ISBN with hyphens based on standard rules
function formatISBN(isbn) {
    if (!isbn) return '';
    
    if (isbn.length <= 1) return isbn;
    
    if (isbn.length <= 4) {
        // Partial ISBN-10/13: X-...
        return isbn.substring(0, 1) + 
                (isbn.length > 1 ? '-' + isbn.substring(1) : '');
    }
    
    if (isbn.length <= 7) {
        // Partial ISBN-10/13: X-XXX-...
        return isbn.substring(0, 1) + '-' + 
                isbn.substring(1, 4) + 
                (isbn.length > 4 ? '-' + isbn.substring(4) : '');
    }
    
    if (isbn.length <= 10) {
        if (isbn.length === 10) {
            // Complete ISBN-10: X-XXX-XXXXX-X
            return isbn.substring(0, 1) + '-' + 
                    isbn.substring(1, 4) + '-' + 
                    isbn.substring(4, 9) + '-' + 
                    isbn.substring(9, 10);
        } else {
            // Partial ISBN-10: X-XXX-XXXXX...
            return isbn.substring(0, 1) + '-' + 
                    isbn.substring(1, 4) + '-' + 
                    isbn.substring(4);
        }
    } else {
        if (isbn.length === 13) {
            // Complete ISBN-13: XXX-X-XXX-XXXXX-X
            return isbn.substring(0, 3) + '-' + 
                    isbn.substring(3, 4) + '-' + 
                    isbn.substring(4, 7) + '-' + 
                    isbn.substring(7, 12) + '-' + 
                    isbn.substring(12, 13);
        } else {
            // Partial ISBN-13: XXX-X-XXX-XXXXX...
            return isbn.substring(0, 3) + '-' + 
                    isbn.substring(3, 4) + '-' + 
                    isbn.substring(4, 7) + '-' + 
                    isbn.substring(7);
        }
    }
}

// Basic ISBN validation
function validateISBN(isbn) {
    if (!isbn) return '';
    
    // For incomplete ISBNs, just show a message about expected length
    if (isbn.length < 10) {
        return 'Continue entering digits (ISBN-10: 10 digits, ISBN-13: 13 digits)';
    }
    
    if (isbn.length !== 10 && isbn.length !== 13) {
        return 'ISBN must be 10 or 13 characters long';
    }
    
    if (isbn.length === 10) {
        // Only last character can be 'X' in ISBN-10
        if (/[X]/i.test(isbn.substring(0, 9))) {
            return 'Only the last character of ISBN-10 can be X';
        }
        
        // Validate ISBN-10 checksum
        let sum = 0;
        for (let i = 0; i < 9; i++) {
            sum += parseInt(isbn.charAt(i)) * (10 - i);
        }
        
        let checkDigit = 11 - (sum % 11);
        if (checkDigit === 11) checkDigit = 0;
        if (checkDigit === 10) checkDigit = 'X';
        
        const lastChar = isbn.charAt(9).toUpperCase();
        if (lastChar !== checkDigit.toString()) {
            return 'Invalid ISBN-10 checksum';
        }
        
        return 'Valid ISBN-10';
    }
    
    if (isbn.length === 13) {
        // ISBN-13 cannot have 'X'
        if (/[X]/i.test(isbn)) {
            return 'ISBN-13 cannot contain X';
        }
        
        // Validate ISBN-13 checksum
        let sum = 0;
        for (let i = 0; i < 12; i++) {
            sum += parseInt(isbn.charAt(i)) * (i % 2 === 0 ? 1 : 3);
        }
        
        let checkDigit = 10 - (sum % 10);
        if (checkDigit === 10) checkDigit = 0;
        
        if (parseInt(isbn.charAt(12)) !== checkDigit) {
            return 'Invalid ISBN-13 checksum';
        }
        
        return 'Valid ISBN-13';
    }
    
    return '';
}

// Helper function for HTML escaping
if (typeof escapeHtml !== 'function') {
    function escapeHtml(text) {
        if (!text) return '';
        return text.replace(/[&<>"']/g, m => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
        }[m]));
    }
}
</script>

<style>
/* Additional styling for the modal */
.modal-content {
    border-radius: 0.5rem;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
}

.modal-header {
    border-radius: 0.5rem 0.5rem 0 0;
}

/* Animation for the modal */
.modal.fade .modal-dialog {
    transition: transform 0.3s ease-out;
    transform: translateY(-50px);
}

.modal.show .modal-dialog {
    transform: translateY(0);
}
</style>
