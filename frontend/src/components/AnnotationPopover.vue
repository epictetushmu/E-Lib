<script setup lang="ts">
import { nextTick, onMounted, ref } from 'vue'
import { HIGHLIGHT_COLORS, type HighlightColor } from '@/types/annotation'

/**
 * new: a fresh selection — pick a colour to highlight, or add a note with it.
 * edit: an existing highlight — recolour, edit its note, or delete it.
 * guest / multipage: explain why a highlight can't be made here.
 */
const props = defineProps<{
  mode: 'new' | 'edit' | 'guest' | 'multipage'
  left: number
  top: number
  placeAbove: boolean
  color?: HighlightColor | null
  note?: string
  loginPath?: string
  busy?: boolean
}>()

const emit = defineEmits<{
  pick: [color: HighlightColor]
  save: [note: string]
  remove: []
  close: []
}>()

const root = ref<HTMLElement | null>(null)
const noteOpen = ref(props.mode === 'edit' && !!props.note)
const noteText = ref(props.note ?? '')
const noteField = ref<HTMLTextAreaElement | null>(null)

async function openNote() {
  noteOpen.value = true
  await nextTick()
  noteField.value?.focus()
}

onMounted(() => {
  if (props.mode === 'edit') root.value?.querySelector<HTMLElement>('[aria-checked="true"]')?.focus()
})
</script>

<template>
  <div
    ref="root"
    class="annotation-popover"
    :class="{ above: placeAbove }"
    :style="{ left: `${left}px`, top: `${top}px` }"
    role="dialog"
    :aria-label="mode === 'edit' ? 'Edit highlight' : 'Highlight selection'"
    @keydown.esc.stop="emit('close')"
  >
    <template v-if="mode === 'guest'">
      <p class="popover-message">Log in to highlight passages and keep private notes.</p>
      <RouterLink :to="loginPath ?? '/login'" class="btn btn-primary popover-login">Log in</RouterLink>
    </template>

    <p v-else-if="mode === 'multipage'" class="popover-message">A highlight stays on one page. Select a passage that ends on this page.</p>

    <template v-else>
      <div class="popover-row">
        <div class="marker-tray" role="radiogroup" aria-label="Highlight colour">
          <button
            v-for="c in HIGHLIGHT_COLORS"
            :key="c.id"
            type="button"
            role="radio"
            :class="['marker', `hl-${c.id}`]"
            :aria-checked="mode === 'edit' && color === c.id"
            :aria-label="mode === 'edit' ? `Change to ${c.label}` : `Highlight in ${c.label}`"
            :title="c.label"
            :disabled="busy"
            @click="emit('pick', c.id)"
          ></button>
        </div>
        <button v-if="!noteOpen" type="button" class="popover-link" :disabled="busy" @click="openNote">
          {{ mode === 'edit' ? 'Add note' : 'Highlight with note' }}
        </button>
      </div>

      <form v-if="noteOpen" class="popover-note" @submit.prevent="emit('save', noteText)">
        <label class="visually-hidden" for="popover-note-text">Note</label>
        <textarea
          id="popover-note-text"
          ref="noteField"
          v-model="noteText"
          rows="3"
          placeholder="Why this passage matters"
          @keydown.enter.ctrl.prevent="emit('save', noteText)"
          @keydown.enter.meta.prevent="emit('save', noteText)"
        ></textarea>
        <button type="submit" class="btn btn-primary" :disabled="busy || (mode === 'new' && !noteText.trim())">
          {{ mode === 'edit' ? 'Save note' : 'Save highlight' }}
        </button>
      </form>

      <button v-if="mode === 'edit'" type="button" class="popover-link popover-delete" :disabled="busy" @click="emit('remove')">
        Delete highlight
      </button>
    </template>
  </div>
</template>

<style scoped src="@/css/components/AnnotationPopover.css"></style>
