<script setup lang="ts">
import { computed, nextTick, onMounted, reactive, ref } from 'vue'
import * as adminApi from '@/api/admin'
import { isApiSuccess, apiErrorMessage, requestErrorMessage, type ApiError } from '@/types/api'
import type { Book } from '@/types/book'
import { idToString } from '@/types/book'
import { useToast } from '@/composables/useToast'
import { useCategoryOptions } from '@/composables/useCategoryOptions'
import { thumbnailUrl } from '@/api/books'
import { onThumbnailError } from '@/utils/dom'
import CategoryPicker from '@/components/CategoryPicker.vue'
import { useContentSearchEnabled } from '@/composables/useContentSearchEnabled'

const toast = useToast()

const books = ref<Book[]>([])
const loading = ref(true)
const loadError = ref('')
const busyIds = ref<Set<string>>(new Set())
const availableCategories = useCategoryOptions()
const contentSearchEnabled = useContentSearchEnabled()

async function loadBooks() {
  loading.value = true
  loadError.value = ''
  try {
    const response = await adminApi.getAllBooks()
    const body = response.data
    // Newest first: the books an admin just uploaded are the ones they're most likely to fix up.
    books.value = isApiSuccess(body) ? [...body.data].sort((a, b) => addedAt(b) - addedAt(a)) : []
  } catch {
    loadError.value = "Couldn't load the catalogue. Check that the server is running, then try again."
  } finally {
    loading.value = false
  }
}

function addedAt(book: Book): number {
  return Number(book.created_at?.$date?.$numberLong ?? 0)
}

// --- Filters (also the shelf's legend) ---
type Filter = 'all' | 'public' | 'draft' | 'featured' | 'locked'
const filter = ref<Filter>('all')
const query = ref('')

const filterTests: Record<Filter, (book: Book) => boolean> = {
  all: () => true,
  public: (b) => b.status === 'public',
  draft: (b) => b.status !== 'public',
  featured: (b) => !!b.featured,
  locked: (b) => b.downloadable === false,
}

const filters = computed(() =>
  (
    [
      ['all', 'All'],
      ['public', 'Public'],
      ['draft', 'Drafts'],
      ['featured', 'Featured'],
      ['locked', 'Downloads off'],
    ] as const
  ).map(([key, label]) => ({
    key,
    label,
    count: books.value.filter(filterTests[key]).length,
  })),
)

function matches(book: Book): boolean {
  const q = query.value.trim().toLowerCase()
  const text = `${book.title ?? ''} ${book.author ?? ''}`.toLowerCase()
  return filterTests[filter.value](book) && (!q || text.includes(q))
}

const visibleBooks = computed(() => books.value.filter(matches))

function clearFilters() {
  filter.value = 'all'
  query.value = ''
}

// --- The shelf: one spine per book, public before drafts, featured first ---
const shelf = computed(() => {
  const rank = (b: Book) => (b.status === 'public' ? (b.featured ? 0 : 1) : 2)
  const reads = (b: Book) => (b.views ?? 0) + (b.downloads ?? 0)
  const mostRead = Math.max(1, ...books.value.map(reads))
  // ponytail: one flex item per book, spines thin to 2px past ~400 books; bucket by category if the catalogue grows that big.
  return [...books.value]
    .sort((a, b) => rank(a) - rank(b))
    .map((book) => ({
      book,
      id: idToString(book._id),
      height: 72 + (28 * reads(book)) / mostRead,
    }))
})

const flashedId = ref('')

async function showOnShelf(id: string) {
  if (!visibleBooks.value.some((b) => idToString(b._id) === id)) clearFilters()
  await nextTick()
  document.getElementById(`book-${id}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' })
  flashedId.value = id
  setTimeout(() => {
    if (flashedId.value === id) flashedId.value = ''
  }, 1600)
}

/** Content-search indexing state, shown only when it needs attention or is still in progress. */
function indexLabel(book: Book): string {
  // Nothing indexes books while content search is off, so "waiting" labels would never clear.
  if (!contentSearchEnabled.value) return ''
  // The worker only extracts text from PDFs; books uploaded before file_extension existed are PDFs.
  if ((book.file_extension ?? 'pdf').toLowerCase() !== 'pdf') return 'Not searchable: only PDFs are indexed'
  switch (book.index_status) {
    case 'done':
      return ''
    case 'failed':
      return 'Not searchable: indexing failed'
    case 'no_text':
      return 'Not searchable: no text layer (scanned PDF)'
    case 'indexing':
      return 'Indexing for search…'
    default:
      return 'Waiting to be indexed for search'
  }
}

function isBusy(id: string) {
  return busyIds.value.has(id)
}

async function toggleStatus(book: Book) {
  const id = idToString(book._id)
  const nextStatus = book.status === 'public' ? 'draft' : 'public'
  busyIds.value.add(id)
  try {
    const response = await adminApi.updateBook(id, { status: nextStatus })
    if (isApiSuccess(response.data)) {
      book.status = nextStatus
      toast.success(`Status updated to ${nextStatus}`)
    } else {
      toast.error(apiErrorMessage(response.data as ApiError, 'Failed to update status'))
    }
  } catch {
    toast.error('An error occurred while updating status.')
  } finally {
    busyIds.value.delete(id)
  }
}

async function toggleFeatured(book: Book) {
  const id = idToString(book._id)
  const next = !book.featured
  busyIds.value.add(id)
  try {
    const response = await adminApi.updateBook(id, { featured: next })
    if (isApiSuccess(response.data)) {
      book.featured = next
    } else {
      toast.error(apiErrorMessage(response.data as ApiError, 'Failed to update featured status'))
    }
  } catch {
    toast.error('An error occurred while updating featured status.')
  } finally {
    busyIds.value.delete(id)
  }
}

async function handleDelete(book: Book) {
  if (!confirm(`Delete "${book.title}"? This action can't be undone.`)) return
  const id = idToString(book._id)
  busyIds.value.add(id)
  try {
    const response = await adminApi.deleteBook(id)
    if (isApiSuccess(response.data)) {
      books.value = books.value.filter((b) => idToString(b._id) !== id)
      toast.success('Book deleted.')
    } else {
      toast.error(apiErrorMessage(response.data as ApiError, 'Delete failed'))
    }
  } catch {
    toast.error('An error occurred while deleting.')
  } finally {
    busyIds.value.delete(id)
  }
}

// --- Edit modal ---
const editing = ref<Book | null>(null)
const editForm = reactive({
  title: '',
  author: '',
  description: '',
  status: 'draft',
  featured: false,
  isbn: '',
  downloadable: true,
  categories: [] as string[],
})
const editSaving = ref(false)
const editError = ref('')

function openEdit(book: Book) {
  editing.value = book
  editForm.title = book.title ?? ''
  editForm.author = book.author ?? ''
  editForm.description = book.description ?? ''
  editForm.status = book.status ?? 'draft'
  editForm.featured = !!book.featured
  editForm.isbn = book.isbn ?? ''
  editForm.downloadable = book.downloadable !== false
  editForm.categories = [...(book.categories ?? [])]
  editError.value = ''
}

function closeEdit() {
  editing.value = null
}

async function submitEdit() {
  if (!editing.value) return
  editError.value = ''
  editSaving.value = true
  const id = idToString(editing.value._id)
  const payload = { ...editForm }
  try {
    const response = await adminApi.updateBook(id, payload)
    if (isApiSuccess(response.data)) {
      Object.assign(editing.value, payload)
      toast.success('Book updated successfully.')
      closeEdit()
    } else {
      editError.value = apiErrorMessage(response.data as ApiError, 'Update failed')
    }
  } catch (err) {
    editError.value = requestErrorMessage(err, 'An error occurred during update.')
  } finally {
    editSaving.value = false
  }
}

onMounted(loadBooks)
</script>

<template>
  <div class="container admin-page" data-testid="dashboard-page">
    <header class="admin-header">
      <h1 data-testid="dashboard-heading">Manage Books</h1>
      <div class="header-actions">
        <RouterLink to="/admin/logs" class="link-btn">Server logs</RouterLink>
        <RouterLink to="/admin/upload" class="btn btn-primary" data-testid="upload-pdf-link">Upload PDF</RouterLink>
      </div>
    </header>

    <div v-if="loading" class="spinner" role="status" aria-label="Loading books" data-testid="dashboard-loading"></div>

    <div v-else-if="loadError" class="state-message" data-testid="dashboard-error">
      <p>{{ loadError }}</p>
      <button type="button" class="btn btn-outline" @click="loadBooks">Try again</button>
    </div>

    <div v-else-if="!books.length" class="state-message">
      <p>No books in the catalogue yet. Upload a PDF to start the collection.</p>
      <RouterLink to="/admin/upload" class="btn btn-primary">Upload PDF</RouterLink>
    </div>

    <template v-else>
      <section class="collection" aria-labelledby="collection-summary">
        <p id="collection-summary" class="summary">
          {{ books.length }} {{ books.length === 1 ? 'book' : 'books' }}: {{ filters[1].count }} public,
          {{ filters[2].count }} {{ filters[2].count === 1 ? 'draft' : 'drafts' }}, {{ filters[3].count }} featured.
        </p>

        <!-- Decorative overview of the same data as the summary and table, so it's hidden from assistive tech. -->
        <div class="shelf" aria-hidden="true">
          <button
            v-for="(spine, i) in shelf"
            :key="spine.id"
            type="button"
            tabindex="-1"
            class="spine"
            :class="{
              draft: spine.book.status !== 'public',
              featured: spine.book.featured,
              dim: !matches(spine.book),
            }"
            :style="{ height: `${spine.height}%`, '--i': Math.min(i, 40) }"
            :title="spine.book.title"
            :data-testid="`shelf-spine-${spine.id}`"
            @click="showOnShelf(spine.id)"
          ></button>
        </div>

        <div class="toolbar">
          <div class="filters" role="group" aria-label="Show books">
            <button
              v-for="f in filters"
              :key="f.key"
              type="button"
              class="filter"
              :class="`filter-${f.key}`"
              :aria-pressed="filter === f.key"
              @click="filter = f.key"
            >
              {{ f.label }} <span class="count">{{ f.count }}</span>
            </button>
          </div>
          <label class="search">
            <span class="visually-hidden">Find a book by title or author</span>
            <input v-model="query" type="search" placeholder="Find by title or author" />
          </label>
        </div>
      </section>

      <div class="table-wrap">
        <table class="admin-table" data-testid="books-table">
          <thead>
            <tr>
              <th scope="col">Book</th>
              <th scope="col">Status</th>
              <th scope="col"><span class="visually-hidden">Featured</span></th>
              <th scope="col" class="num">Views</th>
              <th scope="col" class="num">Downloads</th>
              <th scope="col" class="num">Rating</th>
              <th scope="col"><span class="visually-hidden">Actions</span></th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="book in visibleBooks"
              :id="`book-${idToString(book._id)}`"
              :key="idToString(book._id)"
              :class="{ flash: flashedId === idToString(book._id) }"
              :data-testid="`book-row-${idToString(book._id)}`"
            >
              <td>
                <div class="book-cell">
                  <img
                    class="cover"
                    :src="book.thumbnail || thumbnailUrl(idToString(book._id))"
                    alt=""
                    loading="lazy"
                    @error="onThumbnailError"
                  />
                  <div class="book-text">
                    <cite class="book-title">{{ book.title }}</cite>
                    <span class="book-meta"
                      >{{ book.author || 'Unknown author'
                      }}<template v-if="book.year">, {{ book.year }}</template></span
                    >
                    <span v-if="book.categories?.length" class="book-categories">{{ book.categories.join(', ') }}</span>
                    <span
                      v-if="indexLabel(book)"
                      class="book-index"
                      :class="{ 'is-problem': book.index_status === 'failed' || book.index_status === 'no_text' }"
                      :title="book.index_error"
                      :data-testid="`index-status-${idToString(book._id)}`"
                      >{{ indexLabel(book) }}</span
                    >
                  </div>
                </div>
              </td>
              <td class="status-cell">
                <button
                  type="button"
                  class="status-chip"
                  :class="book.status === 'public' ? 'is-public' : 'is-draft'"
                  :title="
                    book.status === 'public' ? 'Public. Select to move back to drafts.' : 'Draft. Select to publish.'
                  "
                  :disabled="isBusy(idToString(book._id))"
                  :data-testid="`status-toggle-${idToString(book._id)}`"
                  @click="toggleStatus(book)"
                >
                  {{ book.status === 'public' ? 'Public' : 'Draft' }}
                </button>
              </td>
              <td class="featured-cell">
                <button
                  type="button"
                  class="star"
                  aria-label="Featured"
                  :aria-pressed="!!book.featured"
                  :title="
                    book.featured
                      ? 'Featured on the home page. Select to remove.'
                      : 'Select to feature on the home page.'
                  "
                  :disabled="isBusy(idToString(book._id))"
                  :data-testid="`featured-toggle-${idToString(book._id)}`"
                  @click="toggleFeatured(book)"
                >
                  <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M12 2.8l2.8 5.9 6.4.8-4.7 4.4 1.2 6.4L12 17.2l-5.7 3.1 1.2-6.4-4.7-4.4 6.4-.8z" />
                  </svg>
                </button>
              </td>
              <td class="num" data-label="Views">{{ book.views ?? 0 }}</td>
              <td class="num" data-label="Downloads">
                <span v-if="book.downloadable === false" class="locked" title="Downloads are turned off for this book"
                  >Off</span
                >
                <template v-else>{{ book.downloads ?? 0 }}</template>
              </td>
              <td class="num" data-label="Rating">
                <template v-if="book.review_count">
                  {{ book.average_rating?.toFixed(1) }}
                  <span class="reviews"
                    >{{ book.review_count }} {{ book.review_count === 1 ? 'review' : 'reviews' }}</span
                  >
                </template>
                <span v-else class="none">None yet</span>
              </td>
              <td class="actions-cell">
                <button
                  type="button"
                  class="link-btn"
                  :data-testid="`edit-book-${idToString(book._id)}`"
                  @click="openEdit(book)"
                >
                  Edit
                </button>
                <RouterLink :to="`/read/${idToString(book._id)}`" class="link-btn">Preview</RouterLink>
                <button
                  type="button"
                  class="link-btn danger"
                  :disabled="isBusy(idToString(book._id))"
                  :data-testid="`delete-book-${idToString(book._id)}`"
                  @click="handleDelete(book)"
                >
                  Delete
                </button>
              </td>
            </tr>
            <tr v-if="!visibleBooks.length" class="no-match">
              <td colspan="7">
                No books match these filters.
                <button type="button" class="link-btn" @click="clearFilters">Show all books</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <div v-if="editing" class="modal-overlay" @click.self="closeEdit">
      <div class="modal-panel card" role="dialog" aria-modal="true" aria-labelledby="edit-book-heading">
        <h2 id="edit-book-heading">Edit Book</h2>
        <p v-if="editError" class="alert alert-danger">{{ editError }}</p>
        <form @submit.prevent="submitEdit">
          <div class="form-grid">
            <div class="form-field">
              <label for="edit-title">Title</label>
              <input id="edit-title" v-model="editForm.title" type="text" required />
            </div>
            <div class="form-field">
              <label for="edit-author">Author</label>
              <input id="edit-author" v-model="editForm.author" type="text" />
            </div>
            <div class="form-field span-2">
              <label for="edit-description">Description</label>
              <textarea id="edit-description" v-model="editForm.description" rows="3"></textarea>
            </div>
            <div class="form-field">
              <label for="edit-status">Status</label>
              <select id="edit-status" v-model="editForm.status">
                <option value="draft">Draft</option>
                <option value="public">Public</option>
              </select>
            </div>
            <div class="form-field">
              <label for="edit-isbn">ISBN</label>
              <input id="edit-isbn" v-model="editForm.isbn" type="text" />
            </div>
            <div class="form-field checkbox-field">
              <label><input v-model="editForm.featured" type="checkbox" /> Featured</label>
            </div>
            <div class="form-field checkbox-field">
              <label
                ><input v-model="editForm.downloadable" type="checkbox" data-testid="edit-downloadable-checkbox" />
                Downloadable</label
              >
            </div>
            <div class="form-field span-2">
              <label for="edit-categories">Categories</label>
              <CategoryPicker
                id="edit-categories"
                v-model="editForm.categories"
                :options="availableCategories"
                placeholder="Fiction, Fantasy, Adventure…"
              />
              <small class="text-muted">Pick an existing category or type a new one and press Enter.</small>
            </div>
          </div>
          <div class="modal-actions">
            <button type="button" class="btn btn-outline" @click="closeEdit">Cancel</button>
            <button type="submit" class="btn btn-primary" :disabled="editSaving" data-testid="edit-save-button">
              {{ editSaving ? 'Saving…' : 'Save Changes' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<style scoped src="@/css/views/admin/Dashboard.css"></style>
