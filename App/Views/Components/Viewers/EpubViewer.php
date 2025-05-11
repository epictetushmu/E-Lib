<?php
/**
 * EPUB Viewer Component
 * Uses ePubJS to display EPUB e-books in the browser
 * 
 * This component works with the API-based DocumentViewer.php component
 * which handles book data fetching and provides the global bookId and book variables
 */
?>

<!-- EPUB Viewer Container -->
<div id="epubContainer" style="display: none; height: 90vh;">
    <div id="epubViewer" style="height: 100%;"></div>
    
    <!-- Navigation Controls -->
    <div class="epub-controls">
        <button id="prev" class="btn btn-light"><i class="fas fa-chevron-left"></i></button>
        <span id="currentPage" class="mx-2">0 of 0</span>
        <button id="next" class="btn btn-light"><i class="fas fa-chevron-right"></i></button>
    </div>
</div>

<!-- ePubJS Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.7.1/jszip.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/epubjs/dist/epub.min.js"></script>

<script>
    // Define initialization function that will be called by the parent DocumentViewer
    function initializeEpubViewer() {
        // EPUB.js initialization
        const spinner = document.getElementById('loadingSpinner');
        const epubContainer = document.getElementById('epubContainer');
        const authToken = localStorage.getItem('authToken') || sessionStorage.getItem('authToken') || '';
        let book, rendition;
        
        // Use the API endpoint to get the EPUB file
        const epubUrl = `/api/v1/books/${bookId}/file`;
        
        // Create a book object with specific request credentials
        book = ePub(epubUrl, {
            requestHeaders: {
                'Authorization': `Bearer ${authToken}`
            },
            requestCredentials: 'include',
            requestWithCredentials: true
        });
        
        // Initialize the rendition
        rendition = book.renderTo("epubViewer", {
            width: "100%",
            height: "100%",
            spread: "auto"
        });
        
        // Display the first page
        rendition.display().then(() => {
            // Hide spinner and show EPUB viewer
            spinner.style.display = 'none';
            epubContainer.style.display = 'block';
            
            // Update page information
            updatePageInfo();
        }).catch(error => {
            console.error('Error loading EPUB:', error);
            spinner.style.display = 'none';
            document.getElementById('errorContainer').style.display = 'block';
            document.getElementById('errorMessage').textContent = `Error loading EPUB. The file might be corrupted or incompatible. ${error.message || ''}`;
        });
        
        // Set up event listeners for page navigation
        document.getElementById("prev").addEventListener("click", () => {
            rendition.prev();
        });
        
        document.getElementById("next").addEventListener("click", () => {
            rendition.next();
        });
        
        // Listen for page changes
        rendition.on("relocated", location => {
            updatePageInfo(location);
        });
        
        // Update page information display
        function updatePageInfo(location) {
            if (!location) return;
            
            const currentPage = location.start.displayed.page;
            const totalPages = location.total;
            
            document.getElementById("currentPage").textContent = `${currentPage} of ${totalPages}`;
        }
        
        // Add keyboard controls
        document.addEventListener("keyup", event => {
            // Left arrow key
            if (event.key === "ArrowLeft") {
                rendition.prev();
            }
            
            // Right arrow key
            if (event.key === "ArrowRight") {
                rendition.next();
            }
        });
    }
</script>

<style>
    .epub-controls {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        background: rgba(255, 255, 255, 0.9);
        padding: 10px;
        text-align: center;
        border-top: 1px solid #ddd;
        z-index: 100;
    }
</style>