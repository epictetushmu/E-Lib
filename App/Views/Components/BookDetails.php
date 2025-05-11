<div class="row">
    <!-- Book Cover + Actions Column -->
    <div class="col-md-4">
        <img src="<?= htmlspecialchars($book['thumbnail_path'] ?? '/assets/uploads/thumbnails/placeholder-book.jpg') ?>"
                alt="<?= htmlspecialchars($book['title']) ?> cover"
                class="img-fluid rounded shadow-sm"
                onerror="this.src='/assets/uploads/thumbnails/placeholder-book.jpg'">

        <!-- Action Buttons -->
        <div class="action-buttons mt-4">
            <?php if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])): ?>
                <button id="saveBtn" class="btn btn-outline-primary me-2 action-btn">
                    <i class="fas fa-bookmark me-1"></i> Save to Reading List
                </button>
                
                <button id="previewBtn" class="btn btn-info me-2 action-btn">
                    <i class="fas fa-eye me-1"></i> Online Preview
                </button>
                
                <?php if (!isset($book['downloadable']) || $book['downloadable'] === true): ?>
                    <button id="downloadBtn" class="btn btn-success me-2 action-btn">
                        <i class="fas fa-download me-1"></i> Download PDF
                    </button>
                <?php else: ?>
                    <button class="btn btn-outline-secondary me-2 action-btn" disabled title="This book is not available for download">
                        <i class="fas fa-ban me-1"></i> Download Disabled
                    </button>
                <?php endif; ?>
            <?php endif; ?>
            
            <button id="shareBtn" class="btn btn-outline-secondary action-btn">
                <i class="fas fa-share-alt me-1"></i> Share
            </button>
        </div>
    </div>
    
    <!-- Book Info Column - MOVED OUT OF THE FIRST COLUMN -->
    <div class="col-md-8">
        <h1 class="fw-bold"><?= htmlspecialchars($book['title'] ?? 'Untitled') ?></h1>

        <!-- Categories -->
        <div class="mb-3">
            <?php if (!empty($book['categories'])): ?>
                <?php 
                // Handle MongoDB BSON arrays properly
                $categories = $book['categories'];
                if ($categories instanceof \MongoDB\Model\BSONArray) {
                    // Convert BSON array to PHP array
                    $categories = $categories->getArrayCopy();
                    foreach ($categories as $category): ?>
                        <span class="badge bg-info me-1 mb-1"><?= htmlspecialchars((string)$category) ?></span>
                    <?php endforeach;
                } elseif (is_array($categories)) {
                    // Regular PHP array
                    foreach ($categories as $category): ?>
                        <span class="badge bg-info me-1 mb-1"><?= htmlspecialchars((string)$category) ?></span>
                    <?php endforeach;
                } else {
                    // Single category as string
                    ?>
                    <span class="badge bg-info"><?= htmlspecialchars((string)$categories) ?></span>
                <?php } ?>
            <?php else: ?>
                <span class="badge bg-secondary">Uncategorized</span>
            <?php endif; ?>
        </div>

        <!-- Description -->
        <p class="text-muted fst-italic">"<?= htmlspecialchars($book['description'] ?? 'No description available') ?>"</p>

        <!-- Metadata -->
        <div class="row mt-4">
            <div class="col-md-6">
                <p><strong>Author:</strong> <?= htmlspecialchars($book['author'] ?? 'Unknown') ?></p>
                <?php if (!empty($book['published_date'])): ?>
                    <p><strong>Published:</strong> <?= htmlspecialchars($book['published_date']) ?></p>
                <?php endif; ?>
                <?php if (!empty($book['isbn'])): ?>
                    <p><strong>ISBN:</strong> <span class="formatted-isbn"><?= htmlspecialchars($book['isbn']) ?></span></p>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <?php if (!empty($book['language'])): ?>
                    <p><strong>Language:</strong> <?= htmlspecialchars($book['language']) ?></p>
                <?php endif; ?>
                <?php if (!empty($book['pages'])): ?>
                    <p><strong>Pages:</strong> <?= htmlspecialchars($book['pages']) ?></p>
                <?php endif; ?>
                <?php if (!empty($book['publisher'])): ?>
                    <p><strong>Publisher:</strong> <?= htmlspecialchars($book['publisher']) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <a href="/" class="btn btn-secondary mt-4">
            <i class="fas fa-arrow-left me-2"></i>Back to Home
        </a>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const shareBtn = document.getElementById('shareBtn');
    
    // Format ISBN numbers for better readability
    const formatIsbnElements = document.querySelectorAll('.formatted-isbn');
    formatIsbnElements.forEach(element => {
        const isbn = element.textContent.trim();
        element.textContent = formatISBN(isbn);
    });
    
    // Format ISBN with hyphens based on standard rules
    function formatISBN(isbn) {
        if (!isbn) return isbn;
        
        // Remove any existing non-alphanumeric characters
        isbn = isbn.replace(/[^0-9X]/gi, '');
        
        if (isbn.length === 10) {
            // Format ISBN-10: 1-234-56789-X
            return isbn.substring(0, 1) + '-' + 
                   isbn.substring(1, 4) + '-' + 
                   isbn.substring(4, 9) + '-' + 
                   isbn.substring(9, 10);
        } else if (isbn.length === 13) {
            // Format ISBN-13: 978-1-234-56789-7
            return isbn.substring(0, 3) + '-' + 
                   isbn.substring(3, 4) + '-' + 
                   isbn.substring(4, 7) + '-' + 
                   isbn.substring(7, 12) + '-' + 
                   isbn.substring(12, 13);
        }
        
        return isbn; // Return unformatted if not correct length
    }
    
    if (shareBtn) {
        shareBtn.addEventListener('click', function() {
            // Get the current URL
            const bookUrl = window.location.href;
            
            // Copy to clipboard
            navigator.clipboard.writeText(bookUrl)
                .then(() => {
                    // Change button text/appearance temporarily
                    const originalContent = shareBtn.innerHTML;
                    shareBtn.innerHTML = '<i class="fas fa-check me-2"></i>Copied to clipboard!';
                    shareBtn.classList.add('btn-success');
                    shareBtn.classList.remove('btn-outline-secondary');
                    
                    // Reset button after 2 seconds
                    setTimeout(() => {
                        shareBtn.innerHTML = originalContent;
                        shareBtn.classList.remove('btn-success');
                        shareBtn.classList.add('btn-outline-secondary');
                    }, 2000);
                })
                .catch(err => {
                    console.error('Failed to copy URL: ', err);
                    alert('Could not copy link. Please try again.');
                });
        });
    }

    const saveBtn = document.getElementById('saveBtn');
    if (saveBtn) {
        saveBtn.addEventListener('click', async () => {
            const bookId = document.getElementById('bookId').value;
            
            try {
                const response = await axios.post('/api/v1/save-book', {
                    book_id: bookId
                
                }, {headers: {
                    'Authorization': `Bearer ${localStorage.getItem('authToken') || sessionStorage.getItem('authToken')}`  ;
                    
                }}
            );
                
                if (response.data.status === 'success') {
                    saveBtn.textContent = 'Saved to List';
                    saveBtn.disabled = true;
                } else {
                    alert(response.data.message || 'Failed to save book');
                }
            } catch (error) {
                console.error('Error saving book:', error);
                alert('An error occurred while saving the book');
            }
        });
    }

    const downloadBtn = document.getElementById('downloadBtn');
    const bookId = document.getElementById('bookId').value;
    if (downloadBtn) {
        downloadBtn.addEventListener('click', async function() {
            const token = localStorage.getItem('authToken') || sessionStorage.getItem('authToken');

            try {
                const response = await axios.get('/api/v1/books/'+ bookId + '/download', {
                    headers: {
                        'Authorization': `Bearer ${token}`
                    },
                    responseType: 'blob' // Important for downloading files
                });

                // Create a link element to download the file
                const url = window.URL.createObjectURL(new Blob([response.data]));
                const link = document.createElement('a');
                link.href = url;
                link.setAttribute('download', '<?= htmlspecialchars($book["title"]) ?>.pdf');
                document.body.appendChild(link);
                link.click();
                link.remove();
            } catch (error) {
                console.error('Error downloading the file:', error);
                alert('Failed to download the file. Please try again.');
            }
        });
    }

    // Add event listener for preview button
    const previewBtn = document.getElementById('previewBtn');
    if (previewBtn) {
        previewBtn.addEventListener('click', function() {
            const bookId = document.getElementById('bookId').value;
            window.location.href = `/read/${bookId}`; // Navigate in the same tab
        });
    }
});
</script>
