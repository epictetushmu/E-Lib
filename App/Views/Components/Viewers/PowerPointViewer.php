<?php
/**
 * PowerPoint Viewer Component
 * Uses Google Docs Viewer to display PowerPoint presentations
 * 
 * This component is expected to be included by the DocumentViewer.php component
 * which provides the $book and $filePath variables
 */

// Ensure we have the file path - need the full URL for Google Docs Viewer
$fileUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" .
           ($filePath ?? $book['pdf_path'] ?? $book['file_path'] ?? '');
$fileUrl = htmlspecialchars($fileUrl);
?>

<!-- PowerPoint Viewer -->
<div class="powerpoint-container" id="powerPointContainer" style="display: none; height: 100%;">
    <iframe 
        src="https://docs.google.com/viewer?url=<?= urlencode($fileUrl) ?>&embedded=true" 
        width="100%" 
        height="100%" 
        style="border: none;"
        frameborder="0"
        allowfullscreen>
    </iframe>
</div>

<script>
    // Wait for iframe to load, then hide spinner
    const spinner = document.getElementById('loadingSpinner');
    const pptContainer = document.getElementById('powerPointContainer');
    
    // Show container after a short delay
    setTimeout(() => {
        spinner.style.display = 'none';
        pptContainer.style.display = 'block';
    }, 2000); // Give the iframe a chance to load
    
    // Add event listener to iframe when it loads
    document.querySelector('#powerPointContainer iframe').addEventListener('load', function() {
        spinner.style.display = 'none';
        pptContainer.style.display = 'block';
    });
    
    // Fallback if iframe fails to load after 8 seconds
    setTimeout(() => {
        if (spinner.style.display !== 'none') {
            spinner.innerHTML = `
                <div class="text-center">
                    <div class="mb-4"><i class="fas fa-exclamation-circle fa-3x text-warning"></i></div>
                    <h4>Preview taking longer than expected</h4>
                    <p class="text-muted">The presentation may not be compatible with the viewer.</p>
                    <a href="/download/<?= $book['_id'] ?? '' ?>" class="btn btn-primary">
                        <i class="fas fa-download"></i> Download to view
                    </a>
                </div>
            `;
        }
    }, 8000);
</script>

<style>
    .powerpoint-container {
        width: 100%;
        height: 90vh;
    }
</style>