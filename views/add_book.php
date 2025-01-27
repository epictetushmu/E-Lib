<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Book</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
</head>
<link rel="stylesheet" href="../styles/insert_book.css">

<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container">
            <a class="navbar-brand" href="index.html">Library</a>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="index.html">Home</a></li>
                    <li class="nav-item"><a class="nav-link active" href="insert_book.html">Add Book</a></li>
                    <li class="nav-item"><a class="nav-link" href="search_results.html">Search</a></li>
                </ul>
                <form class="d-flex ms-3">
                    <input class="form-control me-2" id="search" type="search" id="bookToSearch"  placeholder="Search by title" aria-label="Search">
                    <button class="btn btn-outline-success" type="submit">Search</button>
                </form>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <h2>Add a New Book</h2>
        <form id="bookForm">

            <div class="mb-3">
                <label for="title" class="form-label">Book Title</label>
                <input type="text" class="form-control" id="title" data-description="Enter the book title" required>
                <div class="tooltip hidden">Enter the book title</div>
            </div>
            
            <div class="mb-3">
                <label for="author" class="form-label">Author</label>
                <input type="text" class="form-control" id="author" data-description="Enter the author's name">
                <div class="tooltip hidden">Enter the author's name</div>
            </div>
            
            <div class="mb-3">
                <label for="category" class="form-label">Category</label>
                <div class="custom-select-wrapper" data-description="Select one or more categories">
                    <div id="customDropdown" class="custom-select-display">Select categories</div>
                    <ul id="dropdownOptions" class="custom-dropdown hidden">
                        <li data-value="1">Literature</li>
                        <li data-value="2">Science Fiction</li>
                        <li data-value="3">Non-Fiction</li>
                        <li data-value="4">Fantasy</li>
                    </ul>
                    <select id="category" class="hidden" multiple>
                        <option value="1">Literature</option>
                        <option value="2">Science Fiction</option>
                        <option value="3">Non-Fiction</option>
                        <option value="4">Fantasy</option>
                    </select>
                </div>
                <div class="tooltip hidden">Select one or more categories</div>
            </div>

            <div class="mb-3">
                <label for="year" class="form-label">Publication Year</label>
                <input type="number" class="form-control" id="year" data-description="Enter the year the book was published">
            </div>
            <div class="mb-3">
                <label for="condition" class="form-label">Condition</label>
                <select class="form-select" id="condition" data-description="Select the condition of the book">
                    <option value="New">New</option>
                    <option value="Good">Good</option>
                    <option value="Fair">Fair</option>
                    <option value="Poor">Poor</option>
                    <option value="undefined">Unknown</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="copies" class="form-label">Number of Copies</label>
                <input type="number" class="form-control" id="copies" required data-description="Enter the number of copies available">
            </div>
            <div>
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control" id="description" rows="3" data-description="Provide a brief description of the book"></textarea>
            </div>
            <div class="mb-3">
                <label for="cover" class="form-label">Cover Image</label>
                <input type="file" class="form-control" id="cover" data-description="Upload the book's cover image">
            </div>
            
            <button type="submit" id="submitForm" class="btn btn-primary">Insert</button>
            <button type="button" class="btn btn-secondary" id="clearForm" >Clear Form</button>
        </form>
    </div>
    <script type="module" src="../js/ui/navBar.js"></script>
    <script type="module" src="../js/ui/insert_book.js"></script>
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
            formData.append('categories', JSON.stringify(categories));

            axios.post('/api/add_book.php', formData)
                .then(response => {
                    alert('Book added successfully!');
                    document.getElementById('bookForm').reset();
                })
                .catch(error => {
                    console.error('There was an error adding the book!', error);
                });
        });
    </script>
</body>
</html>