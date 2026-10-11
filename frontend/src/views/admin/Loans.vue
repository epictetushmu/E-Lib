<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import * as adminApi from '@/api/admin'
import type { LoanRecord } from '@/api/admin'
import { isApiSuccess, requestErrorMessage } from '@/types/api'

const loans = ref<LoanRecord[]>([])
const loading = ref(true)
const loadError = ref('')

type Filter = 'all' | LoanRecord['status']
const filter = ref<Filter>('all')
const query = ref('')

const filters = computed(() =>
  (
    [
      ['all', 'All'],
      ['active', 'On loan'],
      ['returned', 'Returned'],
      ['expired', 'Expired'],
    ] as [Filter, string][]
  ).map(([key, label]) => ({
    key,
    label,
    count: key === 'all' ? loans.value.length : loans.value.filter((l) => l.status === key).length,
  })),
)

const borrowers = computed(() => new Set(loans.value.map((l) => l.user_id)).size)

const shown = computed(() => {
  const q = query.value.trim().toLowerCase()
  return loans.value.filter(
    (l) =>
      (filter.value === 'all' || l.status === filter.value) &&
      (!q || [l.book_title, l.book_author, l.username, l.email].some((v) => v?.toLowerCase().includes(q))),
  )
})

function date(iso: string | null): string {
  return iso ? new Date(iso).toLocaleString() : '—'
}

/** How long the reader had it: until returned, until due, or so far. */
function held(l: LoanRecord): string {
  const end = l.returned_at ?? (l.status === 'active' ? new Date().toISOString() : l.due_at)
  if (!l.borrowed_at || !end) return '—'
  const hours = (Date.parse(end) - Date.parse(l.borrowed_at)) / 3_600_000
  return hours < 24 ? `${Math.max(1, Math.round(hours))} h` : `${Math.round(hours / 24)} d`
}

onMounted(async () => {
  try {
    const { data: body } = await adminApi.getLoans()
    if (isApiSuccess(body)) loans.value = body.data
    else loadError.value = 'Failed to load loans.'
  } catch (err) {
    loadError.value = requestErrorMessage(err, 'Failed to load loans.')
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="container admin-page" data-testid="loans-page">
    <header class="admin-header">
      <h1>Loans</h1>
      <div class="header-actions">
        <RouterLink to="/admin" class="link-btn">Back to books</RouterLink>
      </div>
    </header>

    <div v-if="loading" class="spinner" role="status" aria-label="Loading loans"></div>
    <div v-else-if="loadError" class="state-message"><p>{{ loadError }}</p></div>
    <div v-else-if="!loans.length" class="state-message">
      <p>No books have been borrowed yet. Set "Copies to lend" on a book to make it borrowable.</p>
    </div>

    <template v-else>
      <p class="text-muted">
        {{ loans.length }} loans by {{ borrowers }} {{ borrowers === 1 ? 'reader' : 'readers' }} (newest 1000).
      </p>
      <div class="toolbar">
        <div class="filters" role="group" aria-label="Show loans">
          <button
            v-for="f in filters"
            :key="f.key"
            type="button"
            class="filter"
            :aria-pressed="filter === f.key"
            @click="filter = f.key"
          >
            {{ f.label }} <span class="count">{{ f.count }}</span>
          </button>
        </div>
        <label class="search">
          <span class="visually-hidden">Find by book or reader</span>
          <input v-model="query" type="search" placeholder="Find by book or reader" />
        </label>
      </div>

      <div class="table-wrap">
        <table class="admin-table" data-testid="loans-table">
          <thead>
            <tr>
              <th>Book</th>
              <th>Reader</th>
              <th>Borrowed</th>
              <th>Due</th>
              <th>Returned</th>
              <th class="num">Held</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="l in shown" :key="`${l.book_id}-${l.user_id}-${l.borrowed_at}`">
              <td>
                <RouterLink v-if="l.book_title" :to="`/books/${l.book_id}`">{{ l.book_title }}</RouterLink>
                <span v-else class="text-muted">Deleted book</span>
                <div v-if="l.book_author" class="text-muted">{{ l.book_author }}</div>
              </td>
              <td>
                {{ l.username ?? 'Deleted user' }}
                <div v-if="l.email" class="text-muted">{{ l.email }}</div>
              </td>
              <td>{{ date(l.borrowed_at) }}</td>
              <td>{{ date(l.due_at) }}</td>
              <td>{{ date(l.returned_at) }}</td>
              <td class="num">{{ held(l) }}</td>
              <td>{{ filters.find((f) => f.key === l.status)?.label }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>

<style scoped src="@/css/views/admin/Dashboard.css"></style>
