<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { MathfieldElement } from 'mathlive'
import katex from 'katex'
import 'katex/dist/katex.min.css'

/**
 * Visual equation editor: type maths the way it looks (1/2 becomes a fraction, sqrt a root),
 * or click a symbol. Hands back LaTeX, which is what notes store and KaTeX renders.
 */
const props = defineProps<{ latex?: string; display?: boolean; editing?: boolean }>()
const emit = defineEmits<{ insert: [latex: string, display: boolean]; close: [] }>()

// MathLive draws with the KaTeX fonts already loaded above; no sounds.
MathfieldElement.fontsDirectory = null
MathfieldElement.soundsDirectory = null

const dialog = ref<HTMLDialogElement | null>(null)
const fieldHost = ref<HTMLElement | null>(null)
const display = ref(props.display ?? true)
const empty = ref(!props.latex)
let field: MathfieldElement | null = null

/** `#@` takes the current selection, `#?` is a box to fill in, `#0` places the cursor. */
const palette = [
  {
    label: 'Structures',
    items: [
      { tex: '\\frac{a}{b}', insert: '\\frac{#@}{#?}', name: 'Fraction' },
      { tex: '\\sqrt{x}', insert: '\\sqrt{#0}', name: 'Square root' },
      { tex: '\\sqrt[n]{x}', insert: '\\sqrt[#?]{#0}', name: 'Nth root' },
      { tex: 'x^{n}', insert: '#@^{#?}', name: 'Power' },
      { tex: 'x_{i}', insert: '#@_{#?}', name: 'Subscript' },
      { tex: '\\left(x\\right)', insert: '\\left(#0\\right)', name: 'Brackets' },
      { tex: '\\sum_{i}^{n}', insert: '\\sum_{#?}^{#?}', name: 'Sum' },
      { tex: '\\int_{a}^{b}', insert: '\\int_{#?}^{#?}', name: 'Integral' },
      { tex: '\\lim_{x\\to a}', insert: '\\lim_{#?\\to#?}', name: 'Limit' },
      { tex: '\\binom{n}{k}', insert: '\\binom{#?}{#?}', name: 'Binomial' },
      { tex: '\\begin{pmatrix}a&b\\\\c&d\\end{pmatrix}', insert: '\\begin{pmatrix}#?&#?\\\\#?&#?\\end{pmatrix}', name: 'Matrix' },
      { tex: '\\vec{v}', insert: '\\vec{#0}', name: 'Vector' },
    ],
  },
  {
    label: 'Relations and operators',
    items: [
      { tex: '\\le', insert: '\\le', name: 'Less or equal' },
      { tex: '\\ge', insert: '\\ge', name: 'Greater or equal' },
      { tex: '\\ne', insert: '\\ne', name: 'Not equal' },
      { tex: '\\approx', insert: '\\approx', name: 'Approximately' },
      { tex: '\\pm', insert: '\\pm', name: 'Plus or minus' },
      { tex: '\\times', insert: '\\times', name: 'Times' },
      { tex: '\\cdot', insert: '\\cdot', name: 'Dot' },
      { tex: '\\infty', insert: '\\infty', name: 'Infinity' },
      { tex: '\\to', insert: '\\to', name: 'Arrow' },
      { tex: '\\Rightarrow', insert: '\\Rightarrow', name: 'Implies' },
      { tex: '\\in', insert: '\\in', name: 'Element of' },
      { tex: '\\forall', insert: '\\forall', name: 'For all' },
      { tex: '\\exists', insert: '\\exists', name: 'Exists' },
      { tex: '\\partial', insert: '\\partial', name: 'Partial' },
    ],
  },
  {
    label: 'Greek',
    items: ['alpha', 'beta', 'gamma', 'delta', 'epsilon', 'theta', 'lambda', 'mu', 'pi', 'sigma', 'phi', 'omega', 'Delta', 'Sigma', 'Omega'].map(
      (g) => ({ tex: `\\${g}`, insert: `\\${g}`, name: g }),
    ),
  },
]

function glyph(tex: string): string {
  return katex.renderToString(tex, { throwOnError: false })
}

function add(insert: string) {
  field?.executeCommand(['insert', insert, { selectionMode: 'placeholder' }])
  field?.focus()
}

/**
 * Puts the caret in the field. MathLive's focus() is a no-op while it believes it still has focus,
 * and it can miss losing focus (seen inside the highlight popover), so if the browser's focus
 * really is elsewhere, focus its internal input directly.
 */
function focusField() {
  if (!field) return
  field.focus()
  if (document.activeElement !== field) {
    field.shadowRoot?.querySelector<HTMLElement>('.ML__keyboard-sink')?.focus()
  }
}

// Choosing a placement shouldn't strand the caret on the radio button.
watch(display, focusField)

function submit() {
  const latex = field?.getValue('latex').trim() ?? ''
  if (latex) emit('insert', latex, display.value)
}

onMounted(() => {
  // Attach first: MathLive throws "Mathfield not mounted" if configured while detached.
  field = new MathfieldElement()
  fieldHost.value?.appendChild(field)
  field.value = props.latex ?? ''
  field.smartFence = true
  field.menuItems = []
  field.addEventListener('input', () => (empty.value = !field?.getValue('latex').trim()))
  field.addEventListener('keydown', (event) => {
    if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) {
      event.preventDefault()
      submit()
    }
  })

  // The on-screen symbol keyboard must live inside the modal, or it would sit behind it.
  if (dialog.value) window.mathVirtualKeyboard.container = dialog.value
  dialog.value?.showModal()
  field.focus()
})

onBeforeUnmount(() => {
  window.mathVirtualKeyboard.hide()
  window.mathVirtualKeyboard.container = document.body
})
</script>

<template>
  <!-- Esc closes this dialog only (MathLive cancels the native dialog cancel, so handle it here), never the popover underneath. -->
  <dialog
    ref="dialog"
    class="equation-dialog dialog-shell"
    aria-labelledby="equation-dialog-title"
    @cancel.prevent="emit('close')"
    @keydown.esc.stop="emit('close')"
  >
    <div class="equation-head dialog-head">
      <h2 id="equation-dialog-title">{{ editing ? 'Edit equation' : 'Equation' }}</h2>
      <button type="button" class="equation-close dialog-close" aria-label="Close" @click="emit('close')">&times;</button>
    </div>

    <!-- A click anywhere on the writing area, incl. its empty padding, lands in the field. -->
    <div ref="fieldHost" class="equation-field" @pointerup="focusField"></div>
    <p class="equation-tip">
      Type it as you'd write it: <kbd>1/2</kbd> makes a fraction, <kbd>^</kbd> a power, <kbd>sqrt</kbd> a root, <kbd>alpha</kbd> α.
      Tab moves to the next box.
    </p>

    <div class="equation-palette">
      <section v-for="group in palette" :key="group.label" :aria-label="group.label">
        <h3>{{ group.label }}</h3>
        <div class="equation-keys">
          <!-- eslint-disable vue/no-v-html -- KaTeX output for our own fixed symbol list -->
          <button
            v-for="item in group.items"
            :key="item.name"
            type="button"
            class="equation-key"
            :title="item.name"
            :aria-label="item.name"
            @mousedown.prevent
            @click="add(item.insert)"
            v-html="glyph(item.tex)"
          ></button>
          <!-- eslint-enable vue/no-v-html -->
        </div>
      </section>
    </div>

    <div class="equation-foot">
      <fieldset class="equation-mode">
        <legend class="visually-hidden">Placement</legend>
        <label @mousedown.prevent><input v-model="display" type="radio" :value="false" /> In the sentence</label>
        <label @mousedown.prevent><input v-model="display" type="radio" :value="true" /> On its own line</label>
      </fieldset>
      <div class="equation-actions">
        <button type="button" class="link-btn" @click="emit('close')">Cancel</button>
        <button type="button" class="btn btn-primary" :disabled="empty" @click="submit">
          {{ editing ? 'Update equation' : 'Insert equation' }}
        </button>
      </div>
    </div>
  </dialog>
</template>

<style scoped src="@/css/components/EquationDialog.css"></style>
