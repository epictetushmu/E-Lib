<div class="container mt-4">
    <h2 class="mb-4">Manage Books</h2>
    <div class="table-responsive">
        <table class="table table-striped align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Title</th>
                    <th>Author</th>
                    <th>Description</th>
                    <th style="width: 100px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                // Initialize $books as an empty array if not set
                $books = $books ?? [];
                
                // Only try to loop if $books is iterable
                if (!empty($books)): 
                    foreach ($books as $book): 
                ?>
                    <tr id="bookRow-<?= $book['id'] ?>">
                        <td><?= htmlspecialchars($book['title']) ?></td>
                        <td><?= htmlspecialchars($book['author']) ?></td>
                        <td><?= htmlspecialchars($book['description']) ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-warning" onclick="editBook('<?= $book['id'] ?>')">Edit</button>
                                <button class="btn btn-danger" onclick="deleteBook('<?= $book['id'] ?>')">Delete</button>
                            </div>
                        </td>
                    </tr>

                    <!-- Hidden edit form row -->
                    <tr id="editRow-<?= $book['id'] ?>" style="display: none;">
                        <td colspan="4">
                            <form class="edit-form" onsubmit="submitEdit(event, '<?= $book['id'] ?>')">
                                <div class="row g-2">
                                    <div class="col-md-3">
                                        <input type="text" class="form-control" name="title" placeholder="Title" value="<?= htmlspecialchars($book['title']) ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <input type="text" class="form-control" name="author" placeholder="Author" value="<?= htmlspecialchars($book['author']) ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <input type="text" class="form-control" name="description" placeholder="Description" value="<?= htmlspecialchars($book['description']) ?>">
                                    </div>
                                    <div class="col-md-2 d-flex justify-content-end">
                                        <button class="btn btn-success me-2" type="submit">Save</button>
                                        <button class="btn btn-secondary" type="button" onclick="cancelEdit(<?= $book['id'] ?>)">Cancel</button>
                                    </div>
                                </div>
                            </form>
                        </td>
                    </tr>
                <?php 
                    endforeach; 
                endif;
                ?>
                <!-- Books will be loaded here by JavaScript -->
            </tbody>
        </table>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    getBooks();
});


function editBook(bookId) {
    document.getElementById('bookRow-' + bookId).style.display = 'none';
    document.getElementById('editRow-' + bookId).style.display = '';
}

function cancelEdit(bookId) {
    document.getElementById('editRow-' + bookId).style.display = 'none';
    document.getElementById('bookRow-' + bookId).style.display = '';
}

function submitEdit(event, bookId) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    
    // Convert FormData to a plain object
    const bookData = {};
    formData.forEach((value, key) => {
        bookData[key] = value;
    });
    
    axios.put(`/api/v1/books/${bookId}`, bookData, {
        headers: { 
            "Authorization": "Bearer " + (localStorage.getItem('authToken') || sessionStorage.getItem('authToken')),
            "Content-Type": "application/json"
        }
    })
    .then(response => {
        if (response.data.success) {
            // Reload updated book list
            getBooks(); // Refresh the book list instead of full page reload
            cancelEdit(bookId); // Close the edit form
        } else {
            alert('Update failed: ' + (response.data.message || 'Unknown error'));
        }
    })
    .catch(err => {
        console.error('Error updating book:', err);
        alert('An error occurred while updating the book');
    });
}

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
        headers: {
            'Authorization': 'Bearer ' + authToken
        }
    })
    .then(response => {
        const books = response.data.data;
        const tableBody = document.querySelector('tbody');
        tableBody.innerHTML = '';

        books.forEach(book => {
            const id = book._id?.$oid || book._id;
            const title = escapeHtml(book.title);
            const author = escapeHtml(book.author);
            const description = escapeHtml(book.description);

            const row = `
                <tr id="bookRow-${id}">
                    <td>${title}</td>
                    <td>${author}</td>
                    <td>${description}</td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-warning" onclick="editBook('${id}')">Edit</button>
                            <button class="btn btn-danger" onclick="deleteBook('${id}')">Delete</button>
                        </div>
                    </td>
                </tr>
                <tr id="editRow-${id}" style="display: none;">
                    <td colspan="4">
                        <form class="edit-form" onsubmit="submitEdit(event, '${id}')">
                            <div class="row g-2">
                                <div class="col-md-3">
                                    <input type="text" class="form-control" name="title" placeholder="Title" value="${title}">
                                </div>
                                <div class="col-md-3">
                                    <input type="text" class="form-control" name="author" placeholder="Author" value="${author}">
                                </div>
                                <div class="col-md-4">
                                    <input type="text" class="form-control" name="description" placeholder="Description" value="${description}">
                                </div>
                                <div class="col-md-2 d-flex justify-content-end">
                                    <button class="btn btn-success me-2" type="submit">Save</button>
                                    <button class="btn btn-secondary" type="button" onclick="cancelEdit('${id}')">Cancel</button>
                                </div>
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

function deleteBook(bookId) {
    if (!confirm('Are you sure you want to delete this book? This action cannot be undone.')) {
        return;
    }
    
    const authToken = localStorage.getItem('authToken') || sessionStorage.getItem('authToken');
    
    axios.delete(`/api/v1/books/${bookId}`, {
        headers: {
            'Authorization': 'Bearer ' + authToken
        }
    })
    .then(response => {
        if (response.data.status === 'success') {
            // Remove the book from the UI
            const bookRow = document.getElementById(`bookRow-${bookId}`);
            const editRow = document.getElementById(`editRow-${bookId}`);
            
            if (bookRow) bookRow.remove();
            if (editRow) editRow.remove();
            
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