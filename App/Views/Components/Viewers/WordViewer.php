<?php
/**
 * Word Document Viewer Component
 * Handles rendering of Microsoft Word format documents (doc/docx)
 */
?>

<div id="wordReader" class="document-viewer">
    <!-- Word Document Reader Container -->
    <div class="text-center p-4" id="wordViewerContent">
        <div class="alert alert-info">
            <i class="fas fa-file-word fa-2x mb-3"></i>
            <h5>Word Document Preview</h5>
            <p>Microsoft Word documents cannot be directly previewed in the browser without additional services.</p>
            <p>You can download the file to view it with Microsoft Word or a compatible word processor.</p>
        </div>
        <div class="mb-4">
            <button id="downloadWordBtn" class="btn btn-primary">
                <i class="fas fa-download"></i> Download Document
            </button>
        </div>
        <div class="mt-3">
            <p class="small text-muted">Compatible applications:</p>
            <ul class="list-unstyled small text-muted">
                <li>Microsoft Word</li>
                <li>Google Docs</li>
                <li>LibreOffice Writer</li>
                <li>Apple Pages</li>
            </ul>
        </div>
    </div>
</div>

<script>
function initializeWordViewer() {
    console.log("Initializing Word document viewer for book:", bookId);
    
    // Hide loading spinner since we can't display Word documents directly
    document.getElementById('loadingSpinner').style.display = 'none';
    
    // Set up download button
    document.getElementById('downloadWordBtn').addEventListener('click', function() {
        downloadDocument(book);
    });
    
    // If book doesn't allow downloads, hide the download button
    if (book.downloadable === false) {
        document.getElementById('downloadWordBtn').style.display = 'none';
    }
}
</script>

<style>
#wordReader {
    height: 100%;
    background-color: #f8f9fa;
    display: flex;
    align-items: center;
    justify-content: center;
}

#wordViewerContent {
    max-width: 500px;
    margin: 0 auto;
}

#wordViewerContent .alert {
    display: flex;
    flex-direction: column;
    align-items: center;
}
</style>