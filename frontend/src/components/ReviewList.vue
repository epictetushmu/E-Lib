<script setup lang="ts">
import { reactive, ref } from 'vue'
import * as reviewsApi from '@/api/reviews'
import type { Review } from '@/types/review'
import { useAuthStore } from '@/stores/auth'
import { useReplyWrite } from '@/composables/useReplyWrite'
import { fromServerTime, shortDate } from '@/utils/date'
import StarRating from '@/components/StarRating.vue'
import ReplyNode from '@/components/ReplyNode.vue'

const props = defineProps<{ reviews: Review[]; bookId: string }>()
/** Something changed server-side; the parent reloads the reviews. */
const emit = defineEmits<{ changed: [] }>()

const auth = useAuthStore()
const { busy, write } = useReplyWrite(() => emit('changed'))

/** The review whose reply box is open (keyed by the review author's id), and the drafts. */
const replyingTo = ref<string | null>(null)
const drafts = reactive<Record<string, string>>({})

const topLevel = (review: Review) => (review.replies ?? []).filter((r) => !r.parent_id)

function editMyReview() {
  const field = document.getElementById('review-comment')
  field?.scrollIntoView({ behavior: 'smooth', block: 'center' })
  field?.focus({ preventScroll: true })
}

async function deleteMyReview() {
  if (!window.confirm('Delete your review? Its replies will be deleted too.')) return
  await write(() => reviewsApi.deleteReview(props.bookId), 'Review deleted', 'Could not delete your review')
}

async function postReply(review: Review) {
  const text = (drafts[review.user_id] ?? '').trim()
  if (!text) return
  const ok = await write(
    () => reviewsApi.addReply(props.bookId, review.user_id, null, text),
    'Reply posted',
    'Could not post your reply',
  )
  if (ok) {
    drafts[review.user_id] = ''
    replyingTo.value = null
  }
}
</script>

<template>
  <div class="review-list">
    <h3>Reviews</h3>
    <p v-if="!reviews.length" class="text-muted">No reviews yet.</p>
    <ul v-else class="reviews">
      <li v-for="review in reviews" :key="review.user_id" class="review" data-testid="review">
        <div class="review-header">
          <strong>{{ review.username }}</strong>
          <StarRating :rating="review.rating" />
          <span class="review-date text-muted">{{ shortDate(fromServerTime(review.created_at)) }}</span>
        </div>
        <p class="review-comment">{{ review.comment }}</p>

        <div class="review-actions">
          <button
            v-if="auth.isAuthenticated"
            type="button"
            class="link-btn"
            :aria-expanded="replyingTo === review.user_id"
            data-testid="review-reply-button"
            @click="replyingTo = replyingTo === review.user_id ? null : review.user_id"
          >
            Reply
          </button>
          <template v-if="review.user_id === auth.claims?.user_id">
            <button type="button" class="link-btn" data-testid="edit-review-button" @click="editMyReview">Edit</button>
            <button
              type="button"
              class="link-btn danger"
              :disabled="busy"
              data-testid="delete-review-button"
              @click="deleteMyReview"
            >
              Delete
            </button>
          </template>
        </div>

        <ol
          v-if="topLevel(review).length || replyingTo === review.user_id"
          class="thread"
          :aria-label="`Replies to ${review.username}`"
        >
          <li v-if="replyingTo === review.user_id" class="reply">
            <form class="reply-form" @submit.prevent="postReply(review)">
              <label class="visually-hidden" :for="`reply-${review.user_id}`">Reply to {{ review.username }}</label>
              <textarea
                :id="`reply-${review.user_id}`"
                v-model="drafts[review.user_id]"
                rows="2"
                maxlength="2000"
                required
                :placeholder="`Reply to ${review.username}`"
                data-testid="review-reply-input"
              ></textarea>
              <div class="reply-form-actions">
                <button type="submit" class="btn btn-primary btn-sm" :disabled="busy" data-testid="review-post-reply-button">
                  Post reply
                </button>
                <button type="button" class="btn btn-outline btn-sm" @click="replyingTo = null">Cancel</button>
              </div>
            </form>
          </li>
          <ReplyNode
            v-for="reply in topLevel(review)"
            :key="reply.id"
            :reply="reply"
            :thread="review.replies ?? []"
            :book-id="bookId"
            :review-user-id="review.user_id"
            :depth="1"
            @changed="emit('changed')"
          />
        </ol>
      </li>
    </ul>
  </div>
</template>

<style scoped src="@/css/components/ReviewList.css"></style>
