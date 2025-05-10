<?php
/**
 * PowerPoint Viewer Component
 * Uses Google Docs Viewer to display PowerPoint presentations
 * 
 * This component is expected to be included by the DocumentViewer.php component
 * which provides the bookId variable via JavaScript
 */
?>

<!-- PowerPoint Viewer -->
<div class="powerpoint-container" id="powerPointContainer" style="display: none; height: 100%;">
    <!-- This iframe will be populated with the secure URL via JavaScript -->
    <iframe 
        id="powerPointFrame"
        width="100%" 
        height="100%" 
        style="border: none;"
        frameborder="0"
        allowfullscreen>
    </iframe>
</div>

<script>
    // Get authentication token
    const authToken = localStorage.getItem('authToken') || sessionStorage.getItem('authToken') || '';
    const spinner = document.getElementById('loadingSpinner');
    const pptContainer = document.getElementById('powerPointContainer');
    const powerPointFrame = document.getElementById('powerPointFrame');
    
    // First get the secure file URL with authentication
    axios.get(`/api/v1/books/${bookId}/file`, {
        headers: {
            'Authorization': `Bearer ${authToken}`
        }
    })
    .then(response => {
        // Get the full URL to use in the Google Docs viewer
        const fileUrl = response.data.file_url || window.location.origin + `/api/v1/books/${bookId}/file`;
        
        // Set the iframe source with the Google Docs viewer
        powerPointFrame.src = `https://docs.google.com/viewer?url=${encodeURIComponent(fileUrl)}&embedded=true`;
        
        // Show container after setting the source
        setTimeout(() => {
            spinner.style.display = 'none';
            pptContainer.style.display = 'block';
        }, 2000); // Give the iframe a chance to load
    })
    .catch(error => {
        console.error('Error fetching PowerPoint URL:', error);
        // Show error message
        spinner.innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle fa-2x mb-3"></i>
                <p>Error loading PowerPoint file. The file might be corrupted or inaccessible.</p>
                <p class="small text-muted mt-2">${error.message || 'Unknown error'}</p>
                <div class="mt-3">
                    <button onclick="location.reload()" class="btn btn-sm btn-outline-danger me-2">Try Again</button>
                    <a href="/book/${bookId}" class="btn btn-sm btn-outline-secondary">View Book Details</a>
                </div>
            </div>
        `;
    });
    
    // Add event listener to iframe when it loads
    powerPointFrame.addEventListener('load', function() {
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
                    <a href="/download/${bookId}" class="btn btn-primary">
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