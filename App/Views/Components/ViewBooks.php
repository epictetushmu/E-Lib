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
                    <th style="width: 140px;">Actions</th>
                </tr>
            </thead>
            <tbody id="booksTableBody">
                <!-- Rows are dynamically injected by JavaScript -->
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    getBooks();
});

function escapeHtml(text) {
    if (!text) return '';
    return text.replace(/[&<>"']/g, function(m) {
        return {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[m];
    });
}

function getBooks() {
    const authToken = localStorage.getItem('authToken') || sessionStorage.getItem('authToken');
    axios.get('/api/v1/books', {
        headers: { 'Authorization': 'Bearer ' + authToken }
    })
    .then(response => {
        const books = response.data.data;
        const tableBody = document.getElementById('booksTableBody');
        tableBody.innerHTML = '';

        books.forEach(book => {
            const id = book._id?.$oid || book._id;
            const title = escapeHtml(book.title);
            const author = escapeHtml(book.author);
            const description = escapeHtml(book.description);
            const status = book.status || 'available';

            const row = `
                <tr id="bookRow-${id}">
                    <td>${title}</td>
                    <td>${author}</td>
                    <td>${description}</td>
                    <td>${status.charAt(0).toUpperCase() + status.slice(1)}</td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-warning" onclick="editBook('${id}')">Edit</button>
                            <button class="btn btn-danger" onclick="deleteBook('${id}')">Delete</button>
                        </div>
                    </td>
                </tr>
                <tr id="editRow-${id}" style="display: none;">
                    <td colspan="5">
                        <form onsubmit="submitEdit(event, '${id}')">
                            <div class="row g-2 mb-2">
                                <div class="col-md-3">
                                    <input type="text" class="form-control" name="title" value="${title}" required>
                                </div>
                                <div class="col-md-3">
                                    <input type="text" class="form-control" name="author" value="${author}" required>
                                </div>
                                <div class="col-md-4">
                                    <input type="text" class="form-control" name="description" value="${description}">
                                </div>
                                <div class="col-md-2">
                                    <select class="form-select" name="status">
                                        <option value="draft" ${status === 'draft' ? 'selected' : ''}>Draft</option>
                                        <option value="public" ${status === 'public' ? 'selected' : ''}>Public</option>
                                    </select>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-success me-2">Save</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('${id}')">Cancel</button>
                            </div>
                        </form>
                    </td>
                </tr>
            `;
            tableBody.insertAdjacentHTML('beforeend', row);
        });
    })
    .catch(error => {
        console.error('Error fetching books:', error);
        alert('Failed to fetch books.');
    });
}

function editBook(bookId) {
    document.getElementById(`bookRow-${bookId}`).style.display = 'none';
    document.getElementById(`editRow-${bookId}`).style.display = '';
}

function cancelEdit(bookId) {
    document.getElementById(`editRow-${bookId}`).style.display = 'none';
    document.getElementById(`bookRow-${bookId}`).style.display = '';
}

function submitEdit(event, bookId) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    const bookData = {};

    formData.forEach((value, key) => {
        bookData[key] = value;
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
        alert('An error occurred while updating the book');
    });
}

function deleteBook(bookId) {
    if (!confirm('Are you sure you want to delete this book?')) return;

    const authToken = localStorage.getItem('authToken') || sessionStorage.getItem('authToken');
    axios.delete(`/api/v1/books/${bookId}`, {
        headers: { 'Authorization': 'Bearer ' + authToken }
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
        alert('An error occurred while deleting the book');
    });
}
</script>
