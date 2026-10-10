<script setup lang="ts">
import { ref } from 'vue'
import * as reviewsWriteApi from '@/api/reviews'
import type { Review } from '@/types/review'
import { isApiSuccess, apiErrorMessage, requestErrorMessage, type ApiError } from '@/types/api'
import { useToast } from '@/composables/useToast'

// existing: the current user's review of this book, if any — the form then edits it instead of adding one.
const props = defineProps<{ bookId: string; existing?: Review }>()
const emit = defineEmits<{ submitted: [] }>()

const toast = useToast()

const rating = ref(props.existing?.rating ?? 0)
const hoverRating = ref(0)
const comment = ref(props.existing?.comment ?? '')
const submitting = ref(false)
const error = ref('')

async function submit() {
  error.value = ''
  if (rating.value < 1) {
    error.value = 'Please select a rating'
    return
  }
  if (!comment.value.trim()) {
    error.value = 'Please enter a comment'
    return
  }

  submitting.value = true
  try {
    const response = await reviewsWriteApi.addReview(props.bookId, rating.value, comment.value.trim())
    const body = response.data
    if (isApiSuccess(body)) {
      toast.success(props.existing ? 'Your review has been updated' : 'Your review has been submitted')
      emit('submitted')
    } else {
      error.value = apiErrorMessage(body as ApiError, 'Error submitting review')
    }
  } catch (err) {
    error.value = requestErrorMessage(err, 'Error submitting review. Please try again.')
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <form class="review-form card" @submit.prevent="submit">
    <h4>{{ existing ? 'Edit Your Review' : 'Add Your Review' }}</h4>
    <p v-if="error" class="alert alert-danger" role="alert">{{ error }}</p>

    <div class="star-picker" role="radiogroup" aria-label="Rating">
      <button
        v-for="i in 5"
        :key="i"
        type="button"
        class="star-btn"
        :class="{ filled: i <= (hoverRating || rating) }"
        :aria-checked="i === rating"
        :aria-label="`${i} star${i > 1 ? 's' : ''}`"
        role="radio"
        @click="rating = i"
        @mouseenter="hoverRating = i"
        @mouseleave="hoverRating = 0"
      >
        ★
      </button>
    </div>

    <div class="form-field">
      <label for="review-comment">Comment</label>
      <textarea id="review-comment" v-model="comment" rows="3" required></textarea>
    </div>

    <button type="submit" class="btn btn-primary" :disabled="submitting">
      {{ submitting ? 'Saving…' : existing ? 'Update Review' : 'Submit Review' }}
    </button>
  </form>
</template>

<style scoped src="@/css/components/ReviewForm.css"></style>
