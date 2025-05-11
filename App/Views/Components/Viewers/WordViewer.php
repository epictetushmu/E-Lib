<?php
/**
 * Word Document Viewer Component
 * Uses mammoth.js to render Microsoft Word format documents (doc/docx) in the browser
 */
?>

<div id="wordReader" class="document-viewer">
    <!-- Word Document Reader Container -->
    <div id="wordViewerContent">
        <div id="wordDocumentContainer" class="word-document-container">
            <!-- Document content will be rendered here -->
        </div>
        
        <div id="fallbackMessage" class="text-center p-4" style="display: none;">
            <div class="alert alert-info">
                <i class="fas fa-file-word fa-2x mb-3"></i>
                <h5>Word Document Preview</h5>
                <p>We couldn't render this document in the browser.</p>
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
</div>

<!-- Mammoth.js Library for DOCX parsing -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/mammoth/1.6.0/mammoth.browser.min.js"></script>

<script>
function initializeWordViewer() {
    console.log("Initializing Word document viewer for book:", bookId);
    
    const authToken = localStorage.getItem('authToken') || sessionStorage.getItem('authToken') || '';
    const documentContainer = document.getElementById('wordDocumentContainer');
    const fallbackMessage = document.getElementById('fallbackMessage');
    const spinner = document.getElementById('loadingSpinner');
    
    // Set up download button
    document.getElementById('downloadWordBtn').addEventListener('click', function() {
        downloadDocument(book);
    });
    
    // If book doesn't allow downloads, hide the download button
    if (book.downloadable === false) {
        document.getElementById('downloadWordBtn').style.display = 'none';
    }
    
    // Fetch and render DOCX document
    fetchAndRenderDocument();
    
    function fetchAndRenderDocument() {
        const url = `/api/v1/books/${bookId}/file`;
        
        // Fetch the document with authorization
        fetch(url, {
            method: 'GET',
            headers: {
                'Authorization': `Bearer ${authToken}`
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.arrayBuffer();
        })
        .then(arrayBuffer => {
            // Use mammoth.js to convert the docx to html
            return mammoth.convertToHtml({ arrayBuffer: arrayBuffer });
        })
        .then(result => {
            // Display the rendered HTML
            documentContainer.innerHTML = result.value;
            
            // Apply styling to the rendered document
            applyDocumentStyling();
            
            // Hide spinner
            spinner.style.display = 'none';
            
            // Handle any warnings if needed
            if (result.messages.length > 0) {
                console.log("Mammoth warnings:", result.messages);
            }
        })
        .catch(error => {
            console.error("Error rendering Word document:", error);
            // Show fallback message if rendering fails
            documentContainer.style.display = 'none';
            fallbackMessage.style.display = 'block';
            spinner.style.display = 'none';
        });
    }
    
    function applyDocumentStyling() {
        // Add CSS classes to document elements for better styling
        const headings = documentContainer.querySelectorAll('h1, h2, h3, h4, h5, h6');
        headings.forEach(heading => {
            heading.classList.add('docx-heading');
        });
        
        const paragraphs = documentContainer.querySelectorAll('p');
        paragraphs.forEach(para => {
            para.classList.add('docx-paragraph');
        });
        
        const tables = documentContainer.querySelectorAll('table');
        tables.forEach(table => {
            table.classList.add('docx-table', 'table', 'table-bordered');
        });
        
        const images = documentContainer.querySelectorAll('img');
        images.forEach(img => {
            img.classList.add('docx-image');
            img.style.maxWidth = '100%';
        });
        
        const lists = documentContainer.querySelectorAll('ul, ol');
        lists.forEach(list => {
            list.classList.add('docx-list');
        });
    }
}
</script>

<style>
#wordReader {
    height: 100%;
    background-color: #ffffff;
    overflow-y: auto;
}

.word-document-container {
    padding: 30px;
    max-width: 800px;
    margin: 0 auto;
    background-color: #fff;
    box-shadow: 0 0 10px rgba(0,0,0,0.1);
    min-height: 90vh;
}

/* Document element styling */
.docx-heading {
    margin-top: 1.2em;
    margin-bottom: 0.8em;
    font-weight: 600;
    color: #333;
}

.docx-paragraph {
    margin-bottom: 1em;
    line-height: 1.6;
}

.docx-table {
    margin-bottom: 1.5em;
    width: 100%;
}

.docx-image {
    margin: 1em 0;
    display: block;
}

.docx-list {
    margin-bottom: 1em;
    padding-left: 1.5em;
}

/* Print styles */
@media print {
    .word-document-container {
        box-shadow: none;
        padding: 0;
    }
}
</style>