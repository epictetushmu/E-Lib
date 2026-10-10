<script setup lang="ts">
import { computed, ref } from 'vue'
import { renderNote } from '@/utils/noteMarkdown'

/** A rendered note: Markdown, maths, code and diagrams. */
const props = defineProps<{ text: string }>()

// Safe to bind as HTML: the renderer escapes any raw HTML in the note (markdown-it html: false).
const html = computed(() => renderNote(props.text))

const dialog = ref<HTMLDialogElement | null>(null)
const dialogSvg = ref('')

/** Opens a drawn diagram (click or Enter/Space on it) in a full-size dialog. */
function openDiagram(event: MouseEvent | KeyboardEvent) {
  const block = (event.target as HTMLElement).closest<HTMLElement>('.note-diagram[role="button"]')
  if (!block) return
  if (event instanceof KeyboardEvent) {
    if (event.key !== 'Enter' && event.key !== ' ') return
    event.preventDefault()
  }
  dialogSvg.value = block.innerHTML
  dialog.value?.showModal()
}
</script>

<template>
  <div class="note-content-wrap">
    <!-- eslint-disable-next-line vue/no-v-html -->
    <div class="note-content" @click="openDiagram" @keydown="openDiagram" v-html="html"></div>
    <dialog ref="dialog" class="diagram-dialog dialog-shell" aria-label="Diagram" @click.self="dialog?.close()">
      <button type="button" class="diagram-dialog-close dialog-close" aria-label="Close diagram" @click="dialog?.close()">&times;</button>
      <!-- The diagram's SVG from renderDiagramSvg() (user text escaped), already shown in the note. -->
      <!-- eslint-disable-next-line vue/no-v-html -->
      <div class="diagram-dialog-body" v-html="dialogSvg"></div>
    </dialog>
  </div>
</template>

<style scoped src="@/css/components/NoteContent.css"></style>
