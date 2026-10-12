<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import * as friendsApi from '@/api/friends'
import { isApiSuccess, apiErrorMessage, requestErrorMessage, type ApiError } from '@/types/api'
import { fromServerTime, shortDate } from '@/utils/date'
import BookCard from '@/components/BookCard.vue'
import StarRating from '@/components/StarRating.vue'

const route = useRoute()
const library = ref<friendsApi.FriendLibrary | null>(null)
const error = ref('')

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
    <p v-if="error" class="alert alert-danger">{{ error }}</p>
    <div v-else-if="!library" class="spinner" role="status" aria-label="Loading library"></div>
    <template v-else>
      <div class="card profile-header">
        <div class="avatar-lg">{{ (library.username || '?').charAt(0).toUpperCase() }}</div>
        <div>
          <h1>{{ library.username }}</h1>
          <RouterLink to="/profile" class="text-muted small">← Back to your profile</RouterLink>
        </div>
      </div>

      <h2>Saved books</h2>
      <p v-if="!library.saved.length" class="text-muted">No saved books yet.</p>
      <div v-else class="grid-books" data-testid="friend-saved">
        <BookCard v-for="book in library.saved" :key="String(book._id)" :book="book" />
      </div>

      <h2>Reviews</h2>
      <p v-if="!library.reviews.length" class="text-muted">No reviews yet.</p>
      <ul v-else class="loan-list" data-testid="friend-reviews">
        <li v-for="r in library.reviews" :key="r.book_id" class="card loan-item">
          <div class="loan-main">
            <RouterLink :to="`/books/${r.book_id}`" class="loan-title">{{ r.title }}</RouterLink>
            <span class="loan-when">{{ r.author }} · {{ shortDate(fromServerTime(r.created_at)) }}</span>
            <span v-if="r.comment">{{ r.comment }}</span>
          </div>
          <div class="loan-side"><StarRating :rating="r.rating" /></div>
        </li>
      </ul>
    </template>
  </div>
</template>

<style scoped src="@/css/views/Profile.css"></style>
