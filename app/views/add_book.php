<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Book | Epictetus Library</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100">
    
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="/E-Lib/">
                <i class="fas fa-book-open me-2"></i>Epictetus Library
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="/E-Lib/">Home</a></li>
                    <li class="nav-item"><a class="nav-link active" href="/E-Lib/add-book">Add Book</a></li>
                    <li class="nav-item"><a class="nav-link" href="/E-Lib/search_results">Search</a></li>
                </ul>
                <form class="d-flex" id="searchForm">
                    <div class="input-group">
                        <input type="search" id="search" class="form-control" placeholder="Search titles..." aria-label="Search">
                        <button type="submit" class="btn btn-success"><i class="fas fa-search"></i></button>
                    </div>
                </form>
            </div>
        </div>
    </nav>

    <!-- Add Book Form Section -->
    <div class="container mt-5">
        <h2 class="text-center fw-bold">Add a New Book</h2>
        <div class="card p-4 shadow mt-4">
            <form id="bookForm">
                <div class="mb-3">
                    <label for="title" class="form-label">Book Title</label>
                    <input type="text" class="form-control" id="title" required>
                </div>
                <div class="mb-3">
                    <label for="author" class="form-label">Author</label>
                    <input type="text" class="form-control" id="author">
                </div>
                <div class="mb-3">
                    <label for="category" class="form-label">Category</label>
                    <select id="category" class="form-select" multiple>
                        <option value="Literature">Literature</option>
                        <option value="Science Fiction">Science Fiction</option>
                        <option value="Non-Fiction">Non-Fiction</option>
                        <option value="Fantasy">Fantasy</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="year" class="form-label">Publication Year</label>
                    <input type="number" class="form-control" id="year">
                </div>
                <div class="mb-3">
                    <label for="condition" class="form-label">Condition</label>
                    <select class="form-select" id="condition">
                        <option value="New">New</option>
                        <option value="Good">Good</option>
                        <option value="Fair">Fair</option>
                        <option value="Poor">Poor</option>
                        <option value="undefined">Unknown</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="copies" class="form-label">Number of Copies</label>
                    <input type="number" class="form-control" id="copies" required>
                </div>
                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" rows="3"></textarea>
                </div>
                <div class="mb-3">
                    <label for="cover" class="form-label">Cover Image</label>
                    <input type="file" class="form-control" id="cover">
                </div>
                <div class="text-center">
                    <button type="submit" class="btn btn-primary">Insert</button>
                    <button type="button" class="btn btn-secondary" id="clearForm">Clear</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Footer -->
    <footer class="py-4 mt-auto bg-dark text-light text-center">
        <div class="container">
            <p>&copy; 2025 Epictetus Library. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script>
        document.getElementById('bookForm').addEventListener('submit', function(event) {
            event.preventDefault();
            const title = document.getElementById('title').value;
            const author = document.getElementById('author').value;
            const year = document.getElementById('year').value;
            const condition = document.getElementById('condition').value;
            const copies = document.getElementById('copies').value;
            const description = document.getElementById('description').value;
            const cover = document.getElementById('cover').files[0];
            const categories = Array.from(document.getElementById('category').selectedOptions).map(option => option.value);

            const formData = new FormData();
            formData.append('title', title);
            formData.append('author', author);
            formData.append('year', year);
            formData.append('condition', condition);
            formData.append('copies', copies);
            formData.append('description', description);
            formData.append('cover', cover);
            formData.append('category', JSON.stringify(categories));

            axios.post('/E-Lib/api/add-book', formData)
                .then(response => {
                    alert('Book added successfully!');
                    document.getElementById('bookForm').reset();
                })
                .catch(error => {
                    console.error('Error adding the book!', error);
                });
        });
    </script>
</body>
</html>
