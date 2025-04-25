
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
                <?php foreach ($books as $book): ?>
                    <tr id="bookRow-<?= $book['id'] ?>">
                        <td><?= htmlspecialchars($book['title']) ?></td>
                        <td><?= htmlspecialchars($book['author']) ?></td>
                        <td><?= htmlspecialchars($book['description']) ?></td>
                        <td>
                            <button class="btn btn-sm btn-warning" onclick="editBook(<?= $book['id'] ?>)">Edit</button>
                        </td>
                    </tr>

                    <!-- Hidden edit form row -->
                    <tr id="editRow-<?= $book['id'] ?>" style="display: none;">
                        <td colspan="4">
                            <form class="edit-form" onsubmit="submitEdit(event, <?= $book['id'] ?>)">
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
                <?php endforeach; ?>
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
    const data = new FormData(form);

    fetch(`/api/v1/books/${bookId}`, {
        method: 'POST',
        body: data
    })
    .then(res => res.json())
    .then(response => {
        if (response.success) {
            // Ideally: reload updated book list or update UI inline
            location.reload(); // For now, just reload
        } else {
            alert('Update failed: ' + response.message);
        }
    })
    .catch(err => {
        console.error('Error updating book:', err);
        alert('An error occurred.');
    });
}
function getBooks(){ 
    axios.get('/api/v1/books', {headers: {'Authorization': 'Bearer ' +( localStorage.getItem('authToken') || sessionStorage.getItem('authToken'))}})
        .then(response => {
            console.log(response.data.data);
            const books = response.data.data;
            const tableBody = document.querySelector('tbody');
            tableBody.innerHTML = ''; 
        })
        .catch(error => {
            console.error('Error fetching books:', error);
            alert('Failed to fetch books.');
        });
}
</script>