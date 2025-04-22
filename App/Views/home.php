<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Epictetus Library - Home of Knowledge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/styles/home.css"> 
    
</head>
<body class="d-flex flex-column min-vh-100">

    <?php 
        include 'Partials/Header.php';
        include 'Components/Hero.php';
        if (!$isLoggedIn): ?>
            <div id="loginPopup" style="display:none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
                background-color: rgba(0,0,0,0.5); z-index: 1050;">
                <?php include 'Components/LoginForm.php'; ?>
            </div>
        <?php endif; 
        include 'Components/Featured.php';
        include 'Partials/Footer.php';
    ?>

<!-- Dependencies -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

    <script>
    let hasScrolled = false;

    window.addEventListener('scroll', function () {
        if (!hasScrolled && window.scrollY > 50) {
            hasScrolled = true;

            // Only show popup if it's included in DOM
            const popup = document.getElementById('loginPopup');
            if (popup) {
                popup.style.display = 'flex'; // or block based on your modal style
            }
            this.sessionStorage.setItem('hasScrolled', 'true');
        }
    });

    // Optional: close popup
    function closeLoginPopup() {
        document.getElementById('loginPopup').style.display = 'none';
    }

    document.addEventListener('DOMContentLoaded', () => {
        const loader = document.getElementById('loader');
        const booksGrid = document.getElementById('booksGrid');

        const loadFeaturedBooks = async () => {
            try {
                loader.style.display = 'block';
                const { data } = await axios.get('/api/v1/featured-books');

                if (data?.status === 'success' && Array.isArray(data.data)) {
                    renderBooks(data.data);
                } else {
                    showError('No books found in the collection.');
                }
            } catch (error) {
                console.error('Error loading featured books:', error);
                showError('An error occurred while fetching featured books.');
            } finally {
                loader.style.display = 'none';
            }
        };

        const renderBooks = (books) => {
            if (!books.length) return showError('No featured books available.');

            booksGrid.innerHTML = books.map(book => {
                // Extract the book ID correctly from MongoDB's ObjectId format
                const bookId = book._id.$oid || book._id;
                
                return `
                <div class="col-md-6 col-lg-4 col-xl-3 mb-4">
                    <div class="card h-100 shadow-sm border-0 book-card">
                        <div class="position-relative book-cover-wrapper">
                            <img src="${book.thumbnail_path || '/assets/uploads/thumbnails/placeholder-book.jpg'}"
                                 alt="${book.title} cover"
                                 class="card-img-top book-cover"
                                 onerror="this.src='/assets/uploads/thumbnails/placeholder-book.jpg'">
                        </div>
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title mb-1 text-truncate" title="${book.title}">${book.title}</h5>
                            <p class="text-muted mb-3 small">${book.author || 'Unknown Author'}</p>
                            <div class="d-flex justify-content-between mt-auto align-items-center">
                                <div>
                                ${book.categories && Array.isArray(book.categories) && book.categories.length > 0 ? 
                                    `<span class="badge bg-info text-dark">${book.categories[0]}</span>` : 
                                    `<span class="badge bg-secondary text-light">General</span>`}
                                </div>
                                <a href="/book/${bookId}" class="btn btn-sm btn-outline-primary">
                                    Details <i class="fas fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                `;
            }).join('');
        };

        const showError = (message) => {
            booksGrid.innerHTML = `
                <div class="col-12 text-center text-danger">
                    <i class="fas fa-exclamation-circle fa-3x mb-3"></i>
                    <p class="fw-semibold">${message}</p>
                </div>
            `;
        };

        loadFeaturedBooks();
    });
</script>
</body>
</html>