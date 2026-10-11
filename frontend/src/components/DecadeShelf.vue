<script setup lang="ts">
import { computed } from 'vue'
import type { FacetOption } from '@/types/book'

/**
 * Publication decades drawn as book spines on a shelf: each spine's height is how many matching books
 * came out that decade. Spines are toggle buttons; empty decades between the first and last stay as
 * gaps so the shelf reads as a timeline.
 */
const props = defineProps<{ decades: FacetOption<number>[]; selected: number[] }>()
const emit = defineEmits<{ toggle: [decade: number] }>()

const INKS = ['saffron', 'olive', 'aegean', 'rose']

const spines = computed(() => {
  const counts = new Map(props.decades.map((d) => [d.value, d.count]))
  for (const d of props.selected) if (!counts.has(d)) counts.set(d, 0)
  if (!counts.size) return []
  const years = [...counts.keys()]
  const first = Math.min(...years)
  const last = Math.max(...years)
  const max = Math.max(1, ...counts.values())
  const out = []
  for (let decade = first; decade <= last; decade += 10) {
    const count = counts.get(decade) ?? 0
    out.push({ decade, count, height: count ? 18 + (count / max) * 82 : 0, ink: INKS[(decade / 10) % INKS.length] })
  }
  return out
})
</script>

<template>
  <figure v-if="spines.length" class="shelf" data-testid="decade-shelf">
    <figcaption class="shelf-caption">Published</figcaption>
    <div class="shelf-scroll">
    <div class="shelf-row" role="group" aria-label="Filter by decade published">
      <span class="shelf-plank meander meander--on-dark" aria-hidden="true"></span>
      <button
        v-for="spine in spines"
        :key="spine.decade"
        type="button"
        class="spine-slot"
        :disabled="!spine.count && !selected.includes(spine.decade)"
        :aria-pressed="selected.includes(spine.decade)"
        :aria-label="`${spine.decade}s, ${spine.count} ${spine.count === 1 ? 'book' : 'books'}`"
        :data-testid="`decade-${spine.decade}`"
        @click="emit('toggle', spine.decade)"
      >
        <span class="spine-count" aria-hidden="true">{{ spine.count || '' }}</span>
        <span class="spine-well"><span class="spine" :class="`spine--${spine.ink}`" :style="{ height: `${spine.height}%` }"></span></span>
        <span class="spine-label" aria-hidden="true">{{ spine.decade }}s</span>
      </button>
    </div>
    </div>
  </figure>
</template>

<style scoped src="@/css/components/DecadeShelf.css"></style>
