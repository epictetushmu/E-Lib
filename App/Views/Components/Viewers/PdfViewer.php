<?php
/**
 * PDF Viewer Component
 * Uses PDF.js to render PDF documents in the browser
 * 
 * This component works with the API-based DocumentViewer.php component
 * which handles book data fetching and provides the global bookId and book variables
 */
?>

<!-- PDF Canvas Container -->
<div class="pdf-container" style="height: 90vh; overflow-y: auto;" id="pdfContainer">
    <div id="pdfPages"></div>
</div>

<!-- PDF.js Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.5.141/pdf.min.js"></script>
<script>
    // Define initialization function that will be called by the parent DocumentViewer
    function initializePdfViewer() {
        // PDF.js initialization
        let pdfDoc = null;
        let scale = 1.5;
        let currentPage = 1;
        const pdfPagesContainer = document.getElementById('pdfPages');
        const spinner = document.getElementById('loadingSpinner');
        const pdfContainer = document.getElementById('pdfContainer');
        const authToken = localStorage.getItem('authToken') || sessionStorage.getItem('authToken') || '';
        
        // The workerSrc property needs to be specified
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.5.141/pdf.worker.min.js';
        
        // Load the PDF directly using authentication headers
        const pdfUrl = `/api/v1/books/${bookId}/file`;
        loadPDFWithAuth(pdfUrl, authToken);
        
        // Load PDF with authentication headers
        function loadPDFWithAuth(url, token) {
            
            // Configure the loading task with authentication headers
            const loadingTask = pdfjsLib.getDocument({
                url: url,
                httpHeaders: {
                    "Authorization": `Bearer ${token}`
                },
                withCredentials: true
            });
            
            loadingTask.promise
                .then(pdf => {
                    console.log(`PDF loaded successfully with ${pdf.numPages} pages`);
                    pdfDoc = pdf;
                    spinner.style.display = 'none';
                    pdfContainer.style.display = 'block';
                    
                    // Load first page
                    renderPage(1);
                    
                    // Add an intersection observer to lazy load PDF pages
                    setupIntersectionObserver();
                })
                .catch(error => {
                    console.error('Error loading PDF:', error);
                    handleError(error);
                });
        }
        
        // Handle errors when loading the PDF
        function handleError(error) {
            spinner.style.display = 'none';
            document.getElementById('errorContainer').style.display = 'block';
            document.getElementById('errorMessage').textContent = `Error loading PDF: ${error.message || 'Unknown error'}`;
        }
        
        // Render a specific page
        function renderPage(pageNumber) {
            pdfDoc.getPage(pageNumber).then(page => {
                const viewport = page.getViewport({ scale });
                
                // Create canvas for this page
                const canvasContainer = document.createElement('div');
                canvasContainer.className = 'pdf-page';
                canvasContainer.dataset.pageNumber = pageNumber;
                canvasContainer.style.position = 'relative';
                canvasContainer.style.margin = '20px auto';
                canvasContainer.style.boxShadow = '0 4px 8px rgba(0,0,0,0.1)';
                
                const canvas = document.createElement('canvas');
                canvasContainer.appendChild(canvas);
                pdfPagesContainer.appendChild(canvasContainer);
                
                const context = canvas.getContext('2d');
                canvas.height = viewport.height;
                canvas.width = viewport.width;
                
                // Add page number label
                const pageLabel = document.createElement('div');
                pageLabel.className = 'page-number';
                pageLabel.textContent = pageNumber;
                pageLabel.style.position = 'absolute';
                pageLabel.style.bottom = '10px';
                pageLabel.style.right = '10px';
                pageLabel.style.background = 'rgba(0,0,0,0.5)';
                pageLabel.style.color = 'white';
                pageLabel.style.padding = '5px 10px';
                pageLabel.style.borderRadius = '4px';
                pageLabel.style.fontSize = '14px';
                canvasContainer.appendChild(pageLabel);
                
                // Render the page
                const renderContext = {
                    canvasContext: context,
                    viewport
                };
                
                page.render(renderContext);
            });
        }
        
        // Set up intersection observer for lazy loading pages
        function setupIntersectionObserver() {
            // Create placeholders for all pages first
            for (let i = 1; i <= pdfDoc.numPages; i++) {
                if (i > 1) {  // Skip the first page as we already rendered it
                    const placeholder = document.createElement('div');
                    placeholder.className = 'pdf-page-placeholder';
                    placeholder.dataset.pageNumber = i;
                    placeholder.style.height = '800px';  // Approximate height
                    placeholder.style.margin = '20px auto';
                    placeholder.style.backgroundColor = '#f8f9fa';
                    placeholder.style.border = '1px solid #dee2e6';
                    placeholder.style.display = 'flex';
                    placeholder.style.justifyContent = 'center';
                    placeholder.style.alignItems = 'center';
                    placeholder.style.boxShadow = '0 4px 8px rgba(0,0,0,0.1)';
                    placeholder.innerHTML = `<span>Page ${i}</span>`;
                    pdfPagesContainer.appendChild(placeholder);
                }
            }
            
            // Set up the intersection observer to replace placeholders with actual rendered pages
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const pageNumber = parseInt(entry.target.dataset.pageNumber);
                        if (!entry.target.classList.contains('pdf-page')) {
                            // Replace placeholder with actual page
                            renderPage(pageNumber);
                            entry.target.remove(); // Remove the placeholder
                        }
                        observer.unobserve(entry.target);
                    }
                });
            }, {
                root: pdfContainer,
                rootMargin: '200px 0px',
                threshold: 0.1
            });
            
            // Observe all placeholders
            document.querySelectorAll('.pdf-page-placeholder').forEach(placeholder => {
                observer.observe(placeholder);
            });
        }
    }
</script>

<style>
    .pdf-container {
        background-color: #eee;
        padding: 20px 0;
        text-align: center;
    }
    
    .pdf-page {
        background-color: white;
        display: inline-block;
    }
    
    @media (max-width: 768px) {
        .pdf-container canvas {
            max-width: 100%;
            height: auto !important;
        }
    }
</style>