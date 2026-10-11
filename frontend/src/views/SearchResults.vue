<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter, type LocationQueryRaw } from 'vue-router'
import * as booksApi from '@/api/books'
import { isApiSuccess } from '@/types/api'
import type { CatalogResult, ContentSearchResult, FacetOption } from '@/types/book'
import { idToString } from '@/types/book'
import BookCard from '@/components/BookCard.vue'

type Mode = 'title' | 'content'
type Facet = 'category' | 'format' | 'author'

const SORTS = [
  { value: 'title', label: 'Title A–Z' },
  { value: 'newest', label: 'Recently added' },
  { value: 'year_desc', label: 'Newest published' },
  { value: 'year_asc', label: 'Oldest published' },
  { value: 'views', label: 'Most viewed' },
]
const FACET_GROUPS: { key: Facet; label: string }[] = [
  { key: 'category', label: 'Subject' },
  { key: 'author', label: 'Author' },
  { key: 'format', label: 'Format' },
]

const route = useRoute()
const router = useRouter()

/** Every filter lives in the URL, so a search can be shared, bookmarked and stepped back through. */
function list(key: string): string[] {
  const v = route.query[key]
  return (Array.isArray(v) ? v : v ? [v] : []).filter((x): x is string => typeof x === 'string' && x !== '')
}

const searchTerm = ref('')
const mode = computed<Mode>(() => (route.query.mode === 'content' ? 'content' : 'title'))
const selected = computed<Record<Facet, string[]>>(() => ({
  category: list('category'),
  format: list('format'),
  author: list('author'),
}))
const yearFrom = computed(() => (typeof route.query.year_from === 'string' ? route.query.year_from : ''))
const yearTo = computed(() => (typeof route.query.year_to === 'string' ? route.query.year_to : ''))
/** What's typed in the range fields; applied to the URL on submit. */
const yearInput = ref({ from: '', to: '' })
const sort = computed(() => (typeof route.query.sort === 'string' ? route.query.sort : 'title'))
const page = computed(() => Math.max(1, Number(route.query.page) || 1))
const activeCount = computed(
  () => Object.values(selected.value).reduce((n, values) => n + values.length, 0) + (yearFrom.value || yearTo.value ? 1 : 0),
)
const yearLabel = computed(() => {
  if (yearFrom.value && yearTo.value) return yearFrom.value === yearTo.value ? yearFrom.value : `${yearFrom.value}–${yearTo.value}`
  if (yearFrom.value) return `From ${yearFrom.value}`
  return yearTo.value ? `Until ${yearTo.value}` : ''
})

const catalog = ref<CatalogResult | null>(null)
const contentResults = ref<ContentSearchResult[]>([])
const loading = ref(false)
const error = ref('')
const hasSearched = ref(false)
const filtersOpen = ref(false)
/** Facet groups showing every option instead of the first few. */
const expanded = ref(new Set<Facet>())
const SHOWN = 6

const pageCount = computed(() => (catalog.value ? Math.max(1, Math.ceil(catalog.value.total / catalog.value.pageSize)) : 1))

/**
 * A facet's options, plus any selected value the current results no longer contain (count 0), so it can
 * be unticked. Selected options come first, so collapsing a long list never hides one.
 */
function options(facet: Facet): FacetOption[] {
  const opts = catalog.value?.facets[facet] ?? []
  const picked = selected.value[facet]
  const missing = picked.filter((v) => !opts.some((o) => o.value === v)).map((value) => ({ value, count: 0 }))
  const all = [...opts, ...missing]
  return [...all.filter((o) => picked.includes(o.value)), ...all.filter((o) => !picked.includes(o.value))]
}

function shown(facet: Facet): FacetOption[] {
  const all = options(facet)
  return expanded.value.has(facet) ? all : all.slice(0, Math.max(SHOWN, selected.value[facet].length))
}

function toggleExpanded(facet: Facet) {
  const next = new Set(expanded.value)
  if (!next.delete(facet)) next.add(facet)
  expanded.value = next
}

function facetLabel(facet: Facet, value: string): string {
  return facet === 'format' ? value.toUpperCase() : value
}

function go(changes: LocationQueryRaw, push = true) {
  const query = { ...route.query, ...changes }
  // Any change to what's searched starts again from page 1.
  if (!('page' in changes)) delete query.page
  ;(push ? router.push : router.replace)({ path: '/search', query })
}

function toggle(facet: Facet, value: string) {
  const current = selected.value[facet]
  go({ [facet]: current.includes(value) ? current.filter((v) => v !== value) : [...current, value] })
}

function applyYears() {
  const clean = (y: string) => (/^\d{1,4}$/.test(y.trim()) ? y.trim() : undefined)
  let [from, to] = [clean(yearInput.value.from), clean(yearInput.value.to)]
  if (from && to && Number(from) > Number(to)) [from, to] = [to, from]
  go({ year_from: from, year_to: to })
}

function clearYears() {
  go({ year_from: undefined, year_to: undefined })
}

function clearFilters() {
  go({ category: [], format: [], author: [], year_from: undefined, year_to: undefined })
}

function submit() {
  go({ q: searchTerm.value.trim() || undefined })
}

function setMode(next: Mode) {
  go({ mode: next === 'content' ? 'content' : undefined }, false)
}

async function runCatalog() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await booksApi.catalog({
      q: searchTerm.value.trim() || undefined,
      ...selected.value,
      year_from: yearFrom.value || undefined,
      year_to: yearTo.value || undefined,
      sort: sort.value,
      page: page.value,
    })
    if (isApiSuccess(data)) catalog.value = data.data
    else error.value = 'Search is unavailable right now. Try again in a moment.'
  } catch {
    error.value = 'Search is unavailable right now. Try again in a moment.'
  } finally {
    loading.value = false
  }
}

async function runContent() {
  contentResults.value = []
  const trimmed = searchTerm.value.trim()
  hasSearched.value = trimmed !== ''
  error.value = ''
  if (!trimmed) return
  loading.value = true
  try {
    const { data } = await booksApi.contentSearch(trimmed)
    contentResults.value = isApiSuccess(data) ? data.data : []
    if (!contentResults.value.length) error.value = `No pages found for "${trimmed}".`
  } catch {
    error.value = 'An error occurred while searching.'
  } finally {
    loading.value = false
  }
}

watch(
  () => route.fullPath,
  () => {
    if (route.path !== '/search') return
    searchTerm.value = typeof route.query.q === 'string' ? route.query.q : ''
    yearInput.value = { from: yearFrom.value, to: yearTo.value }
    if (mode.value === 'content') runContent()
    else runCatalog()
  },
  { immediate: true },
)
</script>

<template>
  <div class="container search-page" data-testid="search-page">
    <h1>Search</h1>
    <div class="search-modes" role="group" aria-label="What to search">
      <button
        type="button"
        class="mode-btn"
        :aria-pressed="mode === 'title'"
        data-testid="search-mode-title"
        @click="setMode('title')"
      >
        Catalog
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
        :placeholder="mode === 'content' ? 'Search for a topic, idea or phrase…' : 'Title or author'"
        :maxlength="mode === 'content' ? 300 : 200"
        aria-label="Search books"
        data-testid="search-input"
      />
      <button type="submit" class="btn btn-primary" data-testid="search-submit">Search</button>
    </form>

    <template v-if="mode === 'title'">
      <div class="catalog">
        <button
          type="button"
          class="btn btn-outline filters-toggle"
          :aria-expanded="filtersOpen"
          aria-controls="catalog-filters"
          data-testid="filters-toggle"
          @click="filtersOpen = !filtersOpen"
        >
          Filters<span v-if="activeCount"> ({{ activeCount }})</span>
        </button>

        <aside id="catalog-filters" class="filters" :class="{ 'filters--open': filtersOpen }" aria-label="Filters">
          <template v-for="group in FACET_GROUPS" :key="group.key">
          <fieldset class="facet" :data-testid="`facet-${group.key}`">
            <legend>{{ group.label }}</legend>
            <p v-if="!options(group.key).length" class="facet-empty">None in these results</p>
            <label v-for="opt in shown(group.key)" :key="opt.value" class="facet-option">
              <input
                type="checkbox"
                :checked="selected[group.key].includes(opt.value)"
                :data-testid="`facet-${group.key}-${opt.value}`"
                @change="toggle(group.key, opt.value)"
              />
              <span class="facet-name">{{ facetLabel(group.key, opt.value) }}</span>
              <span class="facet-count">{{ opt.count }}</span>
            </label>
            <button
              v-if="options(group.key).length > SHOWN"
              type="button"
              class="facet-more"
              :aria-expanded="expanded.has(group.key)"
              @click="toggleExpanded(group.key)"
            >
              {{ expanded.has(group.key) ? 'Show fewer' : `Show all ${options(group.key).length}` }}
            </button>
          </fieldset>

          <form v-if="group.key === 'category'" class="facet facet-years" data-testid="facet-year" @submit.prevent="applyYears">
            <fieldset>
              <legend>Published</legend>
              <div class="year-fields">
                <label>
                  From
                  <input
                    v-model="yearInput.from"
                    type="text"
                    inputmode="numeric"
                    pattern="[0-9]{1,4}"
                    maxlength="4"
                    :placeholder="catalog?.years ? String(catalog.years.min) : ''"
                    data-testid="year-from"
                  />
                </label>
                <label>
                  To
                  <input
                    v-model="yearInput.to"
                    type="text"
                    inputmode="numeric"
                    pattern="[0-9]{1,4}"
                    maxlength="4"
                    :placeholder="catalog?.years ? String(catalog.years.max) : ''"
                    data-testid="year-to"
                  />
                </label>
              </div>
              <button type="submit" class="btn btn-outline year-apply" data-testid="year-apply">Apply</button>
            </fieldset>
          </form>
          </template>
        </aside>

        <section class="results" :aria-busy="loading" aria-live="polite">
          <div class="results-bar">
            <p class="results-total" data-testid="catalog-total">
              <template v-if="catalog">
                {{ catalog.total }} {{ catalog.total === 1 ? 'book' : 'books' }}<template v-if="searchTerm.trim()">
                  for “{{ searchTerm.trim() }}”</template
                >
              </template>
            </p>
            <label class="sort">
              Sort by
              <select :value="sort" data-testid="catalog-sort" @change="go({ sort: ($event.target as HTMLSelectElement).value })">
                <option v-for="s in SORTS" :key="s.value" :value="s.value">{{ s.label }}</option>
              </select>
            </label>
          </div>

          <ul v-if="activeCount" class="active-filters" aria-label="Active filters">
            <template v-for="(values, facet) in selected" :key="facet">
              <li v-for="value in values" :key="`${facet}-${value}`">
                <button
                  type="button"
                  class="active-filter"
                  :aria-label="`Remove filter ${facetLabel(facet, value)}`"
                  @click="toggle(facet, value)"
                >
                  {{ facetLabel(facet, value) }} <span aria-hidden="true">×</span>
                </button>
              </li>
            </template>
            <li v-if="yearLabel">
              <button type="button" class="active-filter" :aria-label="`Remove filter published ${yearLabel}`" @click="clearYears">
                {{ yearLabel }} <span aria-hidden="true">×</span>
              </button>
            </li>
            <li><button type="button" class="clear-filters" data-testid="clear-filters" @click="clearFilters">Clear filters</button></li>
          </ul>

          <div v-if="loading && !catalog" class="spinner" role="status" aria-label="Searching" data-testid="search-loading"></div>
          <p v-else-if="error" class="text-muted" data-testid="search-message">{{ error }}</p>
          <div v-else-if="catalog && !catalog.books.length" class="empty" data-testid="search-message">
            <p>{{ searchTerm.trim() ? `No books found for "${searchTerm.trim()}".` : 'No books match these filters.' }}</p>
            <p class="text-muted">
              <template v-if="activeCount">Remove a filter, or </template>try fewer or different words — titles and authors are matched
              as written, in Greek or English.
            </p>
          </div>
          <template v-else-if="catalog">
            <div class="grid-books" :class="{ 'is-stale': loading }" data-testid="search-results">
              <BookCard v-for="book in catalog.books" :key="idToString(book._id)" :book="book" />
            </div>
            <nav v-if="pageCount > 1" class="pager" aria-label="Pages">
              <button type="button" class="btn btn-outline" :disabled="page <= 1" @click="go({ page: page - 1 })">Previous</button>
              <span>Page {{ page }} of {{ pageCount }}</span>
              <button type="button" class="btn btn-outline" :disabled="page >= pageCount" @click="go({ page: page + 1 })">Next</button>
            </nav>
          </template>
        </section>
      </div>
    </template>

    <template v-else>
      <div v-if="loading" class="spinner" role="status" aria-label="Searching" data-testid="search-loading"></div>
      <p v-else-if="error" class="text-muted" data-testid="search-message">{{ error }}</p>
      <ol v-else-if="hasSearched" class="content-results" data-testid="content-results">
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
      <p v-else class="text-muted" data-testid="search-message">
        Describe what you are looking for, in Greek or English, to find the pages that cover it.
      </p>
    </template>
  </div>
</template>

<style scoped src="@/css/views/SearchResults.css"></style>
