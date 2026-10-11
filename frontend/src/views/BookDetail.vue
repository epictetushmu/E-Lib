<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import * as booksApi from '@/api/books'
import * as reviewsApi from '@/api/reviews'
import * as usersApi from '@/api/users'
import * as adminApi from '@/api/admin'
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
const isPhysical = computed(() => book.value?.file_type === 'physical')
const isDownloadable = computed(() => book.value?.downloadable !== false && !loan.value && !isPhysical.value)
const lending = ref(false)
const checkoutEmail = ref('')
const shortDate = (iso: string) => new Date(iso).toLocaleDateString()
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

/** Borrow (PDF) / reserve (physical) / return / cancel / desk checkout, then reload the loan state. */
async function lendingAction(request: () => Promise<{ data: unknown }>, success: string, failure: string) {
  lending.value = true
  try {
    const { data: body } = await request()
    if (isApiSuccess(body as ApiError)) {
      toast.success(success)
      await loadBook()
    } else {
      toast.error(apiErrorMessage(body as ApiError, failure))
    }
  } catch (err) {
    toast.error(requestErrorMessage(err, failure))
  } finally {
    lending.value = false
  }
}

const handleBorrow = () =>
  lendingAction(
    () => booksApi.borrowBook(bookId),
    isPhysical.value ? 'Reserved — pick it up at the library desk' : 'Borrowed — happy reading',
    isPhysical.value ? 'Could not reserve this book' : 'Could not borrow this book',
  )

const handleReturn = () =>
  lendingAction(
    () => booksApi.returnBook(bookId),
    isPhysical.value ? 'Reservation cancelled' : 'Book returned',
    'Something went wrong. Please try again.',
  )

const handleRenew = () =>
  lendingAction(() => booksApi.renewBook(bookId), 'Renewed', 'Could not renew this loan')

const handleJoinWaitlist = () =>
  lendingAction(
    () => booksApi.joinWaitlist(bookId),
    "You're on the waitlist. We'll notify you when a copy is free.",
    'Could not join the waitlist',
  )

const handleLeaveWaitlist = () =>
  lendingAction(() => booksApi.leaveWaitlist(bookId), 'You left the waitlist', 'Something went wrong. Please try again.')

const handleCheckout = () =>
  lendingAction(
    () => adminApi.checkOut(bookId, checkoutEmail.value.trim()),
    `Checked out to ${checkoutEmail.value.trim()}`,
    'Could not check out this book',
  ).then(() => (checkoutEmail.value = ''))

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

async function loadBook() {
  const { data: body } = await booksApi.getBook(bookId)
  if (isApiSuccess(body)) {
    book.value = body.data
    document.title = book.value.title || 'Book Details'
  } else {
    notFound.value = true
  }
}

onMounted(async () => {
  try {
    await loadBook()
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
          <template v-if="isPhysical || loan">
            <p v-if="isPhysical" class="loan-note" data-testid="physical-note">
              <strong>Physical book</strong><template v-if="book.shelf_location"> · Shelf {{ book.shelf_location }}</template>
            </p>
            <p v-if="!loan" class="text-muted loan-note">Reference only: read it in the library.</p>

            <template v-else-if="loan.due_at">
              <RouterLink v-if="!isPhysical" :to="`/read/${bookId}`" class="btn btn-primary full-width" data-testid="read-loan-button">
                Read
              </RouterLink>
              <p class="loan-note" data-testid="loan-due">
                {{
                  isPhysical
                    ? `You have this book. Please return it to the library desk by ${shortDate(loan.due_at)}.`
                    : `Borrowed until ${shortDate(loan.due_at)}.`
                }}
              </p>
              <button
                v-if="loan.renewals_left"
                type="button"
                class="btn btn-outline full-width"
                :disabled="lending"
                data-testid="renew-button"
                @click="handleRenew"
              >
                Renew for {{ loan.loan_days }} days ({{ loan.renewals_left }} left)
              </button>
              <button
                v-if="!isPhysical"
                type="button"
                class="btn btn-outline full-width"
                :disabled="lending"
                data-testid="return-button"
                @click="handleReturn"
              >
                Return now
              </button>
            </template>

            <template v-else-if="loan.pickup_by">
              <p class="loan-note" data-testid="loan-reserved">
                {{
                  isPhysical
                    ? `Reserved for you. Pick it up at the library desk by ${shortDate(loan.pickup_by)}.`
                    : `A copy is held for you until ${shortDate(loan.pickup_by)}.`
                }}
              </p>
              <button
                v-if="!isPhysical"
                type="button"
                class="btn btn-primary full-width"
                :disabled="lending"
                data-testid="start-reading-button"
                @click="handleBorrow"
              >
                Start reading
              </button>
              <button
                type="button"
                class="btn btn-outline full-width"
                :disabled="lending"
                data-testid="cancel-reservation-button"
                @click="handleReturn"
              >
                {{ isPhysical ? 'Cancel reservation' : 'Let it go' }}
              </button>
            </template>

            <template v-else-if="loan.position">
              <p class="loan-note" data-testid="waitlist-position">
                You're #{{ loan.position }} in line. We'll let you know when a copy is
                {{ isPhysical ? 'ready to pick up' : 'free' }}.
              </p>
              <button
                type="button"
                class="btn btn-outline full-width"
                :disabled="lending"
                data-testid="leave-waitlist-button"
                @click="handleLeaveWaitlist"
              >
                Leave the waitlist
              </button>
            </template>

            <template v-else>
              <template v-if="auth.isAuthenticated">
                <button
                  v-if="loan.available > 0 && !loan.waiting"
                  type="button"
                  class="btn btn-primary full-width"
                  :disabled="lending"
                  :data-testid="isPhysical ? 'reserve-button' : 'borrow-button'"
                  @click="handleBorrow"
                >
                  {{ isPhysical ? 'Reserve for pickup' : `Borrow for ${loan.loan_days} days` }}
                </button>
                <button
                  v-else
                  type="button"
                  class="btn btn-outline full-width"
                  :disabled="lending"
                  data-testid="join-waitlist-button"
                  @click="handleJoinWaitlist"
                >
                  All copies are out: join the waitlist
                </button>
              </template>
              <p class="text-muted loan-note" data-testid="loan-availability">
                {{ loan.available }} of {{ loan.copies }}
                {{ isPhysical ? 'on the shelf' : loan.copies === 1 ? 'copy available' : 'copies available' }} ·
                {{ loan.loan_days }}-day loan<template v-if="loan.waiting"> · {{ loan.waiting }} waiting</template>
              </p>
            </template>

            <form
              v-if="auth.isAdmin && isPhysical && loan"
              class="checkout-form"
              data-testid="checkout-form"
              @submit.prevent="handleCheckout"
            >
              <label for="checkout-email">Check out at the desk</label>
              <input
                id="checkout-email"
                v-model="checkoutEmail"
                type="email"
                required
                placeholder="Reader's email"
                data-testid="checkout-email"
              />
              <button type="submit" class="btn btn-outline full-width" :disabled="lending">Check out</button>
            </form>
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
            <template v-if="isPhysical"></template>
            <button
              v-else-if="isDownloadable"
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
            to {{ isPhysical ? 'reserve' : loan ? 'borrow' : 'read online, save, or download' }} this book.
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
