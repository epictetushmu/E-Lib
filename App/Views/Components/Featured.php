<main class="flex-grow-1 py-5" id="featured">
    <div class="container">
        <h2 class="text-center mb-5 fw-bold text-primary">Featured Collection</h2>

        <!-- Loading Spinner -->
        <div id="loader" class="text-center my-5">
            <div class="spinner-border text-primary" role="status" aria-label="Loading..."></div>
        </div>

        <!-- Books Grid -->
        <div class="row g-4" id="booksGrid">
            <!-- Dynamic content loaded via JavaScript -->
        </div>
    </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const loader = document.getElementById('loader');
        const booksGrid = document.getElementById('booksGrid');

        const loadFeaturedBooks = async () => {
            try {
                loader.style.display = 'block';
                const { data } = await axios.get('/api/v1/books/featured');

                if (data?.status === 'success' && Array.isArray(data.data)) {
                    renderBooks(data.data);
                } else {
                    showError('No featured books found.');
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
                const bookId = book._id.$oid || book._id;
                return `
                    <div class="col-md-4 mb-4">
                        <div class="card h-100 shadow-sm">
                            <img src="${book.thumbnail || '/assets/uploads/thumbnails/placeholder-book.jpg'}" 
                                 class="card-img-top" style="height: 200px; object-fit: cover;"
                                 alt="${book.title || 'Book cover'}"
                                 onerror="this.src='/assets/uploads/thumbnails/placeholder-book.jpg'">
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title text-truncate" title="${book.title || 'Unknown Title'}">
                                    ${book.title || 'Unknown Title'}
                                </h5>
                                <p class="card-text text-muted small text-truncate">
                                    By ${book.author || 'Unknown Author'}
                                </p>
                                ${book.year ? `<p class="card-text small mb-2">${book.year}</p>` : ''}
                                <a href="/book/${bookId}" class="btn btn-sm btn-primary mt-auto">
                                    View Details
                                </a>
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