<script setup lang="ts">
import { defineAsyncComponent, nextTick, onMounted, ref } from 'vue'

// The renderer (Markdown, KaTeX, highlight.js) loads only when a preview is first shown;
// the visual equation editor (MathLive) only when someone first adds an equation.
const NoteContent = defineAsyncComponent(() => import('@/components/NoteContent.vue'))
const EquationDialog = defineAsyncComponent(() => import('@/components/EquationDialog.vue'))

/** Note text box with shortcuts for code, equations and diagrams, and a rendered preview. */
const model = defineModel<string>({ required: true })
const props = defineProps<{ id: string; placeholder?: string; rows?: number; autofocus?: boolean }>()
const emit = defineEmits<{ submit: [] }>()

const field = ref<HTMLTextAreaElement | null>(null)
const previewing = ref(false)

/** Each snippet's `select` part is pre-selected after insertion, so typing replaces the example. */
const snippets = {
  code: { label: 'Code', before: '```python\n', select: 'print("hello")', after: '\n```' },
  diagram: { label: 'Diagram', before: '```mermaid\nflowchart LR\n', select: '  A[Input] --> B[Process] --> C[Output]', after: '\n```' },
} as const

/* Equations go through the visual editor rather than a LaTeX snippet. */

interface MathSpan {
  start: number
  end: number
  latex: string
  display: boolean
}

const equation = ref<{ target: MathSpan | null } | null>(null)

/** The $…$ or $$…$$ the cursor is in, so the Equation button edits it instead of adding another. */
function mathAt(text: string, pos: number): MathSpan | null {
  for (const m of text.matchAll(/\$\$([\s\S]+?)\$\$|\$([^$\n]+?)\$/g)) {
    const start = m.index ?? 0
    const end = start + m[0].length
    if (pos >= start && pos <= end) return { start, end, latex: (m[1] ?? m[2]).trim(), display: m[1] !== undefined }
  }
  return null
}

function openEquation() {
  previewing.value = false
  const el = field.value
  equation.value = { target: el ? mathAt(model.value, el.selectionStart) : null }
}

async function insertEquation(latex: string, display: boolean) {
  const el = field.value
  const target = equation.value?.target
  equation.value = null
  await nextTick()
  if (!el) return

  const text = model.value
  const start = target?.start ?? el.selectionStart
  const end = target?.end ?? el.selectionEnd
  let piece = display ? `$$\n${latex}\n$$` : `$${latex}$`
  // A display equation sits on its own lines.
  if (display && start > 0 && text[start - 1] !== '\n') piece = '\n' + piece
  if (display && end < text.length && text[end] !== '\n') piece += '\n'
  model.value = text.slice(0, start) + piece + text.slice(end)
  await nextTick()
  el.focus()
  el.setSelectionRange(start + piece.length, start + piece.length)
}

async function insert(kind: keyof typeof snippets) {
  previewing.value = false
  await nextTick()
  const el = field.value
  if (!el) return
  const { before, select, after } = snippets[kind]
  const start = el.selectionStart
  const end = el.selectionEnd
  const text = model.value
  // Start the block on its own line.
  const lead = start > 0 && text[start - 1] !== '\n' ? '\n' : ''
  const chosen = text.slice(start, end) || select
  model.value = text.slice(0, start) + lead + before + chosen + after + text.slice(end)
  await nextTick()
  const from = start + lead.length + before.length
  el.focus()
  el.setSelectionRange(from, from + chosen.length)
}

onMounted(() => {
  if (props.autofocus) field.value?.focus()
})
</script>

<template>
  <div class="note-editor">
    <div class="note-editor-bar">
      <div class="note-editor-insert" role="group" aria-label="Insert">
        <button type="button" @click="insert('code')">{{ snippets.code.label }}</button>
        <button type="button" @click="openEquation">Equation</button>
        <button type="button" @click="insert('diagram')">{{ snippets.diagram.label }}</button>
      </div>
      <button type="button" class="note-editor-toggle" :aria-pressed="previewing" @click="previewing = !previewing">
        {{ previewing ? 'Edit' : 'Preview' }}
      </button>
    </div>
    <textarea
      v-show="!previewing"
      :id="props.id"
      ref="field"
      v-model="model"
      :rows="rows ?? 4"
      :placeholder="placeholder"
      spellcheck="true"
      @keydown.enter.ctrl.prevent="emit('submit')"
      @keydown.enter.meta.prevent="emit('submit')"
    ></textarea>
    <div v-if="previewing" class="note-editor-preview">
      <NoteContent v-if="model.trim()" :text="model" />
      <p v-else class="note-editor-empty">Nothing to preview yet.</p>
    </div>
    <p class="note-editor-hint">Markdown works here. Ctrl+Enter saves.</p>

    <EquationDialog
      v-if="equation"
      :latex="equation.target?.latex"
      :display="equation.target?.display ?? true"
      :editing="!!equation.target"
      @insert="insertEquation"
      @close="equation = null"
    />
  </div>
</template>

<style scoped src="@/css/components/NoteEditor.css"></style>
