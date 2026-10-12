<script setup lang="ts">
import { computed, defineAsyncComponent, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { usePdfDocument } from '@/composables/reader/usePdfDocument'
import { useZoomRotate, DEFAULT_SCALE } from '@/composables/reader/useZoomRotate'
import { useReaderSearch } from '@/composables/reader/useReaderSearch'
import { useAnnotations } from '@/composables/reader/useAnnotations'
import { clientPointToPdf, clientRectToPdf, mergeLineRects } from '@/composables/reader/pdfGeometry'
import { getSavedPanelWidth, setSavedPanelWidth, useReaderPersistence } from '@/composables/reader/useReaderPersistence'
import { useToast } from '@/composables/useToast'
import * as booksApi from '@/api/books'
import * as friendsApi from '@/api/friends'
import type { Book } from '@/types/book'
import { isApiSuccess, requestErrorMessage } from '@/types/api'
import type { Annotation, HighlightColor, NewAnnotation } from '@/types/annotation'
import AnnotationPopover from '@/components/AnnotationPopover.vue'
import NoteEditor from '@/components/NoteEditor.vue'

// Markdown/maths/code rendering is a separate chunk, fetched once the notes panel shows a note.
const NoteContent = defineAsyncComponent(() => import('@/components/NoteContent.vue'))

const route = useRoute()
const toast = useToast()
const bookId = route.params.id as string

const persistence = useReaderPersistence(bookId)
const savedZoom = persistence.getSavedZoom()

const zoomRotate = useZoomRotate(savedZoom ?? DEFAULT_SCALE)
const pdf = usePdfDocument(bookId, {
  scale: zoomRotate.scale,
  rotation: zoomRotate.rotation,
  decoratePage: (pageNumber, wrapper, viewport) => {
    annotations.paint(pageNumber, wrapper, viewport, popover.value?.annotation?.id)
    search.paintHits(pageNumber, wrapper, viewport)
  },
})
const search = useReaderSearch(pdf.pdfDoc)
const annotations = useAnnotations(bookId)
const loginPath = `/login?redirect=${encodeURIComponent(route.fullPath)}`

const readerShell = ref<HTMLElement | null>(null)
const pagesContainer = ref<HTMLElement | null>(null)
const searchInput = ref<HTMLInputElement | null>(null)

const themes = [
  { id: 'light', label: 'Paper' },
  { id: 'sepia', label: 'Sepia' },
  { id: 'dark', label: 'Night' },
] as const
const theme = ref<(typeof themes)[number]['id']>('light')
const themeClass = computed(() => `theme-${theme.value}`)

const book = ref<Book | null>(null)
const progress = computed(() => (pdf.numPages.value ? (pdf.currentPage.value / pdf.numPages.value) * 100 : 0))

const pageInput = ref(1)
const notesOpen = ref(false)

/* Notes panel width: dragged by its left edge, remembered across books in this browser. */
const PANEL_DEFAULT = 400
const PANEL_MIN = 280
const savedPanelWidth = getSavedPanelWidth()
const panelWidth = ref(savedPanelWidth !== null && savedPanelWidth >= PANEL_MIN ? savedPanelWidth : PANEL_DEFAULT)
const resizingPanel = ref(false)

/** Keeps at least a quarter of the reader for the book itself. */
function setPanelWidth(width: number) {
  const max = Math.max(PANEL_MIN, (readerShell.value?.clientWidth ?? window.innerWidth) * 0.75)
  panelWidth.value = Math.round(Math.min(max, Math.max(PANEL_MIN, width)))
  setSavedPanelWidth(panelWidth.value)
}

function startPanelResize(event: PointerEvent) {
  const handle = event.currentTarget as HTMLElement
  const startX = event.clientX
  const startWidth = panelWidth.value
  handle.setPointerCapture(event.pointerId)
  resizingPanel.value = true
  // The panel grows leftwards, so dragging left (negative dx) widens it.
  const move = (e: PointerEvent) => setPanelWidth(startWidth - (e.clientX - startX))
  const listeners = new AbortController()
  const stop = () => {
    resizingPanel.value = false
    listeners.abort()
  }
  handle.addEventListener('pointermove', move, { signal: listeners.signal })
  handle.addEventListener('pointerup', stop, { signal: listeners.signal })
  handle.addEventListener('pointercancel', stop, { signal: listeners.signal })
}

function handlePanelResizeKey(event: KeyboardEvent) {
  const step = event.shiftKey ? 80 : 20
  if (event.key === 'ArrowLeft') setPanelWidth(panelWidth.value + step)
  else if (event.key === 'ArrowRight') setPanelWidth(panelWidth.value - step)
  else if (event.key === 'Home') setPanelWidth(PANEL_DEFAULT)
  else return
  event.preventDefault()
}
const noteText = ref('')

const zoomPercent = computed(() => Math.round(zoomRotate.scale.value * 100))

watch(pdf.currentPage, (page) => {
  pageInput.value = page
  persistence.setSavedPage(page)
})

async function applyZoom(action: () => void) {
  action()
  persistence.setSavedZoom(zoomRotate.scale.value)
  await pdf.rebuildPages()
}

async function handleFitWidth() {
  if (!pagesContainer.value) return
  const containerWidth = pagesContainer.value.clientWidth - 48
  const baseWidth = await pdf.getPageBaseWidth(1)
  await applyZoom(() => zoomRotate.fitWidth(containerWidth, baseWidth))
}

async function handleRotate() {
  zoomRotate.rotate()
  await pdf.rebuildPages()
}

function handleGoToPage() {
  pdf.goToPage(pageInput.value)
}

/** Enter runs the search, then steps through matches; Shift+Enter steps backwards. */
async function handleSearchKey(event: KeyboardEvent) {
  if (event.key !== 'Enter') return
  event.preventDefault()
  if (search.matches.value.length) {
    search.step(event.shiftKey ? -1 : 1)
  } else {
    await search.search()
  }
}

// Repaint hits whenever the result set or the current match changes, and bring the current one into view.
watch([search.matches, search.current], () => {
  const match = search.matches.value[search.current.value]
  if (match) search.requestScrollToCurrent()
  pdf.redecorate()
  // Its page is still a placeholder: scroll there, and the hit scrolls into view once the page renders.
  if (match && !pagesContainer.value?.querySelector('.search-hit.is-current')) {
    pdf.goToPage(match.page)
  }
})

/* Highlights and notes */

interface PopoverState {
  mode: 'new' | 'edit' | 'guest' | 'multipage'
  left: number
  top: number
  placeAbove: boolean
  pending?: NewAnnotation
  annotation?: Annotation
}

const popover = ref<PopoverState | null>(null)
const popoverBusy = ref(false)

watch([annotations.items, annotations.friendItems, annotations.hiddenFriends, popover], () => pdf.redecorate())

/* Friends' notes: read-only. Clicking one of their underlines opens it in the notes panel. */
const focusedFriendNote = ref<string | null>(null)
// Whether the reader's own notes are shared, so the panel says honestly who can see them.
const sharingMyNotes = ref(false)

async function showFriendNote(id: string) {
  notesOpen.value = true
  focusedFriendNote.value = id
  await nextTick()
  document.getElementById(`friend-note-${id}`)?.scrollIntoView({ block: 'nearest' })
}

/** Below the anchor, or above it when there isn't room; kept clear of the window edges. */
function anchorFor(box: { left: number; right: number; top: number; bottom: number }) {
  const placeAbove = box.bottom + 160 > window.innerHeight
  return {
    placeAbove,
    top: placeAbove ? box.top - 8 : box.bottom + 8,
    left: Math.min(Math.max((box.left + box.right) / 2, 150), window.innerWidth - 150),
  }
}

function pageOf(node: Node): HTMLElement | null {
  return (node instanceof Element ? node : node.parentElement)?.closest<HTMLElement>('.pdf-page') ?? null
}

function openForSelection(range: Range) {
  const page = pageOf(range.startContainer)
  if (!page?.querySelector('.textLayer')?.contains(range.startContainer)) return
  const box = range.getBoundingClientRect()
  if (!annotations.canAnnotate.value) {
    popover.value = { mode: 'guest', ...anchorFor(box) }
    return
  }
  const endPage = pageOf(range.endContainer)
  if (endPage && endPage !== page) {
    popover.value = { mode: 'multipage', ...anchorFor(box) }
    return
  }

  const pageNumber = Number(page.dataset.pageNumber)
  const viewport = pdf.viewportFor(pageNumber)
  if (!viewport) return
  const pageBox = page.getBoundingClientRect()
  let lines = mergeLineRects([...range.getClientRects()]).filter((r) => r.bottom > pageBox.top && r.top < pageBox.bottom)
  // A table or columns leave many separate pieces per line; past the server's 200 (Annotations::MAX_RECTS), highlight whole rows.
  if (lines.length > 200) lines = mergeLineRects(lines, true)
  if (!lines.length) return

  popover.value = {
    mode: 'new',
    ...anchorFor(box),
    pending: {
      page: pageNumber,
      rects: lines.map((r) => clientRectToPdf(r, page, viewport)),
      // The selection's text keeps pdf.js's line breaks (<br>) as whitespace; range.toString() drops them.
      quote: (window.getSelection()?.toString() ?? range.toString()).replace(/\s+/g, ' ').trim().slice(0, 2000),
      note: '',
      color: 'saffron',
    },
  }
}

/** After a drag, offer to highlight the selection; after a plain click, open the highlight under it. */
function handlePagePointerUp(event: PointerEvent) {
  requestAnimationFrame(() => {
    const selection = window.getSelection()
    if (selection && !selection.isCollapsed && selection.rangeCount) {
      openForSelection(selection.getRangeAt(0))
      return
    }
    const page = (event.target as HTMLElement).closest<HTMLElement>('.pdf-page')
    const pageNumber = Number(page?.dataset.pageNumber)
    const viewport = pdf.viewportFor(pageNumber)
    if (!page || !viewport) return
    const hit = annotations.highlightAt(pageNumber, clientPointToPdf(event.clientX, event.clientY, page, viewport))
    if (hit) {
      const point = { left: event.clientX, right: event.clientX, top: event.clientY, bottom: event.clientY + 12 }
      popover.value = { mode: 'edit', ...anchorFor(point), annotation: hit }
      return
    }
    const friendHit = annotations.friendHighlightAt(pageNumber, clientPointToPdf(event.clientX, event.clientY, page, viewport))
    if (friendHit) showFriendNote(friendHit.id)
  })
}

function closePopover() {
  popover.value = null
}

// Where the pages were scrolled when the popover opened. Firefox fires scroll events that move nothing
// (e.g. as the equation editor opens over the popover), so only a real scroll closes it.
let popoverScroll = { top: 0, left: 0 }
watch(popover, (now, before) => {
  const el = pagesContainer.value
  if (now && !before && el) popoverScroll = { top: el.scrollTop, left: el.scrollLeft }
})

function handlePagesScroll() {
  const el = pagesContainer.value
  if (el && Math.abs(el.scrollTop - popoverScroll.top) + Math.abs(el.scrollLeft - popoverScroll.left) > 1) closePopover()
}

function handleDocumentPointerDown(event: PointerEvent) {
  // While a dialog (e.g. the equation editor) is open, clicks belong to it, including MathLive's floating UI.
  if (document.querySelector('dialog[open]')) return
  if (popover.value && !(event.target as HTMLElement).closest('.annotation-popover')) closePopover()
}

async function withPopoverBusy(action: () => Promise<unknown>, failure: string) {
  popoverBusy.value = true
  try {
    await action()
    window.getSelection()?.removeAllRanges()
    closePopover()
  } catch (err) {
    toast.error(requestErrorMessage(err, failure))
  } finally {
    popoverBusy.value = false
  }
}

function handlePopoverPick(color: HighlightColor) {
  const { pending, annotation } = popover.value ?? {}
  if (pending) withPopoverBusy(() => annotations.add({ ...pending, color }), 'Could not save the highlight.')
  else if (annotation) withPopoverBusy(() => annotations.update(annotation.id, { color }), 'Could not change the colour.')
}

function handlePopoverSave(note: string) {
  const { pending, annotation } = popover.value ?? {}
  if (pending) withPopoverBusy(() => annotations.add({ ...pending, note }), 'Could not save the highlight.')
  else if (annotation) withPopoverBusy(() => annotations.update(annotation.id, { note }), 'Could not save the note.')
}

function handlePopoverRemove() {
  const annotation = popover.value?.annotation
  if (annotation) withPopoverBusy(() => annotations.remove(annotation.id), 'Could not delete the highlight.')
}

async function handleAddNote() {
  const note = noteText.value.trim()
  if (!note) return
  try {
    await annotations.add({ page: pdf.currentPage.value, rects: [], quote: '', note, color: null })
    noteText.value = ''
  } catch (err) {
    toast.error(requestErrorMessage(err, 'Could not save the note.'))
  }
}

/* Editing a note in place from the sidebar */

const editingId = ref<string | null>(null)
const editText = ref('')
const savingEdit = ref(false)

function startEdit(item: Annotation) {
  editingId.value = item.id
  editText.value = item.note
}

async function saveEdit(item: Annotation) {
  savingEdit.value = true
  try {
    await annotations.update(item.id, { note: editText.value })
    editingId.value = null
  } catch (err) {
    toast.error(requestErrorMessage(err, 'Could not save the note.'))
  } finally {
    savingEdit.value = false
  }
}

async function handleRemoveAnnotation(id: string) {
  try {
    await annotations.remove(id)
  } catch (err) {
    toast.error(requestErrorMessage(err, 'Could not delete it.'))
  }
}


function handlePrint() {
  window.print()
}

function toggleFullscreen() {
  if (!readerShell.value) return
  if (document.fullscreenElement) {
    document.exitFullscreen?.()
  } else {
    readerShell.value.requestFullscreen?.()
  }
}

function handleKeydown(event: KeyboardEvent) {
  const target = event.target as HTMLElement
  if (target.closest?.('dialog[open]')) return
  // The browser's own find can't see text drawn on canvas, so Ctrl/Cmd+F goes to the book search.
  if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'f') {
    event.preventDefault()
    searchInput.value?.focus()
    searchInput.value?.select()
    return
  }
  // Zoom keys never fire while typing, including in the <math-field> equation editor.
  if (target.closest?.('input, textarea, select, [contenteditable="true"], math-field')) return
  if (event.key === '+' || event.key === '=') {
    event.preventDefault()
    applyZoom(() => zoomRotate.zoomIn())
  } else if (event.key === '-' || event.key === '_') {
    event.preventDefault()
    applyZoom(() => zoomRotate.zoomOut())
  }
}

onMounted(async () => {
  document.addEventListener('keydown', handleKeydown)
  document.addEventListener('pointerdown', handleDocumentPointerDown)
  pdf.setContainer(pagesContainer.value)
  // Title is decoration for the toolbar; the reader still works if this fails.
  booksApi
    .getBook(bookId)
    .then(({ data }) => {
      if (isApiSuccess(data)) book.value = data.data
    })
    .catch(() => {})
  await pdf.load()
  annotations.load().catch(() => toast.error('Could not load your notes and highlights.'))
  if (annotations.canAnnotate.value) {
    friendsApi
      .getSharing()
      .then(({ data }) => {
        if (isApiSuccess(data)) sharingMyNotes.value = data.data.notes
      })
      .catch(() => {})
  }

  if (savedZoom === null) {
    await handleFitWidth()
  }

  // ?page=N (e.g. a content-search hit) wins over the last-read page.
  const startPage = Number(route.query.page) || persistence.getSavedPage()
  if (startPage && startPage >= 1 && startPage <= pdf.numPages.value) {
    setTimeout(() => pdf.goToPage(startPage), 400)
  }
})

onBeforeUnmount(() => {
  document.removeEventListener('keydown', handleKeydown)
  document.removeEventListener('pointerdown', handleDocumentPointerDown)
  pdf.destroy()
})
</script>

<template>
  <div ref="readerShell" class="pdf-reader-shell" :class="themeClass">
    <header class="pdf-toolbar">
      <div class="reader-title">
        <RouterLink :to="`/books/${bookId}`" class="tool-btn" aria-label="Back to book details">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 5l-7 7 7 7" /></svg>
        </RouterLink>
        <div v-if="book" class="reader-title-text">
          <strong>{{ book.title }}</strong>
          <span v-if="book.author">{{ book.author }}</span>
        </div>
      </div>

      <div class="page-control">
        <label class="visually-hidden" for="reader-page-input">Page</label>
        <input
          id="reader-page-input"
          v-model.number="pageInput"
          type="number"
          min="1"
          :max="pdf.numPages.value"
          :style="{ width: `${String(pdf.numPages.value || 1).length + 1}ch` }"
          @change="handleGoToPage"
          @keydown.enter="handleGoToPage"
        />
        <span data-testid="page-count">of {{ pdf.numPages.value || '—' }}</span>
      </div>

      <div class="toolbar-tools">
        <div class="tool-group" role="group" aria-label="Zoom">
          <button type="button" class="tool-btn" aria-label="Zoom out" title="Zoom out (−)" @click="applyZoom(() => zoomRotate.zoomOut())">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 12h12" /></svg>
          </button>
          <button type="button" class="zoom-label" title="Reset zoom" @click="applyZoom(() => zoomRotate.resetZoom())">{{ zoomPercent }}%</button>
          <button type="button" class="tool-btn" aria-label="Zoom in" title="Zoom in (+)" @click="applyZoom(() => zoomRotate.zoomIn())">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 12h12M12 6v12" /></svg>
          </button>
          <button type="button" class="tool-btn" aria-label="Fit to width" title="Fit to width" @click="handleFitWidth">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5v14M20 5v14M8 12h8M10 9l-3 3 3 3M14 9l3 3-3 3" /></svg>
          </button>
        </div>

        <div class="search-field" role="search">
          <label class="visually-hidden" for="reader-search">Search in document</label>
          <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6" /><path d="M16 16l4 4" /></svg>
          <input
            id="reader-search"
            ref="searchInput"
            v-model="search.term.value"
            type="search"
            placeholder="Find in book"
            :aria-busy="search.searching.value"
            @keydown="handleSearchKey"
          />
          <span data-testid="search-status" class="search-status" :class="{ 'is-empty': search.status.value === 'No matches' }" aria-live="polite">{{
            search.status.value
          }}</span>
          <template v-if="search.matches.value.length">
            <button type="button" class="tool-btn search-step" aria-label="Previous match" title="Previous (Shift+Enter)" @click="search.step(-1)">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 15l6-6 6 6" /></svg>
            </button>
            <button type="button" class="tool-btn search-step" aria-label="Next match" title="Next (Enter)" @click="search.step(1)">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9l6 6 6-6" /></svg>
            </button>
          </template>
        </div>

        <div class="theme-picker" role="radiogroup" aria-label="Page colour">
          <button
            v-for="t in themes"
            :key="t.id"
            type="button"
            role="radio"
            :class="['swatch', `swatch-${t.id}`]"
            :aria-checked="theme === t.id"
            :aria-label="t.label"
            :title="t.label"
            @click="theme = t.id"
          ></button>
        </div>

        <div class="tool-group">
          <button type="button" class="tool-btn" aria-label="Rotate pages" title="Rotate" @click="handleRotate">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 12a8 8 0 1 1-2.3-5.7M20 4v4h-4" /></svg>
          </button>
          <button type="button" class="tool-btn" aria-label="Full screen" title="Full screen" @click="toggleFullscreen">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5" /></svg>
          </button>
          <!-- Lent books: printing is "Save as PDF", which would outlive the loan. -->
          <button
            v-if="book && !book.loan_copies"
            type="button"
            class="tool-btn"
            aria-label="Print"
            title="Print"
            @click="handlePrint"
          >
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 9V4h10v5M7 17H4v-7h16v7h-3M7 14h10v6H7z" /></svg>
          </button>
        </div>

        <button type="button" class="notes-toggle" data-testid="notes-toggle" :aria-pressed="notesOpen" @click="notesOpen = !notesOpen">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h9l4 4v14H6zM9 11h7M9 15h7" /></svg>
          Notes<span v-if="annotations.items.value.length" class="notes-count">{{ annotations.items.value.length }}</span>
        </button>
      </div>

      <div
        class="reading-progress"
        role="progressbar"
        aria-label="Reading progress"
        :aria-valuenow="pdf.currentPage.value"
        aria-valuemin="1"
        :aria-valuemax="pdf.numPages.value || 1"
      >
        <div class="reading-progress-fill" :style="{ width: `${progress}%` }"></div>
      </div>
    </header>

    <div class="pdf-reader-body">
      <div v-if="pdf.loading.value" class="spinner" role="status" aria-label="Loading PDF"></div>
      <p v-else-if="pdf.error.value" class="alert alert-danger reader-error">{{ pdf.error.value }}</p>

      <div
        ref="pagesContainer"
        class="pdf-container"
        :hidden="pdf.loading.value || !!pdf.error.value"
        @pointerup="handlePagePointerUp"
        @scroll.passive="handlePagesScroll"
      ></div>

      <AnnotationPopover
        v-if="popover"
        :key="popover.annotation?.id ?? 'new'"
        :mode="popover.mode"
        :left="popover.left"
        :top="popover.top"
        :place-above="popover.placeAbove"
        :color="popover.annotation?.color"
        :note="popover.annotation?.note"
        :login-path="loginPath"
        :busy="popoverBusy"
        @pick="handlePopoverPick"
        @save="handlePopoverSave"
        @remove="handlePopoverRemove"
        @close="closePopover"
      />

      <aside
        v-if="notesOpen"
        class="pdf-notes-panel"
        :class="{ 'is-resizing': resizingPanel }"
        :style="{ width: `${panelWidth}px` }"
        aria-label="Notes"
      >
        <div
          class="panel-resizer"
          role="separator"
          aria-orientation="vertical"
          aria-label="Resize notes panel"
          :aria-valuenow="panelWidth"
          :aria-valuemin="PANEL_MIN"
          tabindex="0"
          title="Drag to resize · double-click to reset"
          @pointerdown.prevent="startPanelResize"
          @dblclick="setPanelWidth(PANEL_DEFAULT)"
          @keydown="handlePanelResizeKey"
        ></div>
        <div class="notes-header">
          <h2>Notes</h2>
          <button type="button" class="btn-close" aria-label="Close notes" @click="notesOpen = false">&times;</button>
        </div>
        <p class="notes-hint" data-testid="notes-privacy">
          <template v-if="sharingMyNotes">
            Friends can see your notes and highlights when they read this book.
            <RouterLink to="/profile?tab=friends">Change</RouterLink>
          </template>
          <template v-else>Only you can see your notes and highlights.</template>
        </p>

        <div v-if="!annotations.canAnnotate.value" class="notes-guest">
          <p>Log in to highlight passages and keep notes on this book. They're saved to your account and stay private.</p>
          <RouterLink :to="loginPath" class="btn btn-primary">Log in</RouterLink>
        </div>

        <template v-else>
          <div class="notes-add">
            <label for="reader-note-text">Note on page {{ pdf.currentPage.value }}</label>
            <NoteEditor id="reader-note-text" v-model="noteText" placeholder="A thought, a formula, a snippet…" @submit="handleAddNote" />
            <div class="notes-actions">
              <button type="button" class="btn btn-primary" :disabled="!noteText.trim()" @click="handleAddNote">Add note</button>
            </div>
          </div>

          <p v-if="!annotations.items.value.length" class="notes-empty">
            Nothing here yet. Select text on a page to highlight it, or add a note above.
          </p>
          <ul v-else class="notes-list">
            <li v-for="item in annotations.sorted.value" :key="item.id" class="note-item" data-testid="note-item">
              <button type="button" class="note-page" @click="pdf.goToPage(item.page)">p. {{ item.page }}</button>
              <div class="note-body">
                <blockquote v-if="item.quote" :class="['note-quote', `hl-${item.color ?? 'saffron'}`]">{{ item.quote }}</blockquote>
                <form v-if="editingId === item.id" class="note-edit" @submit.prevent="saveEdit(item)" @keydown.esc="editingId = null">
                  <label class="visually-hidden" :for="`note-edit-${item.id}`">Note</label>
                  <NoteEditor
                    :id="`note-edit-${item.id}`"
                    v-model="editText"
                    autofocus
                    :placeholder="item.rects.length ? 'Add a note to this highlight' : ''"
                    @submit="saveEdit(item)"
                  />
                  <div class="note-edit-actions">
                    <!-- A page note is nothing but its text, so it can't be saved empty (delete it instead). -->
                    <button type="submit" class="btn btn-primary" :disabled="savingEdit || (!item.rects.length && !editText.trim())">
                      Save
                    </button>
                    <button type="button" class="link-btn" @click="editingId = null">Cancel</button>
                  </div>
                </form>
                <!-- Keyed by theme so diagrams redraw in the new colours. -->
                <NoteContent v-else-if="item.note" :key="theme" class="note-text" :text="item.note" />
              </div>
              <div v-if="editingId !== item.id" class="note-actions">
                <button
                  type="button"
                  class="note-action"
                  :aria-label="item.rects.length ? (item.note ? 'Edit note on highlight' : 'Add note to highlight') : 'Edit note'"
                  :title="item.note ? 'Edit note' : 'Add note'"
                  @click="startEdit(item)"
                >
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h4L19 9l-4-4L4 16zM13.5 6.5l4 4" /></svg>
                </button>
                <button
                  type="button"
                  class="note-action note-action-delete"
                  :aria-label="item.rects.length ? 'Delete highlight' : 'Delete note'"
                  title="Delete"
                  @click="handleRemoveAnnotation(item.id)"
                >
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>
                </button>
              </div>
            </li>
          </ul>

          <section v-if="annotations.friendItems.value.length" class="friend-notes" aria-labelledby="friend-notes-heading">
            <h3 id="friend-notes-heading">From friends</h3>
            <div class="friend-filter" role="group" aria-label="Whose notes to show" data-testid="friend-filter">
              <button
                v-for="f in annotations.noteFriends.value"
                :key="f.id"
                type="button"
                class="friend-chip"
                :aria-pressed="f.shown"
                :data-testid="`friend-filter-${f.id}`"
                @click="annotations.toggleFriend(f.id)"
              >
                {{ f.username }} <span class="friend-chip-count">{{ f.count }}</span>
              </button>
            </div>
            <p v-if="!annotations.friendsSorted.value.length" class="notes-empty">Turn on a friend above to see their notes.</p>
            <ul v-else class="notes-list">
              <li
                v-for="item in annotations.friendsSorted.value"
                :id="`friend-note-${item.id}`"
                :key="item.id"
                class="note-item friend-note"
                :class="{ 'is-focused': focusedFriendNote === item.id }"
                data-testid="friend-note-item"
              >
                <button type="button" class="note-page" @click="pdf.goToPage(item.page)">p. {{ item.page }}</button>
                <div class="note-body">
                  <span class="note-author">{{ item.username }}</span>
                  <blockquote v-if="item.quote" :class="['note-quote', `hl-${item.color ?? 'saffron'}`]">{{ item.quote }}</blockquote>
                  <NoteContent v-if="item.note" :key="theme" class="note-text" :text="item.note" />
                </div>
              </li>
            </ul>
          </section>
        </template>
      </aside>
    </div>
  </div>
</template>

<style scoped src="@/css/views/Reader.css"></style>
