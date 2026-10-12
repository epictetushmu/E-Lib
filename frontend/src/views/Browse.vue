<script setup lang="ts">
import { onMounted, ref } from 'vue'
import * as booksApi from '@/api/books'
import { isApiSuccess } from '@/types/api'
import type { Book } from '@/types/book'
import BookCard from '@/components/BookCard.vue'

const books = ref<Book[]>([])
const loading = ref(true)
const error = ref('')

onMounted(async () => {
  try {
    const response = await booksApi.listBooks()
    const body = response.data
    books.value = isApiSuccess(body) ? body.data : []
    if (!books.value.length) {
      error.value = 'No books found.'
    }
  } catch {
    error.value = 'An error occurred while loading the catalog.'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="container browse-page" data-testid="browse-page">
    <h1>Browse the Collection</h1>

    <div v-if="loading" class="spinner" role="status" aria-label="Loading books" data-testid="browse-loading"></div>
    <p v-else-if="error" class="text-muted" data-testid="browse-error">{{ error }}</p>
    <div v-else class="grid-books" data-testid="browse-grid">
      <BookCard v-for="book in books" :key="String(book._id)" :book="book" />
    </div>
  </div>
</template>

<style scoped src="@/css/views/Browse.css"></style>
