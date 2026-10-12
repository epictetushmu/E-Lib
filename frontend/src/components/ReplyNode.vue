<script setup lang="ts">
import { computed, ref } from 'vue'
import * as reviewsApi from '@/api/reviews'
import type { ReviewReply } from '@/types/review'
import { useAuthStore } from '@/stores/auth'
import { useReplyWrite } from '@/composables/useReplyWrite'
import { shortDateTime } from '@/utils/date'

/** One reply and, recursively, the replies answering it. */
const props = defineProps<{
  reply: ReviewReply
  /** Every reply in this review's thread, for finding the answers to this one. */
  thread: ReviewReply[]
  bookId: string
  reviewUserId: string
  depth: number
}>()
const emit = defineEmits<{ changed: [] }>()

const auth = useAuthStore()
const { busy, write } = useReplyWrite(() => emit('changed'))

const answers = computed(() => props.thread.filter((r) => r.parent_id === props.reply.id))
const mine = computed(() => !props.reply.deleted && props.reply.user_id === auth.claims?.user_id)

const answering = ref(false)
const draft = ref('')
const editing = ref(false)
const editText = ref('')

async function postAnswer() {
  const text = draft.value.trim()
  if (!text) return
  const ok = await write(
    () => reviewsApi.addReply(props.bookId, props.reviewUserId, props.reply.id, text),
    'Reply posted',
    'Could not post your reply',
  )
  if (ok) {
    draft.value = ''
    answering.value = false
  }
}

function startEdit() {
  editText.value = props.reply.text
  editing.value = true
}

async function saveEdit() {
  const text = editText.value.trim()
  if (!text) return
  if (await write(() => reviewsApi.updateReply(props.reply.id, text), 'Reply updated', 'Could not update your reply')) {
    editing.value = false
  }
}

async function remove() {
  if (!window.confirm('Delete your reply?')) return
  await write(() => reviewsApi.deleteReply(props.reply.id), 'Reply deleted', 'Could not delete your reply')
}
</script>

<template>
  <li class="reply" :class="{ 'is-deleted': reply.deleted }" data-testid="reply">
    <p v-if="reply.deleted" class="reply-text text-muted">[deleted]</p>
    <template v-else>
      <div class="reply-meta">
        <strong>{{ reply.username }}</strong>
        <span class="text-muted">
          {{ shortDateTime(reply.created_at) }}<template v-if="reply.updated_at"> (edited)</template>
        </span>
      </div>

      <form v-if="editing" class="reply-form" @submit.prevent="saveEdit">
        <label class="visually-hidden" :for="`edit-${reply.id}`">Edit your reply</label>
        <textarea :id="`edit-${reply.id}`" v-model="editText" rows="2" maxlength="2000" required></textarea>
        <div class="reply-form-actions">
          <button type="submit" class="btn btn-primary btn-sm" :disabled="busy">Save reply</button>
          <button type="button" class="btn btn-outline btn-sm" @click="editing = false">Cancel</button>
        </div>
      </form>
      <template v-else>
        <p class="reply-text">{{ reply.text }}</p>
        <div class="review-actions">
          <button
            v-if="auth.isAuthenticated"
            type="button"
            class="link-btn"
            :aria-expanded="answering"
            data-testid="reply-button"
            @click="answering = !answering"
          >
            Reply
          </button>
          <template v-if="mine">
            <button type="button" class="link-btn" data-testid="edit-reply-button" @click="startEdit">Edit</button>
            <button type="button" class="link-btn danger" :disabled="busy" data-testid="delete-reply-button" @click="remove">
              Delete
            </button>
          </template>
        </div>
      </template>
    </template>

    <!-- Indentation stops growing after 4 levels, so deep threads stay readable on a phone. -->
    <ol
      v-if="answers.length || answering"
      class="thread"
      :class="{ 'thread-flat': depth >= 4 }"
      :aria-label="reply.deleted ? 'Replies to a deleted reply' : `Replies to ${reply.username}`"
    >
      <li v-if="answering" class="reply">
        <form class="reply-form" @submit.prevent="postAnswer">
          <label class="visually-hidden" :for="`answer-${reply.id}`">Reply to {{ reply.username }}</label>
          <textarea
            :id="`answer-${reply.id}`"
            v-model="draft"
            rows="2"
            maxlength="2000"
            required
            :placeholder="`Reply to ${reply.username}`"
            data-testid="reply-input"
          ></textarea>
          <div class="reply-form-actions">
            <button type="submit" class="btn btn-primary btn-sm" :disabled="busy" data-testid="post-reply-button">
              Post reply
            </button>
            <button type="button" class="btn btn-outline btn-sm" @click="answering = false">Cancel</button>
          </div>
        </form>
      </li>
      <ReplyNode
        v-for="answer in answers"
        :key="answer.id"
        :reply="answer"
        :thread="thread"
        :book-id="bookId"
        :review-user-id="reviewUserId"
        :depth="depth + 1"
        @changed="emit('changed')"
      />
    </ol>
  </li>
</template>

<style scoped src="@/css/components/ReviewList.css"></style>
