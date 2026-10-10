<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { usePdfDocument } from '@/composables/reader/usePdfDocument'
import { useZoomRotate, DEFAULT_SCALE } from '@/composables/reader/useZoomRotate'
import { useReaderSearch } from '@/composables/reader/useReaderSearch'
import { useReaderNotes } from '@/composables/reader/useReaderNotes'
import { useReaderPersistence } from '@/composables/reader/useReaderPersistence'
import { useToast } from '@/composables/useToast'
import * as booksApi from '@/api/books'
import type { Book } from '@/types/book'
import { isApiSuccess } from '@/types/api'

const route = useRoute()
const toast = useToast()
const bookId = route.params.id as string

const persistence = useReaderPersistence(bookId)
const savedZoom = persistence.getSavedZoom()

const zoomRotate = useZoomRotate(savedZoom ?? DEFAULT_SCALE)
const pdf = usePdfDocument(bookId, {
  scale: zoomRotate.scale,
  rotation: zoomRotate.rotation,
  decoratePage: (pageNumber, wrapper, viewport) => search.paintHits(pageNumber, wrapper, viewport),
})
const search = useReaderSearch(pdf.pdfDoc)
const notes = useReaderNotes(bookId)

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

function handleAddNote() {
  if (!noteText.value.trim()) return
  notes.addNote(pdf.currentPage.value, noteText.value)
  noteText.value = ''
}

async function handleExportNotes() {
  await notes.exportNotes()
  toast.success('Notes copied to clipboard as JSON.')
}

function handleClearNotes() {
  if (confirm('Delete all notes for this book in this browser?')) {
    notes.clearNotes()
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
  // The browser's own find can't see text drawn on canvas, so Ctrl/Cmd+F goes to the book search.
  if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'f') {
    event.preventDefault()
    searchInput.value?.focus()
    searchInput.value?.select()
    return
  }
  const tag = (event.target as HTMLElement)?.tagName
  if (tag === 'INPUT' || tag === 'TEXTAREA') return
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
  pdf.setContainer(pagesContainer.value)
  // Title is decoration for the toolbar; the reader still works if this fails.
  booksApi
    .getBook(bookId)
    .then(({ data }) => {
      if (isApiSuccess(data)) book.value = data.data
    })
    .catch(() => {})
  await pdf.load()

  if (savedZoom === null) {
    await handleFitWidth()
  }

  const savedPage = persistence.getSavedPage()
  if (savedPage && savedPage >= 1 && savedPage <= pdf.numPages.value) {
    setTimeout(() => pdf.goToPage(savedPage), 400)
  }
})

onBeforeUnmount(() => {
  document.removeEventListener('keydown', handleKeydown)
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
        <span>of {{ pdf.numPages.value || '—' }}</span>
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
          <span class="search-status" :class="{ 'is-empty': search.status.value === 'No matches' }" aria-live="polite">{{
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
          <button type="button" class="tool-btn" aria-label="Print" title="Print" @click="handlePrint">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 9V4h10v5M7 17H4v-7h16v7h-3M7 14h10v6H7z" /></svg>
          </button>
        </div>

        <button type="button" class="notes-toggle" :aria-pressed="notesOpen" @click="notesOpen = !notesOpen">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h9l4 4v14H6zM9 11h7M9 15h7" /></svg>
          Notes<span v-if="notes.notes.value.length" class="notes-count">{{ notes.notes.value.length }}</span>
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

      <div ref="pagesContainer" class="pdf-container" :hidden="pdf.loading.value || !!pdf.error.value"></div>

      <aside v-if="notesOpen" class="pdf-notes-panel" aria-label="Notes">
        <div class="notes-header">
          <h2>Notes</h2>
          <button type="button" class="btn-close" aria-label="Close notes" @click="notesOpen = false">&times;</button>
        </div>
        <p class="notes-hint">Kept in this browser, for this book only.</p>
        <div class="notes-add">
          <label for="reader-note-text">Note on page {{ pdf.currentPage.value }}</label>
          <textarea id="reader-note-text" v-model="noteText" rows="3" placeholder="A quote, a question, a reminder"></textarea>
          <div class="notes-actions">
            <button type="button" class="btn btn-primary" :disabled="!noteText.trim()" @click="handleAddNote">Add note</button>
            <button type="button" class="link-btn" :disabled="!notes.notes.value.length" @click="handleExportNotes">Copy as JSON</button>
            <button type="button" class="link-btn link-btn-danger" :disabled="!notes.notes.value.length" @click="handleClearNotes">Delete all</button>
          </div>
        </div>
        <p v-if="!notes.notes.value.length" class="notes-empty">Nothing here yet. Notes you add appear in page order, so you can jump back to them.</p>
        <ul v-else class="notes-list">
          <li v-for="note in notes.notes.value" :key="note.id" class="note-item">
            <button type="button" class="note-page" @click="pdf.goToPage(note.page)">p. {{ note.page }}</button>
            <p class="note-text">{{ note.text }}</p>
            <button type="button" class="btn-close" aria-label="Delete note" @click="notes.removeNote(note.id)">&times;</button>
          </li>
        </ul>
      </aside>
    </div>
  </div>
</template>

<style scoped src="@/css/views/Reader.css"></style>
