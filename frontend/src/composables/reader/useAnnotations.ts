import { computed, ref } from 'vue'
import type { PageViewport } from 'pdfjs-dist'
import * as annotationsApi from '@/api/annotations'
import { isApiSuccess, apiErrorMessage, type ApiResponse } from '@/types/api'
import type { Annotation, FriendAnnotation, HighlightColor, NewAnnotation } from '@/types/annotation'
import { useAuthStore } from '@/stores/auth'
import { placeOverPdfRect } from './pdfGeometry'

/** Page order, then top-to-bottom (PDF y grows upwards); page notes lead their page. */
function byPosition<T extends Annotation>(a: T, b: T): number {
  const top = (x: T) => (x.rects.length ? Math.max(...x.rects.map((r) => r[3])) : Infinity)
  return a.page - b.page || top(b) - top(a)
}

function unwrap<T>(body: ApiResponse<T>): T {
  if (isApiSuccess(body)) return body.data
  throw new Error(apiErrorMessage(body, 'Could not save your change'))
}

function hits(a: Annotation, pageNumber: number, [x, y]: number[]): boolean {
  return a.page === pageNumber && a.rects.some(([x1, y1, x2, y2]) => x >= x1 && x <= x2 && y >= y1 && y <= y2)
}

const SHOW_FRIENDS_KEY = 'reader:showFriendNotes'

/**
 * The signed-in reader's highlights and notes for one book, kept on the server per user — plus, read-only,
 * those of friends who share theirs, drawn as underlines the reader can hide.
 */
export function useAnnotations(bookId: string) {
  const auth = useAuthStore()
  const items = ref<Annotation[]>([])
  const friendItems = ref<FriendAnnotation[]>([])
  const showFriends = ref(readShowFriends())
  const canAnnotate = computed(() => auth.isAuthenticated)
  const sorted = computed(() => [...items.value].sort(byPosition))
  const friendsSorted = computed(() => [...friendItems.value].sort(byPosition))

  function readShowFriends(): boolean {
    try {
      return localStorage.getItem(SHOW_FRIENDS_KEY) !== 'off'
    } catch {
      return true
    }
  }

  function setShowFriends(on: boolean) {
    showFriends.value = on
    try {
      localStorage.setItem(SHOW_FRIENDS_KEY, on ? 'on' : 'off')
    } catch {
      // Private mode: the choice lasts for this visit only.
    }
  }

  async function load() {
    if (!canAnnotate.value) return
    const [own, friends] = await Promise.allSettled([
      annotationsApi.listAnnotations(bookId),
      annotationsApi.listFriendAnnotations(bookId),
    ])
    // Friends' notes are extra: if they fail to load, the reader still has its own.
    if (friends.status === 'fulfilled' && isApiSuccess(friends.value.data)) friendItems.value = friends.value.data.data
    if (own.status === 'rejected') throw own.reason
    if (isApiSuccess(own.value.data)) items.value = own.value.data.data
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

  /** Draws this page's highlights; `activeId` gets a frame while its popover is open. Friends' go underneath. */
  function paint(pageNumber: number, wrapper: HTMLElement, viewport: PageViewport, activeId?: string) {
    wrapper.querySelectorAll('.user-highlight, .friend-highlight').forEach((el) => el.remove())
    if (showFriends.value) {
      for (const annotation of friendItems.value) {
        if (annotation.page !== pageNumber) continue
        for (const rect of annotation.rects) {
          const mark = document.createElement('div')
          mark.className = 'friend-highlight'
          placeOverPdfRect(mark, rect, viewport)
          wrapper.appendChild(mark)
        }
      }
    }
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
  function highlightAt(pageNumber: number, point: number[]): Annotation | undefined {
    return [...items.value].reverse().find((a) => hits(a, pageNumber, point))
  }

  /** A friend's highlight under the point, while friends' notes are shown. */
  function friendHighlightAt(pageNumber: number, point: number[]): FriendAnnotation | undefined {
    return showFriends.value ? friendItems.value.find((a) => hits(a, pageNumber, point)) : undefined
  }

  return {
    items,
    sorted,
    friendItems,
    friendsSorted,
    showFriends,
    setShowFriends,
    canAnnotate,
    load,
    add,
    update,
    remove,
    paint,
    highlightAt,
    friendHighlightAt,
  }
}
