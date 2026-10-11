<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import * as booksApi from '@/api/books'
import * as reviewsApi from '@/api/reviews'
import * as usersApi from '@/api/users'
import { isApiSuccess, apiErrorMessage, requestErrorMessage, type ApiError } from '@/types/api'
import type { Book } from '@/types/book'
import type { Review } from '@/types/review'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { downloadBlob, onThumbnailError } from '@/utils/dom'
import StarRating from '@/components/StarRating.vue'
import ReviewList from '@/components/ReviewList.vue'
import ReviewForm from '@/components/ReviewForm.vue'

const route = useRoute()
const auth = useAuthStore()
const toast = useToast()
const bookId = route.params.id as string

const book = ref<Book | null>(null)
const reviews = ref<Review[]>([])
const loading = ref(true)
const notFound = ref(false)

const saving = ref(false)
const saved = ref(false)
const downloading = ref(false)

const cover = computed(() => book.value?.thumbnail || booksApi.thumbnailUrl(bookId))
const loan = computed(() => book.value?.loan ?? null)
const isDownloadable = computed(() => book.value?.downloadable !== false && !loan.value)
const lending = ref(false)
const myReview = computed(() => reviews.value.find((r) => r.user_id === auth.claims?.user_id))

async function loadReviews() {
  try {
    const reviewResponse = await reviewsApi.getReviews(bookId)
    const reviewBody = reviewResponse.data
    reviews.value = isApiSuccess(reviewBody) ? reviewBody.data : []
  } catch {
    reviews.value = []
  }
}

async function handleSave() {
  saving.value = true
  try {
    const response = await usersApi.saveBook(bookId)
    const body = response.data
    if (isApiSuccess(body)) {
      saved.value = true
      toast.success('Saved to your reading list')
    } else {
      toast.error(apiErrorMessage(body as ApiError, 'Failed to save book'))
    }
  } catch (err) {
    toast.error(requestErrorMessage(err, 'An error occurred while saving the book'))
  } finally {
    saving.value = false
  }
}

async function handleBorrow() {
  if (!book.value?.loan) return
  lending.value = true
  try {
    const { data: body } = await booksApi.borrowBook(bookId)
    if (isApiSuccess(body)) {
      book.value.loan = { ...book.value.loan, due_at: body.data.due_at, available: book.value.loan.available - 1 }
      toast.success('Borrowed — happy reading')
    } else {
      toast.error(apiErrorMessage(body as ApiError, 'Could not borrow this book'))
    }
  } catch (err) {
    toast.error(requestErrorMessage(err, 'Could not borrow this book'))
  } finally {
    lending.value = false
  }
}

async function handleReturn() {
  if (!book.value?.loan) return
  lending.value = true
  try {
    await booksApi.returnBook(bookId)
    book.value.loan = { ...book.value.loan, due_at: null, available: book.value.loan.available + 1 }
    toast.success('Book returned')
  } catch (err) {
    toast.error(requestErrorMessage(err, 'Could not return this book'))
  } finally {
    lending.value = false
  }
}

async function handleDownload() {
  downloading.value = true
  try {
    const response = await booksApi.downloadBook(bookId)
    downloadBlob(response.data, `${book.value?.title || 'book'}.pdf`)
  } catch {
    toast.error('Failed to download the file. Please try again.')
  } finally {
    downloading.value = false
  }
}

onMounted(async () => {
  try {
    const response = await booksApi.getBook(bookId)
    const body = response.data
    if (isApiSuccess(body)) {
      book.value = body.data
      document.title = book.value.title || 'Book Details'
    } else {
      notFound.value = true
    }
  } catch {
    notFound.value = true
  } finally {
    loading.value = false
  }

  await loadReviews()
})
</script>

<template>
  <div class="container detail-page">
    <div v-if="loading" class="spinner" role="status" aria-label="Loading book details"></div>

    <div v-else-if="notFound" class="not-found">
      <h2>Book not found</h2>
      <RouterLink to="/browse" class="btn btn-outline">Back to Browse</RouterLink>
    </div>

    <div v-else-if="book" class="detail-grid">
      <div class="cover-column">
        <img :src="cover" class="cover" :alt="`Cover of ${book.title}`" @error="onThumbnailError" />

        <div class="actions">
          <template v-if="loan">
            <template v-if="loan.due_at">
              <RouterLink :to="`/read/${bookId}`" class="btn btn-primary full-width" data-testid="read-loan-button">
                Read
              </RouterLink>
              <button
                type="button"
                class="btn btn-outline full-width"
                :disabled="lending"
                data-testid="return-button"
                @click="handleReturn"
              >
                Return now
              </button>
              <p class="text-muted loan-note" data-testid="loan-due">
                Borrowed until {{ new Date(loan.due_at).toLocaleDateString() }}
              </p>
            </template>
            <template v-else>
              <button
                v-if="auth.isAuthenticated"
                type="button"
                class="btn btn-primary full-width"
                :disabled="lending || loan.available < 1"
                data-testid="borrow-button"
                @click="handleBorrow"
              >
                {{ loan.available < 1 ? 'All copies on loan' : `Borrow for ${loan.loan_days} days` }}
              </button>
              <p class="text-muted loan-note" data-testid="loan-availability">
                {{ loan.available }} of {{ loan.copies }} {{ loan.copies === 1 ? 'copy' : 'copies' }} available
              </p>
            </template>
          </template>
          <RouterLink
            v-else
            :to="`/read/${bookId}`"
            class="btn btn-outline full-width"
            data-testid="preview-button"
          >
            Online Preview
          </RouterLink>
          <template v-if="auth.isAuthenticated">
            <button
              type="button"
              class="btn btn-outline full-width"
              :disabled="saving || saved"
              data-testid="save-button"
              @click="handleSave"
            >
              {{ saved ? 'Saved to List' : saving ? 'Saving…' : 'Save to Reading List' }}
            </button>
            <button
              v-if="isDownloadable"
              type="button"
              class="btn btn-primary full-width"
              :disabled="downloading"
              data-testid="download-button"
              @click="handleDownload"
            >
              {{ downloading ? 'Downloading…' : 'Download PDF' }}
            </button>
            <button
              v-else
              type="button"
              class="btn btn-outline full-width"
              disabled
              :title="loan ? 'Lent books can only be read online while borrowed' : 'This book is not available for download'"
              data-testid="download-disabled-button"
            >
              Download Disabled
            </button>
          </template>
          <p v-else class="text-muted login-hint" data-testid="login-hint">
            <RouterLink :to="{ path: '/login', query: { redirect: route.fullPath } }">Log in</RouterLink>
            to {{ loan ? 'borrow' : 'read online, save, or download' }} this book.
          </p>
        </div>
      </div>

      <div class="info-column">
        <h1 data-testid="book-title">{{ book.title || 'Untitled' }}</h1>

        <div v-if="book.average_rating" class="rating-row">
          <StarRating :rating="book.average_rating" />
          <span class="text-muted" data-testid="book-rating">({{ book.average_rating }})</span>
        </div>

        <div v-if="book.categories?.length" class="categories">
          <span v-for="category in book.categories" :key="category" class="badge">{{ category }}</span>
        </div>
        <p v-else class="badge standalone">Uncategorized</p>

        <p class="description">{{ book.description || 'No description available' }}</p>

        <div class="meta-grid">
          <div>
            <p><strong>Author:</strong> {{ book.author || 'Unknown' }}</p>
            <p v-if="book.published_date"><strong>Published:</strong> {{ book.published_date }}</p>
            <p v-if="book.isbn"><strong>ISBN:</strong> {{ book.isbn }}</p>
          </div>
          <div>
            <p v-if="book.language"><strong>Language:</strong> {{ book.language }}</p>
            <p v-if="book.pages"><strong>Pages:</strong> {{ book.pages }}</p>
            <p v-if="book.publisher"><strong>Publisher:</strong> {{ book.publisher }}</p>
          </div>
        </div>

        <hr />
        <!-- Keyed so the form re-initialises from myReview once reviews load or a new review is saved. -->
        <ReviewForm
          v-if="auth.isAuthenticated"
          :key="myReview?.created_at ?? 'new'"
          :book-id="bookId"
          :existing="myReview"
          @submitted="loadReviews"
        />
        <p v-else class="alert alert-info">
          <RouterLink :to="{ path: '/login', query: { redirect: route.fullPath } }">Log in</RouterLink>
          to leave a review.
        </p>
        <ReviewList :reviews="reviews" />
      </div>
    </div>
  </div>
</template>

<style scoped src="@/css/views/BookDetail.css"></style>
