<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import * as booksApi from '@/api/books'
import { isApiSuccess } from '@/types/api'
import type { Book, ContentSearchResult } from '@/types/book'
import { idToString } from '@/types/book'
import BookCard from '@/components/BookCard.vue'
import { useContentSearchEnabled } from '@/composables/useContentSearchEnabled'

type Mode = 'title' | 'content'

const route = useRoute()
const router = useRouter()

const searchTerm = ref(typeof route.query.q === 'string' ? route.query.q : '')
const contentSearchEnabled = useContentSearchEnabled()
// With content search switched off, an old ?mode=content link just shows title results.
const mode = computed<Mode>(() => (contentSearchEnabled.value && route.query.mode === 'content' ? 'content' : 'title'))
const books = ref<Book[]>([])
const contentResults = ref<ContentSearchResult[]>([])
const loading = ref(false)
const error = ref('')
const hasSearched = ref(false)

/** Bumped per search; a response for anything but the latest search is dropped, so a slow earlier one can't overwrite it. */
let latestSearch = 0

async function runSearch(term: string, searchMode: Mode) {
  const id = ++latestSearch
  const trimmed = term.trim()
  books.value = []
  contentResults.value = []
  if (!trimmed) {
    hasSearched.value = false
    error.value = ''
    loading.value = false
    return
  }
  loading.value = true
  error.value = ''
  hasSearched.value = true
  try {
    if (searchMode === 'content') {
      const { data } = await booksApi.contentSearch(trimmed)
      if (id !== latestSearch) return
      contentResults.value = isApiSuccess(data) ? data.data : []
      if (!contentResults.value.length) error.value = `No pages found for "${trimmed}".`
    } else {
      const { data } = await booksApi.searchBooks(trimmed)
      if (id !== latestSearch) return
      books.value = isApiSuccess(data) ? data.data : []
      if (!books.value.length) error.value = `No books found for "${trimmed}".`
    }
  } catch {
    if (id === latestSearch) error.value = 'An error occurred while searching.'
  } finally {
    if (id === latestSearch) loading.value = false
  }
}

function submit() {
  router.push({ path: '/search', query: { q: searchTerm.value, ...(mode.value === 'content' ? { mode: 'content' } : {}) } })
}

function setMode(next: Mode) {
  router.replace({ path: '/search', query: { ...route.query, mode: next === 'content' ? 'content' : undefined } })
}

watch(
  () => [route.query.q, mode.value] as const,
  ([q, searchMode]) => {
    searchTerm.value = typeof q === 'string' ? q : ''
    runSearch(searchTerm.value, searchMode)
  },
  { immediate: true },
)
</script>

<template>
  <div class="container search-page" data-testid="search-page">
    <h1>Search</h1>
    <div v-if="contentSearchEnabled" class="search-modes" role="group" aria-label="What to search">
      <button
        type="button"
        class="mode-btn"
        :aria-pressed="mode === 'title'"
        data-testid="search-mode-title"
        @click="setMode('title')"
      >
        Titles
      </button>
      <button
        type="button"
        class="mode-btn"
        :aria-pressed="mode === 'content'"
        data-testid="search-mode-content"
        @click="setMode('content')"
      >
        Inside books
      </button>
    </div>
    <form class="search-bar" data-testid="search-form" @submit.prevent="submit">
      <input
        v-model="searchTerm"
        type="search"
        :placeholder="mode === 'content' ? 'Search for a topic, idea or phrase…' : 'Search by title…'"
        :maxlength="mode === 'content' ? 300 : undefined"
        aria-label="Search books"
        data-testid="search-input"
      />
      <button type="submit" class="btn btn-primary" data-testid="search-submit">Search</button>
    </form>

    <div v-if="loading" class="spinner" role="status" aria-label="Searching" data-testid="search-loading"></div>
    <p v-else-if="error" class="text-muted" data-testid="search-message">{{ error }}</p>
    <ol v-else-if="hasSearched && mode === 'content'" class="content-results" data-testid="content-results">
      <li v-for="result in contentResults" :key="idToString(result.book._id)" class="content-result">
        <BookCard :book="result.book" />
        <ul class="matches">
          <li v-for="match in result.matches" :key="match.page">
            <RouterLink
              :to="{ path: `/read/${idToString(result.book._id)}`, query: { page: match.page } }"
              class="match"
              :data-testid="`content-match-${idToString(result.book._id)}-${match.page}`"
            >
              <span class="match-page">Page {{ match.page }}</span>
              <span class="match-snippet">{{ match.snippet }}</span>
            </RouterLink>
          </li>
        </ul>
      </li>
    </ol>
    <div v-else-if="hasSearched" class="grid-books" data-testid="search-results">
      <BookCard v-for="book in books" :key="String(book._id)" :book="book" />
    </div>
    <p v-else class="text-muted" data-testid="search-message">
      {{
        mode === 'content'
          ? 'Describe what you are looking for, in Greek or English, to find the pages that cover it.'
          : 'Enter a title to search the catalog.'
      }}
    </p>
  </div>
</template>

<style scoped src="@/css/views/SearchResults.css"></style>
