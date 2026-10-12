<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import * as friendsApi from '@/api/friends'
import { isApiSuccess, apiErrorMessage, requestErrorMessage, type ApiError } from '@/types/api'
import { fromServerTime, shortDate } from '@/utils/date'
import BookCard from '@/components/BookCard.vue'
import StarRating from '@/components/StarRating.vue'

// Same names as the switches in Profile → Friends, so readers recognise what they turned on.
const bookSections = [
  { key: 'saved', title: 'Saved books', privateName: 'saved books' },
  { key: 'loans', title: 'Borrowing now', privateName: 'books they’re borrowing' },
  { key: 'downloads', title: 'Downloaded books', privateName: 'downloads' },
] as const

const route = useRoute()
const library = ref<friendsApi.FriendLibrary | null>(null)
const error = ref('')

const shared = computed(() => bookSections.filter((s) => library.value?.[s.key] != null))
const privateNames = computed(() => {
  if (!library.value) return []
  const names: string[] = bookSections.filter((s) => library.value![s.key] === null).map((s) => s.privateName)
  if (library.value.reviews === null) names.push('reviews')
  return names
})
const privateList = computed(() => {
  const n = privateNames.value
  return n.length < 2 ? (n[0] ?? '') : `${n.slice(0, -1).join(', ')} and ${n[n.length - 1]}`
})

onMounted(async () => {
  try {
    const { data: body } = await friendsApi.getFriendLibrary(String(route.params.id))
    if (isApiSuccess(body)) library.value = body.data
    else error.value = apiErrorMessage(body as ApiError, 'Could not load this library')
  } catch (err) {
    error.value = requestErrorMessage(err, 'Could not load this library')
  }
})
</script>

<template>
  <div class="container profile-page" data-testid="friend-library">
    <RouterLink to="/profile?tab=friends" class="back-link">Back to friends</RouterLink>

    <div v-if="error" class="empty-state">
      <p>{{ error }}</p>
      <RouterLink to="/profile?tab=friends" class="btn btn-primary">Go to your friends</RouterLink>
    </div>
    <div v-else-if="!library" class="spinner" role="status" aria-label="Loading library"></div>
    <template v-else>
      <header class="card profile-header">
        <div class="avatar-lg">{{ (library.username || '?').charAt(0).toUpperCase() }}</div>
        <div>
          <h1>{{ library.username }}</h1>
          <p v-if="privateNames.length" class="text-muted" data-testid="friend-private">
            Keeps their {{ privateList }} private.
          </p>
        </div>
      </header>

      <section v-for="section in shared" :key="section.key" class="friend-section">
        <h2>{{ section.title }} <span class="section-count">{{ library[section.key]!.length }}</span></h2>
        <p v-if="!library[section.key]!.length" class="text-muted">Nothing here yet.</p>
        <div v-else class="grid-books" :data-testid="`friend-${section.key}`">
          <BookCard v-for="book in library[section.key]!" :key="String(book._id)" :book="book" />
        </div>
      </section>

      <section v-if="library.reviews" class="friend-section">
        <h2>Reviews <span class="section-count">{{ library.reviews.length }}</span></h2>
        <p v-if="!library.reviews.length" class="text-muted">Nothing here yet.</p>
        <ul v-else class="friend-reviews" data-testid="friend-reviews">
          <li v-for="r in library.reviews" :key="r.book_id" class="card friend-review">
            <RouterLink :to="`/books/${r.book_id}`" class="review-cover" tabindex="-1" aria-hidden="true">
              <img :src="r.thumbnail" alt="" loading="lazy" />
            </RouterLink>
            <div class="review-body">
              <RouterLink :to="`/books/${r.book_id}`" class="loan-title">{{ r.title }}</RouterLink>
              <span class="review-meta">
                <StarRating :rating="r.rating" />
                <span class="text-muted">{{ r.author }}, reviewed {{ shortDate(fromServerTime(r.created_at)) }}</span>
              </span>
              <p v-if="r.comment" class="review-comment">{{ r.comment }}</p>
            </div>
          </li>
        </ul>
      </section>

      <p v-if="!shared.length && !library.reviews" class="empty-state text-muted">
        {{ library.username }} isn’t sharing anything with friends yet.
      </p>
    </template>
  </div>
</template>

<style scoped src="@/css/views/Profile.css"></style>
<style scoped>
.back-link {
  display: inline-block;
  margin-bottom: 0.75rem;
  font-size: 0.9rem;
  color: var(--color-text-muted);
}

.back-link:hover {
  color: var(--color-primary);
}

.profile-header p {
  margin: 0;
}

.friend-section {
  margin-top: 2.25rem;
}

.friend-section h2 {
  font-size: 1.5rem;
  margin-bottom: 0.9rem;
}

.section-count {
  font-family: var(--font-sans);
  font-size: 0.9rem;
  font-weight: 500;
  color: var(--color-text-muted);
}

.friend-reviews {
  list-style: none;
  padding: 0;
  margin: 0;
  display: grid;
  gap: 0.75rem;
  max-width: 760px;
}

.friend-review {
  display: flex;
  gap: 1rem;
  padding: 0.9rem 1rem;
}

.review-cover {
  flex: 0 0 56px;
}

.review-cover img {
  display: block;
  width: 56px;
  height: 78px;
  object-fit: cover;
  border-radius: var(--radius);
  box-shadow: var(--shadow-sm);
}

.review-body {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  min-width: 0;
}

.review-body .loan-title {
  overflow-wrap: anywhere; /* long unbroken titles must not push the page sideways on phones */
}

.review-meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.85rem;
}

.review-comment {
  margin: 0.25rem 0 0;
  max-width: 65ch;
}
</style>
