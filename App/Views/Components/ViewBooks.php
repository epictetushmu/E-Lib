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

<!-- Include additional libraries -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/animate.css@4.1.1/animate.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    /* Smooth transition for showing/hiding edit form */
    .edit-row {
        transition: all 0.4s ease;
        overflow: hidden;
    }
</style>

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

            const displayRow = `
                <tr id="bookRow-${id}">
                    <td>${title}</td>
                    <td>${author}</td>
                    <td>${description}</td>
                    <td><span class="badge ${status === 'public' ? 'bg-success' : 'bg-secondary'}">${status.charAt(0).toUpperCase() + status.slice(1)}</span></td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-warning" onclick="editBook('${id}')">
                                <i class="bi bi-pencil-square"></i> Edit
                            </button>
                            <button class="btn btn-primary" onclick="previewBook('${id}')">
                                <i class="bi bi-file-earmark-pdf"></i> Preview
                            </button>
                            <button class="btn btn-danger" onclick="deleteBook('${id}')">
                                <i class="bi bi-trash3"></i> Delete
                            </button>
                        </div>
                    </td>
                </tr>
            `;

            const editRow = `
                <tr id="editRow-${id}" class="edit-row" style="display: none;">
                    <td colspan="5">
                        <div class="card shadow-sm animate__animated animate__fadeIn">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">Edit Book: ${title}</h5>
                            </div>
                            <div class="card-body">
                                <form onsubmit="submitEdit(event, '${id}')">
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
                                                <input type="text" class="form-control" id="author-${id}" name="author" value="${author}" required>
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
                                        <div class="col-12">
                                            <label for="categories-${id}" class="form-label">Categories</label>
                                            <input type="text" class="form-control" id="categories-${id}" name="categories"
                                                value="${categories}" placeholder="Ex: Fiction, Fantasy, Adventure">
                                            <div class="form-text">Separate categories with commas.</div>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-end mt-4">
                                        <button type="button" class="btn btn-outline-secondary me-2" onclick="cancelEdit('${id}')">Cancel</button>
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
        Swal.fire('Error', 'Failed to fetch books.', 'error');
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
        bookData[key] = key === 'categories' ? value.split(',').map(v => v.trim()) : value;
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
            Swal.fire('Success', 'Book updated successfully.', 'success').then(() => getBooks());
        } else {
            Swal.fire('Error', response.data.message || 'Update failed', 'error');
        }
    })
    .catch(err => {
        console.error('Error updating book:', err);
        Swal.fire('Error', 'An error occurred during update.', 'error');
    });
}

function deleteBook(bookId) {
    Swal.fire({
        title: 'Are you sure?',
        text: "This action can't be undone!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            const authToken = localStorage.getItem('authToken') || sessionStorage.getItem('authToken');
            axios.delete(`/api/v1/books/${bookId}`, {
                headers: { Authorization: 'Bearer ' + authToken }
            })
            .then(response => {
                if (response.data.status === 'success') {
                    Swal.fire('Deleted!', 'Book has been deleted.', 'success').then(() => getBooks());
                } else {
                    Swal.fire('Error', response.data.message || 'Delete failed', 'error');
                }
            })
            .catch(err => {
                console.error('Error deleting book:', err);
                Swal.fire('Error', 'An error occurred while deleting.', 'error');
            });
        }
    });
}

function previewBook(bookId) {
    const previewUrl = `/read/${bookId}`;
    window.open(previewUrl, '_blank');
}
</script>
