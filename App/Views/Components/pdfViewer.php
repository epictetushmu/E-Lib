<div class="container my-4">
        <h2><?= htmlspecialchars($book['title'] ?? 'Untitled') ?></h2>

        <!-- PDF Viewer -->
        <div id="pdfViewer">
            <div class="mb-2">
                <button class="btn btn-secondary btn-sm" id="prevPage">Previous</button>
                <button class="btn btn-secondary btn-sm" id="nextPage">Next</button>
                <span class="mx-2">Page: <span id="pageNum">1</span> / <span id="pageCount">--</span></span>
            </div>
            <canvas id="pdfCanvas"></canvas>
        </div>
    </div>

    <script>
        const url = "<?= htmlspecialchars($book['pdf_url'] ?? '/path/to/default.pdf') ?>";

        let pdfDoc = null,
            pageNum = 1,
            pageRendering = false,
            canvas = document.getElementById('pdfCanvas'),
            ctx = canvas.getContext('2d');

        const renderPage = (num) => {
            pageRendering = true;
            pdfDoc.getPage(num).then(page => {
                const viewport = page.getViewport({ scale: 1.5 });
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

        document.getElementById('prevPage').addEventListener('click', onPrevPage);
        document.getElementById('nextPage').addEventListener('click', onNextPage);

        pdfjsLib.getDocument(url).promise.then(pdfDoc_ => {
            pdfDoc = pdfDoc_;
            document.getElementById('pageCount').textContent = pdfDoc.numPages;
            renderPage(pageNum);
        });
    </script>