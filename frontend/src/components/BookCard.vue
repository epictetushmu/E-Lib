<script setup lang="ts">
import { computed } from 'vue'
import type { Book } from '@/types/book'
import { idToString } from '@/types/book'
import { thumbnailUrl } from '@/api/books'
import { onThumbnailError } from '@/utils/dom'

const props = defineProps<{ book: Book; removable?: boolean }>()
const emit = defineEmits<{ remove: [] }>()

const id = computed(() => idToString(props.book._id))
const cover = computed(() => props.book.thumbnail || thumbnailUrl(id.value))
const visibleCategories = computed(() => (props.book.categories ?? []).slice(0, 2))
const extraCategoryCount = computed(() => Math.max((props.book.categories?.length ?? 0) - 2, 0))
</script>

<template>
  <RouterLink :to="`/books/${id}`" class="book-card" :data-testid="`book-card-${id}`">
    <button
      v-if="removable"
      type="button"
      class="remove-btn"
      aria-label="Remove from saved books"
      data-testid="book-card-remove"
      @click.stop.prevent="emit('remove')"
    >
      &times;
    </button>
    <img :src="cover" class="cover" :alt="`Cover of ${book.title}`" @error="onThumbnailError" />
    <div class="body">
      <h3 class="title" :title="book.title" data-testid="book-card-title">{{ book.title || 'Unknown Title' }}</h3>
      <p class="author">By {{ book.author || 'Unknown Author' }}</p>
      <p v-if="book.year" class="year">{{ book.year }}</p>
      <div v-if="visibleCategories.length" class="categories">
        <span v-for="category in visibleCategories" :key="category" class="badge">{{ category }}</span>
        <span v-if="extraCategoryCount > 0" class="badge">+{{ extraCategoryCount }} more</span>
      </div>
      <span class="view-link">View details</span>
    </div>
  </RouterLink>
</template>

<style scoped src="@/css/components/BookCard.css"></style>
