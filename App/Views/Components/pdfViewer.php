<div class="container my-4">
    <h2><?= htmlspecialchars($book['title'] ?? 'Untitled') ?></h2>

    <!-- Toolbar -->
    <div id="pdfViewer">
        <div class="toolbar mb-3 d-flex flex-wrap align-items-center">
            <div class="btn-group mb-2 me-2">
                <button class="btn btn-primary btn-sm" id="prevPage" aria-label="Previous Page">
                    <i class="fas fa-arrow-left me-1"></i> Prev
                </button>
                <button class="btn btn-primary btn-sm" id="nextPage" aria-label="Next Page">
                    Next <i class="fas fa-arrow-right ms-1"></i>
                </button>
            </div>

            <span class="mx-3 mb-2">Page: <span id="pageNum">1</span> / <span id="pageCount">--</span></span>

            <input type="number" id="gotoPage" class="form-control form-control-sm mb-2 me-2" style="width: 80px;" placeholder="Page #" min="1" aria-label="Go to page">

            <div class="btn-group mb-2 me-2">
                <button class="btn btn-outline-secondary btn-sm" id="zoomOut" aria-label="Zoom Out">
                    <i class="fas fa-search-minus"></i>
                </button>
                <button class="btn btn-outline-secondary btn-sm" id="zoomIn" aria-label="Zoom In">
                    <i class="fas fa-search-plus"></i>
                </button>
            </div>

            <div class="btn-group mb-2 ms-auto">
                <a href="<?= htmlspecialchars($book['pdf_path']) ?>" class="btn btn-secondary btn-sm" download aria-label="Download PDF">
                    <i class="fas fa-download"></i>
                </a>
                <button class="btn btn-outline-dark btn-sm" id="fullscreenBtn" aria-label="Fullscreen">
                    <i class="fas fa-expand"></i>
                </button>
            </div>
        </div>

        <!-- Loading Spinner -->
        <div id="loadingSpinner" class="text-center my-3" style="display: none;">
            <div class="spinner-border text-primary" role="status"></div>
        </div>

        <!-- PDF Canvas -->
      <!-- PDF Scroll Container -->
        <div class="pdf-container" style="height: 90vh; overflow-y: auto;">
            <div id="pdfPages"></div>
        </div>

    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.5.141/pdf.min.js"></script>
<script>
    const url = "<?= htmlspecialchars($book['pdf_path'] ?? '/path/to/default.pdf') ?>";

    let pdfDoc = null;
    let scale = 1.5;
    let currentPage = 1;
    const pdfPagesContainer = document.getElementById('pdfPages');
    const spinner = document.getElementById('loadingSpinner');

    const showSpinner = (show) => spinner.style.display = show ? 'block' : 'none';

    function updateNavButtons() {
        document.getElementById('pageNum').textContent = currentPage;
        document.getElementById('prevPage').disabled = currentPage <= 1;
        document.getElementById('nextPage').disabled = currentPage >= pdfDoc.numPages;
    }

    function scrollToPage(pageNum) {
        const pageCanvas = document.querySelector(`#page-${pageNum}`);
        if (pageCanvas) {
            pageCanvas.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    function renderAllPages() {
        showSpinner(true);
        pdfPagesContainer.innerHTML = '';
        const promises = [];

        for (let pageNum = 1; pageNum <= pdfDoc.numPages; pageNum++) {
            promises.push(
                pdfDoc.getPage(pageNum).then(page => {
                    const viewport = page.getViewport({ scale });
                    const canvas = document.createElement('canvas');
                    canvas.className = 'mb-4 shadow-sm rounded w-100';
                    canvas.id = `page-${pageNum}`;
                    canvas.width = viewport.width;
                    canvas.height = viewport.height;

                    const context = canvas.getContext('2d');
                    const renderContext = { canvasContext: context, viewport };

                    pdfPagesContainer.appendChild(canvas);
                    return page.render(renderContext).promise;
                })
            );
        }

        Promise.all(promises).then(() => {
            showSpinner(false);
            scrollToPage(currentPage);
        });
    }

    // Navigation buttons
    document.getElementById('prevPage').addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage--;
            scrollToPage(currentPage);
            updateNavButtons();
        }
    });

    document.getElementById('nextPage').addEventListener('click', () => {
        if (currentPage < pdfDoc.numPages) {
            currentPage++;
            scrollToPage(currentPage);
            updateNavButtons();
        }
    });

    document.getElementById('gotoPage').addEventListener('change', (e) => {
        const val = parseInt(e.target.value);
        if (!isNaN(val) && val >= 1 && val <= pdfDoc.numPages) {
            currentPage = val;
            scrollToPage(currentPage);
            updateNavButtons();
        }
    });

    document.getElementById('zoomIn').addEventListener('click', () => {
        scale += 0.1;
        renderAllPages();
    });

    document.getElementById('zoomOut').addEventListener('click', () => {
        if (scale > 0.5) {
            scale -= 0.1;
            renderAllPages();
        }
    });

    document.getElementById('fullscreenBtn').addEventListener('click', () => {
        const viewer = document.getElementById('pdfViewer');
        if (document.fullscreenElement) {
            document.exitFullscreen();
        } else {
            viewer.requestFullscreen();
        }
    });

    // Load the PDF
    pdfjsLib.getDocument(url).promise.then(pdf => {
        pdfDoc = pdf;
        document.getElementById('pageCount').textContent = pdfDoc.numPages;
        renderAllPages();
        updateNavButtons();
    }).catch(error => {
        showSpinner(false);
        alert('Failed to load PDF.');
        console.error(error);
    });
</script>
