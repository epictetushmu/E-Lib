<?php
/**
 * Universal Document Viewer Component
 * Supports multiple document formats: PDF, PowerPoint, EPUB, MOBI, Word docs, etc.
 * 
 * @param array $book Book data including file path and type
 */

// Default parameters
$book = $book ?? [];
$filePath = $book['file_path'] ?? $book['pdf_path'] ?? '';
$fileType = $book['file_type'] ?? 'pdf';
$fileExtension = $book['file_extension'] ?? 'pdf';

// Fall back to inferring file type from path if not provided in the book data
if (empty($fileType) && !empty($filePath)) {
    $extension = pathinfo($filePath, PATHINFO_EXTENSION);
    if ($extension) {
        $fileExtension = $extension;
        switch (strtolower($extension)) {
            case 'pdf':
                $fileType = 'pdf';
                break;
            case 'ppt':
            case 'pptx':
                $fileType = 'powerpoint';
                break;
            case 'epub':
                $fileType = 'epub';
                break;
            case 'mobi':
            case 'azw':
            case 'azw3':
                $fileType = 'kindle';
                break;
            case 'djvu':
                $fileType = 'djvu';
                break;
            case 'doc':
            case 'docx':
                $fileType = 'word';
                break;
            default:
                $fileType = 'unknown';
        }
    }
}
?>

<div class="document-viewer-container">
    <div class="card shadow">
        <div class="card-header d-flex justify-content-between align-items-center bg-dark text-white">
            <h5 class="m-0">
                <i class="fas fa-<?php echo getIconForFileType($fileType); ?> me-2"></i>
                <?php echo htmlspecialchars($book['title'] ?? 'Document'); ?>
            </h5>
            
            <div class="document-controls">
                <?php if (isset($book['downloadable']) && $book['downloadable']): ?>
                <a href="/download/<?php echo $book['_id']; ?>" class="btn btn-sm btn-outline-light me-2">
                    <i class="fas fa-download"></i> Download
                </a>
                <?php endif; ?>
                
                <a href="/book/<?php echo $book['_id']; ?>" class="btn btn-sm btn-outline-light">
                    <i class="fas fa-info-circle"></i> Details
                </a>
            </div>
        </div>

        <div class="card-body p-0">
            <!-- Loading Spinner -->
            <div id="loadingSpinner" class="text-center my-5 py-5" style="display: block;">
                <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;"></div>
                <div class="mt-3">Loading <?php echo strtoupper($fileType); ?>...</div>
            </div>

            <?php 
            // Load the appropriate viewer based on file type
            switch ($fileType) {
                case 'pdf':
                    include __DIR__ . '/Viewers/PdfViewer.php';
                    break;
                case 'powerpoint':
                    include __DIR__ . '/Viewers/PowerPointViewer.php';
                    break;
                case 'epub':
                    include __DIR__ . '/Viewers/EpubViewer.php';
                    break;
                case 'kindle':
                    include __DIR__ . '/Viewers/KindleViewer.php';
                    break;
                case 'djvu':
                    include __DIR__ . '/Viewers/DjvuViewer.php';
                    break;
                case 'word':
                    include __DIR__ . '/Viewers/WordViewer.php';
                    break;
                default:
                    // Default to showing download prompt for unsupported formats
                    echo '<div class="text-center my-5">
                            <div class="mb-4"><i class="fas fa-file-alt fa-5x text-muted"></i></div>
                            <h4>This file type cannot be previewed directly</h4>
                            <p class="text-muted">Please download the file to view its contents.</p>';
                    if (isset($book['downloadable']) && $book['downloadable']) {
                        echo '<a href="/download/' . $book['_id'] . '" class="btn btn-primary">
                                <i class="fas fa-download"></i> Download File
                            </a>';
                    }
                    echo '</div>';
            }
            ?>
        </div>
    </div>
</div>

<?php
// Helper function to get appropriate icon for file type
function getIconForFileType($fileType) {
    switch ($fileType) {
        case 'pdf':
            return 'file-pdf';
        case 'powerpoint':
            return 'file-powerpoint';
        case 'epub':
            return 'book';
        case 'kindle':
            return 'tablet';
        case 'djvu':
            return 'file-image';
        case 'word':
            return 'file-word';
        default:
            return 'file-alt';
    }
}
?>

<style>
    .document-viewer-container {
        height: calc(100vh - 160px);
        max-height: 800px;
        display: flex;
        flex-direction: column;
    }
    
    .card {
        flex: 1;
        display: flex;
        flex-direction: column;
        height: 100%;
    }
    
    .card-body {
        flex: 1;
        overflow: hidden;
        position: relative;
    }
    
    /* Responsive styles */
    @media (max-width: 768px) {
        .document-controls .btn-text {
            display: none;
        }
        
        .document-viewer-container {
            height: calc(100vh - 120px);
        }
    }
</style>