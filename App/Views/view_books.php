<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Book | Epictetus Library</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/styles/add_book.css">
</head>
<body class="d-flex flex-column min-vh-100">
    <?php
        include 'Partials/Header.php';
        include 'Components/ListBooks.php';
        ListBooks::renderListBooks($books); 
        include 'Partials/Footer.php';
    ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        fetch('/api/v1/list-books')
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                const booksContainer = document.querySelector('.list-books .row');

                if (!Array.isArray(data) || data.length === 0) {
                    booksContainer.innerHTML = '<p class="text-center">No books available at the moment.</p>';
                } else {
                    // Clear existing content only if new data is available
                    booksContainer.innerHTML = '';
                    data.forEach(book => {
                        const bookCard = `
                            <div class="col-md-4 mb-4">
                                <div class="card h-100 shadow-sm">
                                    <img src="${book.thumbnail_path || '/assets/images/placeholder-book.jpg'}" 
                                         class="card-img-top" style="height: 200px; object-fit: cover;" 
                                         alt="${book.title || 'Book cover'}">
                                    <div class="card-body d-flex flex-column">
                                        <h5 class="card-title text-truncate" title="${book.title || 'Unknown Title'}">
                                            ${book.title || 'Unknown Title'}
                                        </h5>
                                        <p class="card-text text-muted small text-truncate">
                                            By ${book.author || 'Unknown Author'}
                                        </p>
                                        ${book.year ? `<p class="card-text small mb-2">${book.year}</p>` : ''}
                                        <a href="/book/${book._id}" class="btn btn-sm btn-primary mt-auto">
                                            View Details
                                        </a>
                                    </div>
                                </div>
                            </div>
                        `;
                        booksContainer.insertAdjacentHTML('beforeend', bookCard);
                    });
                }
            })
            .catch(error => {
                console.error('There was a problem with the fetch operation:', error);
            });
    });
</script>
</body>
</html>