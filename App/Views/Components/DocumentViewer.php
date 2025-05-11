<?php
/**
 * Universal Document Viewer Component
 * Supports multiple document formats: PDF, PowerPoint, EPUB, MOBI, Word docs, etc.
 * Data is fetched from API call rather than direct PHP variables
 */

// Get bookId from URL for viewer initialization
$pathParts = explode('/', $_SERVER['REQUEST_URI']);
$bookId = $bookId ?? end($pathParts);
?>

<div class="document-viewer-container">
    <div class="card shadow">
        <!-- Header with book info - will be populated dynamically -->
        <div class="card-header d-flex justify-content-between align-items-center bg-dark text-white" id="documentHeader">
            <!-- Loading placeholder -->
            <h5 class="m-0">
                <div class="placeholder-glow d-inline-block" style="min-width: 250px;">
                    <span class="placeholder col-12"></span>
                </div>
            </h5>
            
            <div class="document-controls">
                <a href="/book/<?php echo htmlspecialchars($bookId); ?>" class="btn btn-sm btn-outline-light">
                    <i class="fas fa-info-circle"></i> Details
                </a>
            </div>
        </div>

        <div class="card-body p-0">
            <!-- Loading Spinner -->
            <div id="loadingSpinner" class="text-center my-5 py-5" style="display: block;">
                <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;"></div>
                <div class="mt-3" id="loadingText">Loading document...</div>
            </div>

            <!-- Book data will be fetched by JavaScript -->
            <script>
                // Global variables for viewers
                const bookId = "<?php echo htmlspecialchars($bookId); ?>";
                let fileType = "pdf"; // Default, will be updated after API call
                let book = {}; // Will hold book data
            </script>
            
            <!-- Viewer containers - only one will be shown based on file type -->
            <div id="pdfViewerContainer" style="display: none;">
                <?php include __DIR__ . '/Viewers/PdfViewer.php'; ?>
            </div>
            
            <div id="powerpointViewerContainer" style="display: none;">
                <?php include __DIR__ . '/Viewers/PowerPointViewer.php'; ?>
            </div>
            
            <div id="epubViewerContainer" style="display: none;">
                <?php include __DIR__ . '/Viewers/EpubViewer.php'; ?>
            </div>
            
            <div id="kindleViewerContainer" style="display: none;">
                <?php include __DIR__ . '/Viewers/KindleViewer.php'; ?>
            </div>
            
            <div id="djvuViewerContainer" style="display: none;">
                <?php include __DIR__ . '/Viewers/DjvuViewer.php'; ?>
            </div>
            
            <div id="wordViewerContainer" style="display: none;">
                <?php include __DIR__ . '/Viewers/WordViewer.php'; ?>
            </div>
            
            <div id="unsupportedFormatContainer" class="text-center my-5" style="display: none;">
                <div class="mb-4"><i class="fas fa-file-alt fa-5x text-muted"></i></div>
                <h4>This file type cannot be previewed directly</h4>
                <p class="text-muted">Please download the file to view its contents.</p>
                <a href="#" id="unsupportedDownloadBtn" class="btn btn-primary">
                    <i class="fas fa-download"></i> Download File
                </a>
            </div>
            
            <div id="errorContainer" class="text-center my-5" style="display: none;">
                <div class="mb-4"><i class="fas fa-exclamation-circle fa-5x text-danger"></i></div>
                <h4>Error Loading Document</h4>
                <p class="text-muted" id="errorMessage">The document could not be loaded.</p>
                <button onclick="location.reload()" class="btn btn-outline-primary me-2">Try Again</button>
                <a href="/book/" class="btn btn-outline-secondary" id="errorDetailsLink">View Book Details</a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script>
    // Get authentication token
    const authToken = localStorage.getItem('authToken') || sessionStorage.getItem('authToken') || '';
    
    // Function to fetch book data and initialize viewer
    function initializeDocumentViewer() {
        // Update loading text
        document.getElementById('loadingText').textContent = "Fetching document information...";
        
        // Fetch book data from API
        axios.get(`/api/v1/books/${bookId}`, {
            headers: {
                'Authorization': `Bearer ${authToken}`
            }
        })
        .then(response => {
            if (response.data?.status === 'success' && response.data?.data) {
                book = response.data.data;
                
                // Determine file type
                let detectedFileType = book.file_type || 'pdf';
                let filePath = book.file_path || book.pdf_path || '';
                
                // If file type is not specified, attempt to infer from file extension
                if (!book.file_type && filePath) {
                    const extension = filePath.split('.').pop()?.toLowerCase();
                    
                    if (extension) {
                        switch (extension) {
                            case 'pdf':
                                detectedFileType = 'pdf';
                                break;
                            case 'ppt':
                            case 'pptx':
                                detectedFileType = 'powerpoint';
                                break;
                            case 'epub':
                                detectedFileType = 'epub';
                                break;
                            case 'mobi':
                            case 'azw':
                            case 'azw3':
                                detectedFileType = 'kindle';
                                break;
                            case 'djvu':
                                detectedFileType = 'djvu';
                                break;
                            case 'doc':
                            case 'docx':
                                detectedFileType = 'word';
                                break;
                            default:
                                detectedFileType = 'unknown';
                        }
                    }
                }
                
                // Update global fileType for use in viewers
                fileType = detectedFileType;
                
                // Update document header
                updateDocumentHeader(book, fileType);
                
                // Dispatch event for page title update
                const bookDataEvent = new CustomEvent('bookDataLoaded', {
                    detail: {
                        title: book.title || 'Book Reader',
                        id: bookId
                    }
                });
                document.dispatchEvent(bookDataEvent);
                
                // Initialize appropriate viewer based on file type
                initializeViewer(fileType);
                
            } else {
                showError("Could not retrieve book information.");
            }
        })
        .catch(error => {
            console.error("Error fetching book data:", error);
            showError("Error retrieving book information. Please try again later.");
        });
    }
    
    // Function to update document header
    function updateDocumentHeader(book, fileType) {
        const header = document.getElementById('documentHeader');
        const iconName = getIconForFileType(fileType);
        
        // Update title
        header.querySelector('h5').innerHTML = `
            <i class="fas fa-${iconName} me-2"></i>
            ${book.title ? book.title.escapeHTML() : 'Document'}
        `;
        
        // Update controls
        const controls = header.querySelector('.document-controls');
        
        // Add download button if the book is downloadable
        if (book.downloadable !== false) {
            const downloadBtn = document.createElement('a');
            downloadBtn.href = '#';
            downloadBtn.className = 'btn btn-sm btn-outline-light me-2';
            downloadBtn.id = 'downloadDocument';
            downloadBtn.innerHTML = '<i class="fas fa-download"></i> Download';
            
            // Insert download button before the details button
            controls.insertBefore(downloadBtn, controls.firstChild);
            
            // Add event listener for download
            downloadBtn.addEventListener('click', function(e) {
                e.preventDefault();
                downloadDocument(book);
            });
        }
    }
    
    // Function to download document
    function downloadDocument(book) {
        // Make sure we're using the book ID string, not the entire book object or ObjectId
        let downloadId;
        
        if (typeof book === 'object') {
            if (book._id) {
                // Handle case where _id could be a string or an object with $oid property (MongoDB format)
                if (typeof book._id === 'string') {
                    downloadId = book._id;
                } else if (typeof book._id === 'object' && book._id.$oid) {
                    downloadId = book._id.$oid;
                } else {
                    downloadId = String(book._id); // Force conversion to string
                }
            } else {
                downloadId = bookId;
            }
        } else {
            downloadId = bookId;
        }
        
        // For debugging
        console.log("Download ID:", downloadId);
        
        axios({
            method: 'get',
            url: `/api/v1/download/${downloadId}`,
            responseType: 'blob',
            headers: {
                'Authorization': `Bearer ${authToken}`
            }
        })
        .then(response => {
            const url = window.URL.createObjectURL(new Blob([response.data]));
            const link = document.createElement('a');
            link.href = url;
            
            // Get file extension for download
            const extension = book.file_extension || fileType;
            
            link.setAttribute('download', `${book.title || 'document'}.${extension}`);
            document.body.appendChild(link);
            link.click();
            link.remove();
        })
        .catch(error => {
            console.error("Download error:", error);
            alert("Failed to download document. Please try again later.");
        });
    }
    
    // Function to initialize the appropriate viewer
    function initializeViewer(fileType) {
        // Hide loading spinner
        document.getElementById('loadingText').textContent = `Loading ${fileType.toUpperCase()}...`;
        
        // Show appropriate viewer
        switch (fileType) {
            case 'pdf':
                document.getElementById('pdfViewerContainer').style.display = 'block';
                // PDF viewer is initialized in its own script
                if (typeof initializePdfViewer === 'function') {
                    initializePdfViewer();
                }
                break;
                
            case 'powerpoint':
                document.getElementById('powerpointViewerContainer').style.display = 'block';
                // PowerPoint viewer is initialized in its own script
                if (typeof initializePowerpointViewer === 'function') {
                    initializePowerpointViewer();
                }
                break;
                
            case 'epub':
                document.getElementById('epubViewerContainer').style.display = 'block';
                // EPUB viewer is initialized in its own script
                if (typeof initializeEpubViewer === 'function') {
                    initializeEpubViewer();
                }
                break;
                
            case 'kindle':
                document.getElementById('kindleViewerContainer').style.display = 'block';
                // Kindle viewer is initialized in its own script
                if (typeof initializeKindleViewer === 'function') {
                    initializeKindleViewer();
                }
                break;
                
            case 'djvu':
                document.getElementById('djvuViewerContainer').style.display = 'block';
                // DJVU viewer is initialized in its own script
                if (typeof initializeDjvuViewer === 'function') {
                    initializeDjvuViewer();
                }
                break;
                
            case 'word':
                document.getElementById('wordViewerContainer').style.display = 'block';
                // Word viewer is initialized in its own script
                if (typeof initializeWordViewer === 'function') {
                    initializeWordViewer();
                }
                break;
                
            default:
                // Show unsupported format message
                document.getElementById('loadingSpinner').style.display = 'none';
                const unsupportedContainer = document.getElementById('unsupportedFormatContainer');
                unsupportedContainer.style.display = 'block';
                
                // Set up download button for unsupported formats
                const downloadBtn = document.getElementById('unsupportedDownloadBtn');
                
                if (book.downloadable === false) {
                    downloadBtn.style.display = 'none';
                } else {
                    downloadBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        downloadDocument(book);
                    });
                }
        }
    }
    
    // Function to show error message
    function showError(message) {
        document.getElementById('loadingSpinner').style.display = 'none';
        document.getElementById('errorContainer').style.display = 'block';
        document.getElementById('errorMessage').textContent = message;
        document.getElementById('errorDetailsLink').href = `/book/${bookId}`;
    }
    
    // Helper function to get appropriate icon for file type
    function getIconForFileType(fileType) {
        switch (fileType) {
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
    
    // HTML escaping helper
    String.prototype.escapeHTML = function() {
        return this
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    };
    
    // Initialize the viewer when document is ready
    document.addEventListener('DOMContentLoaded', initializeDocumentViewer);
</script>

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