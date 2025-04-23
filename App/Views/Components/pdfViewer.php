<div class="container my-4">
    <h2><?= htmlspecialchars($book['title'] ?? 'Untitled') ?></h2>

    <!-- PDF Viewer -->
    <div id="pdfViewer">
        <div class="toolbar mb-3">
            <div class="btn-group">
                <button class="btn btn-primary btn-sm" id="prevPage">
                    <i class="fas fa-arrow-left me-1"></i> Previous
                </button>
                <button class="btn btn-primary btn-sm" id="nextPage">
                    Next <i class="fas fa-arrow-right ms-1"></i>
                </button>
            </div>
            <span class="mx-3">Page: <span id="pageNum">1</span> / <span id="pageCount">--</span></span>
            <div class="btn-group ms-2">
                <button class="btn btn-outline-secondary btn-sm" id="zoomOut">
                    <i class="fas fa-search-minus"></i>
                </button>
                <button class="btn btn-outline-secondary btn-sm" id="zoomIn">
                    <i class="fas fa-search-plus"></i>
                </button>
            </div>
        </div>
        <div class="pdf-container">
            <canvas id="pdfCanvas" class="shadow"></canvas>
        </div>
    </div>
</div>

<!-- Load PDF.js from CDN -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.5.141/pdf.min.js"></script>

<script>
    const url = "<?= htmlspecialchars($book['pdf_path'] ?? '/path/to/default.pdf') ?>";

    let pdfDoc = null,
        pageNum = 1,
        pageRendering = false,
        canvas = document.getElementById('pdfCanvas'),
        ctx = canvas.getContext('2d'),
        scale = 1.5;

    const renderPage = (num) => {
        pageRendering = true;
        pdfDoc.getPage(num).then(page => {
            const viewport = page.getViewport({ scale: scale });
            canvas.height = viewport.height;
            canvas.width = viewport.width;

            const renderContext = {
                canvasContext: ctx,
                viewport: viewport
            };
            page.render(renderContext).promise.then(() => {
                pageRendering = false;
            });

            document.getElementById('pageNum').textContent = num;
        });
    };

    const queueRenderPage = (num) => {
        if (pageRendering) return;
        renderPage(num);
    };

    const onPrevPage = () => {
        if (pageNum <= 1) return;
        pageNum--;
        queueRenderPage(pageNum);
    };

    const onNextPage = () => {
        if (pageNum >= pdfDoc.numPages) return;
        pageNum++;
        queueRenderPage(pageNum);
    };

    const onZoomIn = () => {
        scale += 0.1;
        renderPage(pageNum);
    };

    const onZoomOut = () => {
        if (scale <= 0.5) return;
        scale -= 0.1;
        renderPage(pageNum);
    };

    document.getElementById('prevPage').addEventListener('click', onPrevPage);
    document.getElementById('nextPage').addEventListener('click', onNextPage);
    document.getElementById('zoomIn').addEventListener('click', onZoomIn);
    document.getElementById('zoomOut').addEventListener('click', onZoomOut);

    pdfjsLib.getDocument(url).promise.then(pdfDoc_ => {
        pdfDoc = pdfDoc_;
        document.getElementById('pageCount').textContent = pdfDoc.numPages;
        renderPage(pageNum);
    });
</script>