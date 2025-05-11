<?php
/**
 * Kindle (MOBI/AZW) Document Viewer Component
 * Handles rendering of Kindle format books
 */
?>

<div id="kindleReader" class="document-viewer">
    <!-- Kindle Reader Container -->
    <div class="text-center p-4" id="kindleUnsupported">
        <div class="alert alert-info">
            <i class="fas fa-tablet fa-2x mb-3"></i>
            <h5>Kindle Format Preview</h5>
            <p>Kindle format files (MOBI/AZW) cannot be directly previewed in the browser.</p>
            <p>You can download the file to read on a Kindle device or Kindle app.</p>
        </div>
        <button id="downloadKindleBtn" class="btn btn-primary">
            <i class="fas fa-download"></i> Download Kindle Format
        </button>
    </div>
</div>

<script>
function initializeKindleViewer() {
    console.log("Initializing Kindle viewer for book:", bookId);
    
    // Hide loading spinner since we can't display Kindle format directly
    document.getElementById('loadingSpinner').style.display = 'none';
    
    // Set up download button
    document.getElementById('downloadKindleBtn').addEventListener('click', function() {
        downloadDocument(book);
    });
    
    // If book doesn't allow downloads, hide the download button
    if (book.downloadable === false) {
        document.getElementById('downloadKindleBtn').style.display = 'none';
    }
}
</script>

<style>
#kindleReader {
    height: 100%;
    background-color: #f8f9fa;
    display: flex;
    align-items: center;
    justify-content: center;
}

#kindleUnsupported {
    max-width: 500px;
    margin: 0 auto;
}

#kindleUnsupported .alert {
    display: flex;
    flex-direction: column;
    align-items: center;
}
</style>