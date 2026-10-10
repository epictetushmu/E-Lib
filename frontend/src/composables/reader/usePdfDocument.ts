import { ref, shallowRef, type Ref } from 'vue'
import * as pdfjsLib from 'pdfjs-dist'
import type { PDFDocumentLoadingTask, PDFDocumentProxy, PageViewport } from 'pdfjs-dist'
import workerSrc from 'pdfjs-dist/build/pdf.worker.min.mjs?url'
import * as booksApi from '@/api/books'
import { requestErrorMessage } from '@/types/api'

pdfjsLib.GlobalWorkerOptions.workerSrc = workerSrc

export interface UsePdfDocumentOptions {
  scale: Ref<number>
  rotation: Ref<number>
  /** Called each time a page lands in the DOM (and on redecorate()), to draw overlays such as search hits. */
  decoratePage?: (pageNumber: number, wrapper: HTMLElement, viewport: PageViewport) => void
}

/** Loads a book's PDF via authenticated fetch, then lazily renders pages into a container element. */
export function usePdfDocument(bookId: string, options: UsePdfDocumentOptions) {
  const pdfDoc = shallowRef<PDFDocumentProxy | null>(null)
  const numPages = ref(0)
  const currentPage = ref(1)
  const loading = ref(true)
  const error = ref('')

  let loadingTask: PDFDocumentLoadingTask | null = null

  let container: HTMLElement | null = null
  let lazyObserver: IntersectionObserver | null = null
  let trackObserver: IntersectionObserver | null = null
  const viewports = new Map<number, PageViewport>()

  function setContainer(el: HTMLElement | null) {
    container = el
  }

  async function renderPage(pageNumber: number): Promise<HTMLElement | null> {
    const doc = pdfDoc.value
    if (!doc) return null

    const page = await doc.getPage(pageNumber)
    const viewport = page.getViewport({ scale: options.scale.value, rotation: options.rotation.value })

    const wrapper = document.createElement('div')
    wrapper.className = 'pdf-page'
    wrapper.dataset.pageNumber = String(pageNumber)

    const canvas = document.createElement('canvas')
    canvas.width = viewport.width
    canvas.height = viewport.height
    wrapper.appendChild(canvas)

    const label = document.createElement('div')
    label.className = 'pdf-page-label'
    label.textContent = String(pageNumber)
    wrapper.appendChild(label)
    viewports.set(pageNumber, viewport)

    const context = canvas.getContext('2d')
    if (context) {
      page.render({ canvasContext: context, canvas, viewport }).promise.catch((err) => {
        console.error('PDF page render error', err)
      })
    }

    return wrapper
  }

  function makePlaceholder(pageNumber: number): HTMLElement {
    const placeholder = document.createElement('div')
    placeholder.className = 'pdf-page-placeholder'
    placeholder.dataset.pageNumber = String(pageNumber)
    placeholder.textContent = `Page ${pageNumber}`
    return placeholder
  }

  function setupLazyRendering() {
    if (!container) return
    lazyObserver = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) return
          const target = entry.target as HTMLElement
          lazyObserver?.unobserve(target)
          const pageNumber = Number(target.dataset.pageNumber)
          renderPage(pageNumber).then((el) => {
            if (el) {
              target.replaceWith(el)
              decorate(el)
              trackObserver?.observe(el)
            }
          })
        })
      },
      { root: container, rootMargin: '200px 0px', threshold: 0.1 },
    )
    container.querySelectorAll('.pdf-page-placeholder').forEach((el) => lazyObserver?.observe(el))
  }

  function setupPageTracking() {
    if (!container) return
    trackObserver = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting && entry.intersectionRatio > 0.35) {
            const pageNumber = Number((entry.target as HTMLElement).dataset.pageNumber)
            if (!Number.isNaN(pageNumber)) currentPage.value = pageNumber
          }
        })
      },
      { root: container, threshold: [0.35, 0.5] },
    )
    container.querySelectorAll('.pdf-page').forEach((el) => trackObserver?.observe(el))
  }

  function decorate(wrapper: HTMLElement) {
    const pageNumber = Number(wrapper.dataset.pageNumber)
    const viewport = viewports.get(pageNumber)
    if (viewport) options.decoratePage?.(pageNumber, wrapper, viewport)
  }

  /** Re-runs decoratePage on every page currently rendered. */
  function redecorate() {
    container?.querySelectorAll<HTMLElement>('.pdf-page').forEach(decorate)
  }

  /** Re-renders page 1 immediately and re-queues the rest as lazy placeholders (used after zoom/rotate changes). */
  async function rebuildPages() {
    if (!pdfDoc.value || !container) return
    lazyObserver?.disconnect()
    trackObserver?.disconnect()
    container.innerHTML = ''
    viewports.clear()

    const first = await renderPage(1)
    if (first) {
      container.appendChild(first)
      decorate(first)
    }
    for (let pageNumber = 2; pageNumber <= numPages.value; pageNumber++) {
      container.appendChild(makePlaceholder(pageNumber))
    }

    setupLazyRendering()
    setupPageTracking()
  }

  async function load() {
    loading.value = true
    error.value = ''
    try {
      const response = await booksApi.getBookFile(bookId)
      loadingTask = pdfjsLib.getDocument({ data: response.data })
      const doc = await loadingTask.promise
      pdfDoc.value = doc
      numPages.value = doc.numPages
      await rebuildPages()
    } catch (err) {
      error.value = requestErrorMessage(err, 'Error loading PDF')
    } finally {
      loading.value = false
    }
  }

  function goToPage(pageNumber: number) {
    if (!container || pageNumber < 1 || pageNumber > numPages.value) return
    const el = container.querySelector(`[data-page-number="${pageNumber}"]`)
    // Scroll the page container only; scrollIntoView would also scroll the window past the toolbar.
    if (el) {
      container.scrollBy({ top: el.getBoundingClientRect().top - container.getBoundingClientRect().top, behavior: 'smooth' })
    }
    currentPage.value = pageNumber
  }

  async function getPageBaseWidth(pageNumber = 1): Promise<number> {
    if (!pdfDoc.value) return 0
    const page = await pdfDoc.value.getPage(pageNumber)
    return page.getViewport({ scale: 1, rotation: 0 }).width
  }

  function destroy() {
    lazyObserver?.disconnect()
    trackObserver?.disconnect()
    loadingTask?.destroy()
  }

  return {
    pdfDoc,
    numPages,
    currentPage,
    loading,
    error,
    setContainer,
    load,
    rebuildPages,
    goToPage,
    redecorate,
    getPageBaseWidth,
    destroy,
  }
}
