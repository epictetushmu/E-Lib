import { computed, ref } from 'vue'
import type { PageViewport } from 'pdfjs-dist'
import * as annotationsApi from '@/api/annotations'
import { isApiSuccess, apiErrorMessage, type ApiResponse } from '@/types/api'
import type { Annotation, HighlightColor, NewAnnotation } from '@/types/annotation'
import { useAuthStore } from '@/stores/auth'
import { placeOverPdfRect } from './pdfGeometry'

/** Page order, then top-to-bottom (PDF y grows upwards); page notes lead their page. */
function byPosition(a: Annotation, b: Annotation): number {
  const top = (x: Annotation) => (x.rects.length ? Math.max(...x.rects.map((r) => r[3])) : Infinity)
  return a.page - b.page || top(b) - top(a)
}

function unwrap<T>(body: ApiResponse<T>): T {
  if (isApiSuccess(body)) return body.data
  throw new Error(apiErrorMessage(body, 'Could not save your change'))
}

/** The signed-in reader's private highlights and notes for one book, kept on the server per user. */
export function useAnnotations(bookId: string) {
  const auth = useAuthStore()
  const items = ref<Annotation[]>([])
  const canAnnotate = computed(() => auth.isAuthenticated)
  const sorted = computed(() => [...items.value].sort(byPosition))

  async function load() {
    if (!canAnnotate.value) return
    const { data } = await annotationsApi.listAnnotations(bookId)
    if (isApiSuccess(data)) items.value = data.data
  }

  async function add(annotation: NewAnnotation): Promise<Annotation> {
    const created = unwrap((await annotationsApi.createAnnotation(bookId, annotation)).data)
    items.value = [...items.value, created]
    return created
  }

  async function update(id: string, changes: { note?: string; color?: HighlightColor }): Promise<Annotation> {
    const updated = unwrap((await annotationsApi.updateAnnotation(id, changes)).data)
    items.value = items.value.map((a) => (a.id === id ? updated : a))
    return updated
  }

  async function remove(id: string) {
    await annotationsApi.deleteAnnotation(id)
    items.value = items.value.filter((a) => a.id !== id)
  }

  /** Draws this page's highlights; `activeId` gets a frame while its popover is open. */
  function paint(pageNumber: number, wrapper: HTMLElement, viewport: PageViewport, activeId?: string) {
    wrapper.querySelectorAll('.user-highlight').forEach((el) => el.remove())
    for (const annotation of items.value) {
      if (annotation.page !== pageNumber || !annotation.rects.length) continue
      for (const rect of annotation.rects) {
        const mark = document.createElement('div')
        mark.className = `user-highlight hl-${annotation.color ?? 'saffron'}`
        if (annotation.id === activeId) mark.classList.add('is-active')
        placeOverPdfRect(mark, rect, viewport)
        wrapper.appendChild(mark)
      }
    }
  }

  /** The highlight under a PDF-space point on a page, if any (latest wins where they overlap). */
  function highlightAt(pageNumber: number, [x, y]: number[]): Annotation | undefined {
    return [...items.value]
      .reverse()
      .find((a) => a.page === pageNumber && a.rects.some(([x1, y1, x2, y2]) => x >= x1 && x <= x2 && y >= y1 && y <= y2))
  }

  return { items, sorted, canAnnotate, load, add, update, remove, paint, highlightAt }
}
