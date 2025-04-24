
<div class="row">
    <!-- Book Cover + Actions Column -->
    <div class="col-md-4">
        <img src="<?= htmlspecialchars($book['thumbnail_path'] ?? '/assets/uploads/thumbnails/placeholder-book.jpg') ?>"
                alt="<?= htmlspecialchars($book['title']) ?> cover"
                class="img-fluid rounded shadow-sm"
                onerror="this.src='/assets/uploads/thumbnails/placeholder-book.jpg'">

        <?php if (isset($_SESSION['user_id'])): ?>
            <div class="mt-2">
                <button id="saveBtn" class="btn btn-outline-primary w-100">
                    <i class="fas fa-bookmark me-2"></i>Save to List
                </button>
            </div>
        
            <!-- Download Button -->
            <?php if (!empty($book['pdf_path'])): ?>
                <div class="mt-2">
                    <a href="/api/v1/download/<?= htmlspecialchars($book['_id']) ?>" class="btn btn-outline-success w-100" download>
                        <i class="fas fa-file-download me-2"></i>Download PDF
                    </a>
                </div>
            <?php endif; ?>
            <!-- Read Online Button -->
            <div class="mt-2">
                <a href="/read/<?= htmlspecialchars($book['_id']) ?>" class="btn btn-outline-info w-100">
                    <i class="fas fa-book-open me-2"></i>Read Online
                </a>
            </div>  
        <?php else: ?>
            <!-- Login to access -->
            <div class="mt-2">
                <a href="/login?redirect=<?= urlencode('/book/' . $book['_id']) ?>" class="btn btn-outline-secondary w-100">
                    <i class="fas fa-lock me-2"></i>Login for Full Access
                </a>
            </div>
        <?php endif; ?>
        <!-- Share Button -->
        <div class="mt-2">
            <button id="shareBtn" class="btn btn-outline-secondary w-100">
                <i class="fas fa-share-alt me-2"></i>Share
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
                    <p><strong>ISBN:</strong> <?= htmlspecialchars($book['isbn']) ?></p>
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
<script>
document.addEventListener('DOMContentLoaded', function() {
    const shareBtn = document.getElementById('shareBtn');
    
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
});
</script>
