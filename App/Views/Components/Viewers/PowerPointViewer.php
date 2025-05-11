<?php
/**
 * PowerPoint Viewer Component
 * Uses Google Docs Viewer to display PowerPoint presentations
 * 
 * This component works with the API-based DocumentViewer.php component
 * which handles book data fetching and provides the global bookId and book variables
 */
?>

<!-- PowerPoint Viewer -->
<div class="powerpoint-container" id="powerPointContainer">
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
    // Define initialization function that will be called by the parent DocumentViewer
    function initializePowerpointViewer() {
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
            spinner.style.display = 'none';
            document.getElementById('errorContainer').style.display = 'block';
            document.getElementById('errorMessage').textContent = 'Error loading PowerPoint file. The file might be corrupted or inaccessible.';
        });
        
        // Add event listener to iframe when it loads
        powerPointFrame.addEventListener('load', function() {
            spinner.style.display = 'none';
            pptContainer.style.display = 'block';
        });
        
        // Fallback if iframe fails to load after 8 seconds
        setTimeout(() => {
            if (spinner.style.display !== 'none') {
                spinner.style.display = 'none';
                document.getElementById('errorContainer').style.display = 'block';
                document.getElementById('errorMessage').textContent = 'Preview taking longer than expected. The presentation may not be compatible with the viewer.';
                
                // Add download option
                if (book.downloadable !== false) {
                    const downloadBtn = document.createElement('a');
                    downloadBtn.href = "#";
                    downloadBtn.className = 'btn btn-primary mt-3';
                    downloadBtn.innerHTML = '<i class="fas fa-download"></i> Download to view';
                    downloadBtn.onclick = function(e) {
                        e.preventDefault();
                        // Make sure we're using the book ID string, not the entire object
                        const downloadId = bookId || book.id.$oid;
                        
                        axios({
                            method: 'get',
                            url: `/api/v1/books/${downloadId}/download`,
                            responseType: 'blob',
                            headers: {
                                'Authorization': `Bearer ${authToken}`
                            }
                        })
                        .then(response => {
                            const url = window.URL.createObjectURL(new Blob([response.data]));
                            const link = document.createElement('a');
                            link.href = url;
                            link.setAttribute('download', `${book.title || 'presentation'}.${book.file_extension || 'ppt'}`);
                            document.body.appendChild(link);
                            link.click();
                            link.remove();
                        });
                    };
                    document.getElementById('errorContainer').appendChild(downloadBtn);
                }
            }
        }, 8000);
    }
</script>

<style>
    .powerpoint-container {
        width: 100%;
        height: 90vh;
        display: none;
    }
</style>