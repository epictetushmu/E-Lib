<script setup lang="ts">
import { computed } from 'vue'

/** A physical book's call number set like the sticker on its spine: one line per part. */
const props = defineProps<{ callNumber: string }>()
const lines = computed(() => props.callNumber.trim().split(/\s+/))
</script>

<template>
  <span class="spine-label" :aria-label="`Shelf ${callNumber}`">
    <span v-for="(line, i) in lines" :key="i" aria-hidden="true">{{ line }}</span>
  </span>
</template>

<style scoped>
.spine-label {
  display: inline-flex;
  flex-direction: column;
  align-items: center;
  min-width: 4.25rem;
  padding: 0.55rem 0.6rem 0.5rem;
  border-top: 6px solid var(--color-accent);
  border-radius: 2px;
  background: #fffdf8;
  box-shadow: var(--shadow-sm), inset 0 0 0 1px var(--color-border);
  font-family: var(--font-sans);
  font-size: 0.9rem;
  font-weight: 600;
  line-height: 1.25;
  font-variant-numeric: tabular-nums;
  color: var(--color-text);
}
</style>
