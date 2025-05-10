<?php
/**
 * EPUB Viewer Component
 * Uses ePubJS to display EPUB e-books in the browser
 * 
 * This component is expected to be included by the DocumentViewer.php component
 * which provides the bookId variable via JavaScript
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
        spinner.innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle fa-2x mb-3"></i>
                <p>Error loading EPUB. The file might be corrupted or incompatible.</p>
                <p class="small text-muted mt-2">${error.message || 'Unknown error'}</p>
                <div class="mt-3">
                    <button onclick="location.reload()" class="btn btn-sm btn-outline-danger me-2">Try Again</button>
                    <a href="/book/${bookId}" class="btn btn-sm btn-outline-secondary">View Book Details</a>
                </div>
            </div>
        `;
    });
    
    // Set up event listeners for page navigation
    document.getElementById("prev").addEventListener("click", () => {
        rendition.prev();
    });
    
    document.getElementById("next").addEventListener("click", () => {
        rendition.next();
    });
    
    // Add keyboard navigation
    rendition.on("keyup", (event) => {
        if (event.key === "ArrowLeft") {
            rendition.prev();
        }
        if (event.key === "ArrowRight") {
            rendition.next();
        }
    });
    
    // Update page information display
    book.ready.then(() => {
        book.loaded.navigation.then(toc => {
            console.log(toc);
        });
    });
    
    // Update page info when location changes
    rendition.on("relocated", location => {
        updatePageInfo(location);
    });
    
    function updatePageInfo(location) {
        if (!location) {
            document.getElementById("currentPage").textContent = "Loading...";
            return;
        }
        
        const current = book.locations.locationFromCfi(location.start.cfi);
        const total = book.locations.total;
        
        if (current && total) {
            const percentage = Math.floor((current / total) * 100);
            document.getElementById("currentPage").textContent = `${percentage}% of book`;
        } else {
            document.getElementById("currentPage").textContent = 
                `Page ${location.start.displayed.page} of ${location.start.displayed.total}`;
        }
    }
</script>

<style>
    .epub-controls {
        position: fixed;
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%);
        background-color: rgba(255, 255, 255, 0.9);
        padding: 10px 20px;
        border-radius: 30px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        display: flex;
        align-items: center;
    }
</style>