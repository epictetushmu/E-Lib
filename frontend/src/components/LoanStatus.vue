<script setup lang="ts">
import { computed } from 'vue'
import { LOAN_STATUS_LABELS, type LoanRecord } from '@/types/book'

const props = defineProps<{ status: LoanRecord['status'] }>()

/** held = waiting for the reader, out = in their hands, late = overdue, past = finished. */
const tone = computed(() => {
  switch (props.status) {
    case 'reserved':
      return 'held'
    case 'out':
    case 'active':
      return 'out'
    case 'overdue':
      return 'late'
    default:
      return 'past'
  }
})
</script>

<template>
  <span class="loan-status" :class="`tone-${tone}`">{{ LOAN_STATUS_LABELS[status] }}</span>
</template>

<style scoped>
.loan-status {
  display: inline-block;
  padding: 0.15rem 0.55rem;
  border: 1px solid transparent;
  border-radius: 999px;
  font-size: 0.8rem;
  font-weight: 600;
  white-space: nowrap;
}

.tone-held {
  background: #f3e7c9;
  border-color: #e1cc95;
  color: var(--color-accent-text);
}

.tone-out {
  background: #e6ead9;
  border-color: #c7cfae;
  color: var(--color-success);
}

.tone-late {
  background: var(--color-danger);
  color: #fff;
}

.tone-past {
  border-color: var(--color-border);
  color: var(--color-text-muted);
}
</style>
