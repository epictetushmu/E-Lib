<?php
/**
 * DjVu Document Viewer Component
 * Handles rendering of DjVu format books
 */
?>

<div id="djvuReader" class="document-viewer">
    <!-- DjVu Reader Container -->
    <div class="text-center p-4" id="djvuUnsupported">
        <div class="alert alert-info">
            <i class="fas fa-file-image fa-2x mb-3"></i>
            <h5>DjVu Format Preview</h5>
            <p>DjVu is a specialized format often used for scanned documents and books.</p>
            <p>For optimal viewing, please download the file and open it with a compatible DjVu reader.</p>
        </div>
        <button id="downloadDjvuBtn" class="btn btn-primary">
            <i class="fas fa-download"></i> Download DjVu Format
        </button>
        <div class="mt-3">
            <p class="small text-muted">Recommended DjVu readers:</p>
            <ul class="list-unstyled small text-muted">
                <li>WinDjView (Windows)</li>
                <li>DjView (macOS, Linux)</li>
                <li>DjVu Browser Plugin (various browsers)</li>
            </ul>
        </div>
    </div>
</div>

<script>
function initializeDjvuViewer() {
    console.log("Initializing DjVu viewer for book:", bookId);
    
    // Hide loading spinner since we can't display DjVu format directly
    document.getElementById('loadingSpinner').style.display = 'none';
    
    // Set up download button
    document.getElementById('downloadDjvuBtn').addEventListener('click', function() {
        downloadDocument(book);
    });
    
    // If book doesn't allow downloads, hide the download button
    if (book.downloadable === false) {
        document.getElementById('downloadDjvuBtn').style.display = 'none';
    }
}
</script>

<style>
#djvuReader {
    height: 100%;
    background-color: #f8f9fa;
    display: flex;
    align-items: center;
    justify-content: center;
}

#djvuUnsupported {
    max-width: 500px;
    margin: 0 auto;
}

#djvuUnsupported .alert {
    display: flex;
    flex-direction: column;
    align-items: center;
}
</style>