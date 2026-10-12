<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import * as adminApi from '@/api/admin'
import type { LoanRecord } from '@/types/book'
import LoanStatus from '@/components/LoanStatus.vue'
import { shortDate } from '@/utils/date'
import { isApiSuccess, apiErrorMessage, requestErrorMessage, type ApiError } from '@/types/api'
import { useToast } from '@/composables/useToast'

const toast = useToast()
const loans = ref<LoanRecord[]>([])
const loading = ref(true)
const loadError = ref('')
const busy = ref<string | null>(null)

/** Filters group the statuses a librarian acts on; "Current" is everything still holding a copy. */
const FILTERS = {
  all: { label: 'All', match: () => true },
  current: { label: 'Current', match: (l: LoanRecord) => ['active', 'reserved', 'out', 'overdue'].includes(l.status) },
  reserved: { label: 'Awaiting pickup', match: (l: LoanRecord) => l.status === 'reserved' },
  overdue: { label: 'Overdue', match: (l: LoanRecord) => l.status === 'overdue' },
  past: { label: 'Past', match: (l: LoanRecord) => ['returned', 'expired', 'cancelled', 'not_collected'].includes(l.status) },
} as const
type Filter = keyof typeof FILTERS
const filter = ref<Filter>('current')
const query = ref('')

const filters = computed(() =>
  (Object.keys(FILTERS) as Filter[]).map((key) => ({
    key,
    label: FILTERS[key].label,
    count: loans.value.filter(FILTERS[key].match).length,
  })),
)

const borrowers = computed(() => new Set(loans.value.map((l) => l.user_id)).size)

const shown = computed(() => {
  const q = query.value.trim().toLowerCase()
  return loans.value.filter(
    (l) =>
      FILTERS[filter.value].match(l) &&
      (!q || [l.book_title, l.book_author, l.username, l.email].some((v) => v?.toLowerCase().includes(q))),
  )
})

const date = (iso: string | null) => shortDate(iso) || '—'

async function load() {
  try {
    const { data: body } = await adminApi.getLoans()
    if (isApiSuccess(body)) loans.value = body.data
    else loadError.value = 'Failed to load loans.'
  } catch (err) {
    loadError.value = requestErrorMessage(err, 'Failed to load loans.')
  } finally {
    loading.value = false
  }
}

/** Picked up: a reservation becomes a loan (checkout by the reader's email). */
async function act(l: LoanRecord, action: 'pickup' | 'checkin' | 'renew') {
  busy.value = l.id
  try {
    const { data: body } =
      action === 'pickup'
        ? await adminApi.checkOut(l.book_id, l.email ?? '')
        : action === 'renew'
          ? await adminApi.renewLoan(l.book_id, l.user_id)
          : await adminApi.checkIn(l.book_id, l.user_id)
    if (isApiSuccess(body as ApiError)) {
      const done = { pickup: 'Checked out', renew: 'Renewed', checkin: l.status === 'reserved' ? 'Reservation cancelled' : 'Checked in' }
      toast.success(done[action])
      await load()
    } else {
      toast.error(apiErrorMessage(body as ApiError, 'That did not work'))
    }
  } catch (err) {
    toast.error(requestErrorMessage(err, 'That did not work'))
  } finally {
    busy.value = null
  }
}

onMounted(load)
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
      <p>No loans yet. Lend a PDF with "Copies to lend", or add a physical book; walk-ins are checked out from the book's page.</p>
    </div>

    <template v-else>
      <p class="text-muted">
        {{ loans.length }} loans by {{ borrowers }} {{ borrowers === 1 ? 'reader' : 'readers' }} (newest 1000).
        Walk-ins are checked out from the book's page.
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
              <th class="col-book">Book</th>
              <th>Reader</th>
              <th>Reserved</th>
              <th>Borrowed</th>
              <th>Due</th>
              <th>Returned</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="l in shown" :key="l.id" data-testid="loan-row">
              <td>
                <RouterLink v-if="l.book_title" :to="`/books/${l.book_id}`">{{ l.book_title }}</RouterLink>
                <span v-else class="text-muted">Deleted book</span>
                <div class="text-muted sub">
                  {{ l.kind === 'physical' ? 'Physical' : 'PDF' }}<span v-if="l.shelf_location" class="callno">
                    {{ l.shelf_location }}</span
                  >
                </div>
              </td>
              <td class="reader-cell">
                {{ l.username ?? 'Deleted user' }}
                <div v-if="l.email" class="text-muted sub">{{ l.email }}</div>
              </td>
              <td data-label="Reserved" :class="{ empty: !l.reserved_at }">
                {{ date(l.reserved_at) }}
                <div v-if="l.status === 'reserved'" class="text-muted sub">until {{ date(l.pickup_by) }}</div>
              </td>
              <td data-label="Borrowed" :class="{ empty: !l.borrowed_at }">{{ date(l.borrowed_at) }}</td>
              <td data-label="Due" :class="{ empty: !l.due_at }">
                {{ date(l.due_at) }}
                <div v-if="l.renewals" class="text-muted sub">renewed {{ l.renewals }}×</div>
              </td>
              <td
                :data-label="l.cancelled_at ? 'Cancelled' : 'Returned'"
                :class="{ empty: !(l.returned_at ?? l.cancelled_at) }"
              >
                {{ date(l.returned_at ?? l.cancelled_at) }}
              </td>
              <td class="status-cell"><LoanStatus :status="l.status" /></td>
              <td>
                <div class="row-actions">
                <template v-if="l.status === 'reserved' && l.kind === 'physical'">
                  <button type="button" class="link-btn" :disabled="busy === l.id || !l.email" @click="act(l, 'pickup')">
                    Picked up
                  </button>
                  <button type="button" class="link-btn danger" :disabled="busy === l.id" @click="act(l, 'checkin')">
                    Cancel
                  </button>
                </template>
                <template v-else-if="l.status === 'out' || l.status === 'overdue'">
                  <button type="button" class="link-btn" :disabled="busy === l.id" @click="act(l, 'checkin')">Check in</button>
                  <button
                    type="button"
                    class="link-btn"
                    :disabled="busy === l.id || l.renewals >= 2"
                    :title="l.renewals >= 2 ? 'Already renewed twice' : ''"
                    @click="act(l, 'renew')"
                  >
                    Renew
                  </button>
                </template>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>

<style scoped src="@/css/views/admin/Dashboard.css"></style>
<style scoped>
.col-book {
  min-width: 15rem;
}

.admin-table td {
  white-space: nowrap;
}

.admin-table td:first-child {
  white-space: normal;
}

.sub {
  font-size: 0.82rem;
}

.callno::before {
  content: '';
  display: inline-block;
  width: 3px;
  height: 0.8em;
  margin: 0 0.4rem 0 0.45rem;
  background: var(--color-accent);
  vertical-align: -0.05em;
}

/* Phones: Dashboard.css stacks each row; give the dates their names and drop the empty ones. */
@media (max-width: 760px) {
  .admin-table tbody tr:not(.no-match) {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .reader-cell {
    grid-column: 1 / -1;
  }

  td.empty {
    display: none;
  }

  td[data-label]::before {
    content: attr(data-label);
    display: block;
    font-size: 0.75rem;
    color: var(--color-text-muted);
  }

  .row-actions {
    justify-content: flex-start;
  }
}

.row-actions {
  display: flex;
  gap: 0.9rem;
  justify-content: flex-end;
}
</style>
