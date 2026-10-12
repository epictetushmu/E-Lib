<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'

defineOptions({ inheritAttrs: false })

const props = withDefaults(
  defineProps<{
    modelValue: string[]
    options?: string[]
    placeholder?: string
    id?: string
  }>(),
  {
    options: () => [],
    placeholder: 'Add a category',
    id: undefined,
  },
)

const emit = defineEmits<{ 'update:modelValue': [string[]] }>()

const root = ref<HTMLElement | null>(null)
const inputEl = ref<HTMLInputElement | null>(null)
const menuEl = ref<HTMLElement | null>(null)
const query = ref('')
const menuOpen = ref(false)

const trimmedQuery = computed(() => query.value.trim())

function sameName(a: string, b: string): boolean {
  // `modelValue` comes straight from a book's own (freely admin-edited) categories
  // field, unsanitized — coerce defensively so a stray non-string value there can't
  // crash the picker the way an unfiltered `options` entry just did.
  return String(a).toLowerCase() === String(b).toLowerCase()
}

function isSelected(name: string): boolean {
  return props.modelValue.some((selected) => sameName(selected, name))
}

// `options` crosses an API boundary that TypeScript can't guarantee at runtime — a
// stray non-string value here (e.g. a malformed category on some book) would crash
// every comparison below, so filter it out once rather than guard each call site.
const safeOptions = computed(() => props.options.filter((option): option is string => typeof option === 'string'))

const availableOptions = computed(() => {
  const q = trimmedQuery.value.toLowerCase()
  return safeOptions.value.filter((option) => !isSelected(option) && (!q || option.toLowerCase().includes(q)))
})

const canCreate = computed(() => {
  const q = trimmedQuery.value
  return q !== '' && !isSelected(q) && !safeOptions.value.some((option) => sameName(option, q))
})

const rowCount = computed(() => availableOptions.value.length + (canCreate.value ? 1 : 0))

function handleDocumentMousedown(event: MouseEvent) {
  if (root.value && !root.value.contains(event.target as Node)) {
    menuOpen.value = false
  }
}
onMounted(() => document.addEventListener('mousedown', handleDocumentMousedown))
onBeforeUnmount(() => document.removeEventListener('mousedown', handleDocumentMousedown))

function openMenu() {
  menuOpen.value = true
}

// Picking a tag can wrap the field onto a new line, pushing the dropdown further down —
// keep it in view instead of leaving it rendered below the visible/scrollable area.
function scrollMenuIntoView() {
  nextTick(() => menuEl.value?.scrollIntoView?.({ block: 'nearest' }))
}

// Clicking an option never blurs the input (its mousedown is prevented), and calling
// .focus() on an element that's already focused fires no native `focus` event — so every
// interaction that should reveal suggestions opens the menu explicitly, rather than
// depending on `@focus` alone to have fired.
function focusAndOpen() {
  inputEl.value?.focus()
  openMenu()
  scrollMenuIntoView()
}

function addCategory(name: string) {
  const value = name.trim()
  if (!value || isSelected(value)) return
  emit('update:modelValue', [...props.modelValue, value])
}

function removeCategory(name: string) {
  emit(
    'update:modelValue',
    props.modelValue.filter((selected) => selected !== name),
  )
}

function commitSelection(name: string) {
  addCategory(name)
  query.value = ''
  focusAndOpen()
}

// Enter either picks the option the typed text names exactly, or — if nothing matches —
// creates it as a new category.
function commitQuery() {
  const exactMatch = safeOptions.value.find((option) => sameName(option, trimmedQuery.value))
  if (exactMatch) {
    commitSelection(exactMatch)
  } else if (canCreate.value) {
    commitSelection(trimmedQuery.value)
  }
}

function handleBackspace() {
  if (query.value === '' && props.modelValue.length) {
    removeCategory(props.modelValue[props.modelValue.length - 1])
  }
}
</script>

<template>
  <div ref="root" class="category-picker" :class="{ open: menuOpen }">
    <ul class="cp-field" @click="focusAndOpen">
      <li v-for="category in modelValue" :key="category" class="cp-chip">
        <span>{{ category }}</span>
        <button type="button" class="cp-chip-remove" :aria-label="`Remove ${category}`" @click.stop="removeCategory(category)">
          &times;
        </button>
      </li>
      <li class="cp-input-cell">
        <input
          :id="id"
          ref="inputEl"
          v-model="query"
          type="text"
          class="cp-input"
          role="combobox"
          aria-autocomplete="list"
          :aria-expanded="menuOpen"
          :placeholder="modelValue.length ? '' : placeholder"
          @focus="openMenu"
          @keydown.enter.prevent="commitQuery"
          @keydown.backspace="handleBackspace"
          @keydown.escape="menuOpen = false"
        />
      </li>
    </ul>

    <ul v-if="menuOpen && rowCount > 0" ref="menuEl" class="cp-menu" role="listbox">
      <li
        v-for="option in availableOptions"
        :key="option"
        role="option"
        class="cp-option"
        @mousedown.prevent="commitSelection(option)"
      >
        {{ option }}
      </li>
      <li v-if="canCreate" role="option" class="cp-option cp-option-create" @mousedown.prevent="commitQuery">
        Create “{{ trimmedQuery }}”
      </li>
    </ul>
  </div>
</template>

<style scoped src="@/css/components/CategoryPicker.css"></style>
