<div class="container mt-4">
    <h2 class="mb-4">Manage Books</h2>
    <div class="table-responsive">
        <table class="table table-striped align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Title</th>
                    <th>Author</th>
                    <th>Description</th>
                    <th>Status</th>
                    <th style="width: 180px;">Actions</th>
                </tr>
            </thead>
            <tbody id="booksTableBody">
                <!-- Dynamically injected rows -->
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', getBooks);

function escapeHtml(text) {
    if (!text) return '';
    return text.replace(/[&<>"']/g, m => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    }[m]));
}

function getBooks() {
    const authToken = localStorage.getItem('authToken') || sessionStorage.getItem('authToken');
    axios.get('/api/v1/books', {
        headers: { Authorization: 'Bearer ' + authToken }
    })
    .then(response => {
        const books = response.data.data || [];
        const tableBody = document.getElementById('booksTableBody');
        tableBody.innerHTML = '';

        books.forEach(book => {
            const id = book._id?.$oid || book._id;
            const title = escapeHtml(book.title);
            const author = escapeHtml(book.author);
            const description = escapeHtml(book.description);
            const status = book.status || 'available';
            const categories = book.categories ? (Array.isArray(book.categories) ? book.categories.join(', ') : book.categories) : '';

            // Regular row for displaying book info
            const displayRow = `
                <tr id="bookRow-${id}">
                    <td>${title}</td>
                    <td>${author}</td>
                    <td>${description}</td>
                    <td>${status.charAt(0).toUpperCase() + status.slice(1)}</td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-warning" onclick="editBook('${id}')">Edit</button>
                            <button class="btn btn-info" onclick="previewBook('${id}')">View PDF</button>
                            <button class="btn btn-danger" onclick="deleteBook('${id}')">Delete</button>
                        </div>
                    </td>
                </tr>
            `;
            
            // Enhanced edit form with better layout and labels
            const editRow = `
                <tr id="editRow-${id}" style="display: none;">
                    <td colspan="5">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">Edit Book: ${title}</h5>
                            </div>
                            <div class="card-body">
                                <form onsubmit="submitEdit(event, '${id}')">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="title-${id}" class="form-label">Title</label>
                                            <input type="text" class="form-control" id="title-${id}" name="title" value="${title}" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="author-${id}" class="form-label">Author</label>
                                            <input type="text" class="form-control" id="author-${id}" name="author" value="${author}" required>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
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
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="categories-${id}" class="form-label">Categories</label>
                                        <input type="text" class="form-control" id="categories-${id}" name="categories" 
                                            value="${categories}" placeholder="Enter categories separated by commas (e.g., Fiction, Fantasy, Adventure)">
                                        <div class="form-text text-muted">Separate multiple categories with commas</div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-end">
                                        <button type="button" class="btn btn-secondary me-2" onclick="cancelEdit('${id}')">Cancel</button>
                                        <button type="submit" class="btn btn-success">Save Changes</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </td>
                </tr>
            `;
            
            tableBody.insertAdjacentHTML('beforeend', displayRow + editRow);
        });
    })
    .catch(error => {
        console.error('Error fetching books:', error);
        alert('Failed to fetch books.');
    });
}

function editBook(bookId) {
    document.getElementById(`bookRow-${bookId}`).style.display = 'none';
    document.getElementById(`editRow-${bookId}`).style.display = 'table-row';
}

function cancelEdit(bookId) {
    document.getElementById(`editRow-${bookId}`).style.display = 'none';
    document.getElementById(`bookRow-${bookId}`).style.display = 'table-row';
}

function submitEdit(event, bookId) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    const bookData = {};

    formData.forEach((value, key) => {
        if (key === 'categories') {
            bookData[key] = value.split(',').map(item => item.trim()).filter(item => item);
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
            getBooks();
        } else {
            alert('Update failed: ' + (response.data.message || 'Unknown error'));
        }
    })
    .catch(err => {
        console.error('Error updating book:', err);
        alert('An error occurred while updating the book.');
    });
}

function deleteBook(bookId) {
    if (!confirm('Are you sure you want to delete this book?')) return;

    const authToken = localStorage.getItem('authToken') || sessionStorage.getItem('authToken');
    axios.delete(`/api/v1/books/${bookId}`, {
        headers: { Authorization: 'Bearer ' + authToken }
    })
    .then(response => {
        if (response.data.status === 'success') {
            document.getElementById(`bookRow-${bookId}`)?.remove();
            document.getElementById(`editRow-${bookId}`)?.remove();
            alert('Book deleted successfully');
        } else {
            alert('Delete failed: ' + (response.data.message || 'Unknown error'));
        }
    })
    .catch(err => {
        console.error('Error deleting book:', err);
        alert('An error occurred while deleting the book.');
    });
}

function previewBook(bookId) {
    const previewUrl = `/read/${bookId}`;
    window.open(previewUrl, '_blank');
}
</script>
