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
const isDownloadable = computed(() => book.value?.downloadable !== false)

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
          <RouterLink :to="`/read/${bookId}`" class="btn btn-outline full-width" data-testid="preview-button">
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
              title="This book is not available for download"
              data-testid="download-disabled-button"
            >
              Download Disabled
            </button>
          </template>
          <p v-else class="text-muted login-hint" data-testid="login-hint">
            <RouterLink :to="{ path: '/login', query: { redirect: route.fullPath } }">Log in</RouterLink>
            to read online, save, or download this book.
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
        <ReviewForm v-if="auth.isAuthenticated" :book-id="bookId" @submitted="loadReviews" />
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
