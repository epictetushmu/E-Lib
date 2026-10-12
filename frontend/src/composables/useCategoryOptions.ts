import { onMounted, ref } from 'vue'
import { getCategories } from '@/api/books'
import type { MetadataLookup } from '@/api/admin'
import { isApiSuccess } from '@/types/api'

/** Distinct categories already in use, for the category picker's suggestions. */
export function useCategoryOptions() {
  const categories = ref<string[]>([])

  onMounted(async () => {
    try {
      const response = await getCategories()
      if (isApiSuccess(response.data)) categories.value = response.data.data
    } catch {
      // A failed lookup just means fewer suggestions — typing a new category still works.
    }
  })

  return categories
}

/**
 * Up to `max` of a lookup's subjects as categories, spelled like an existing category when one matches
 * (case-insensitively) and those first, so the catalog's subject filter doesn't fill with near-duplicates.
 */
export function pickCategories(subjects: string[], existing: string[], max = 3): string[] {
  const known = new Map(existing.map((c) => [c.toLowerCase(), c]))
  const matched = subjects.flatMap((s) => known.get(s.toLowerCase()) ?? [])
  const fresh = subjects.filter((s) => !known.has(s.toLowerCase()))
  return [...new Set([...matched, ...fresh])].slice(0, max)
}

/** Fill only the empty fields of a book form from a metadata lookup; categories only when it has none. */
export function fillEmpty(
  form: { author: string; year: string; isbn: string; description: string; categories: string[] },
  found: MetadataLookup,
  existing: string[],
) {
  form.author ||= found.author
  form.year ||= found.year
  form.isbn ||= found.isbn
  form.description ||= found.description
  if (!form.categories.length) form.categories = pickCategories(found.categories, existing)
}
