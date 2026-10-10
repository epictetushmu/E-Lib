<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { Handle, useVueFlow, type NodeProps } from '@vue-flow/core'
import { SIDE_POSITION, flowOutline, isFlowKind, nodeHtml, nodeSize, sidePanelKind, type DiagramNode, type Side } from '@/utils/diagram'

/** Node data in the editor: the diagram node minus its id/position, plus an inline-rename flag. */
export type DiagramNodeData = Omit<DiagramNode, 'id' | 'x' | 'y'> & { editing?: boolean }

const props = defineProps<NodeProps<DiagramNodeData>>()
const { updateNodeData } = useVueFlow()

const size = computed(() => nodeSize(props.data))
const html = computed(() => nodeHtml({ ...props.data, id: props.id, x: 0, y: 0 }, true))
const inlineEditable = computed(() => !sidePanelKind(props.data.kind))
const draft = ref(props.data.label)
const field = ref<HTMLTextAreaElement | null>(null)
const sides: Side[] = ['t', 'r', 'b', 'l']

watch(
  () => props.data.editing,
  async (editing) => {
    if (!editing) return
    draft.value = props.data.label
    await nextTick()
    // Vue Flow keeps a new node hidden until it has measured it, and hidden fields can't take focus:
    // try on the next few frames until it's shown.
    for (let frame = 0; frame < 10 && field.value && document.activeElement !== field.value; frame++) {
      await new Promise(requestAnimationFrame)
      field.value?.focus()
    }
    field.value?.select()
  },
  { immediate: true },
)

function startEditing(event: MouseEvent) {
  if (!inlineEditable.value) return // bubbles to the dialog, which focuses the side panel instead
  event.stopPropagation()
  updateNodeData(props.id, { editing: true })
}

function commit() {
  updateNodeData(props.id, { label: draft.value.trim(), editing: false })
}

function cancel() {
  updateNodeData(props.id, { editing: false })
}
</script>

<template>
  <div
    class="dg-editor-node dg-shape"
    :class="[`dg-shape-${data.kind}`, { 'is-selected': selected }]"
    :style="{ width: `${size.w}px`, height: `${size.h}px` }"
    @dblclick="startEditing"
  >
    <!-- eslint-disable-next-line vue/no-v-html -- fixed outline markup -->
    <svg v-if="isFlowKind(data.kind)" class="dg-outline" :width="size.w" :height="size.h" aria-hidden="true" v-html="flowOutline(data.kind)"></svg>
    <textarea
      v-if="data.editing && inlineEditable"
      ref="field"
      v-model="draft"
      class="dg-inline-input nodrag nopan"
      :class="`dg-inline-${data.kind}`"
      aria-label="Shape text"
      @keydown.enter.exact="isFlowKind(data.kind) && ($event.preventDefault(), commit())"
      @keydown.esc.stop.prevent="cancel"
      @blur="commit"
    ></textarea>
    <!-- eslint-disable-next-line vue/no-v-html -- nodeHtml() escapes all user text -->
    <div v-else class="dg-node" :class="`dg-${data.kind}`" v-html="html"></div>
    <Handle v-for="side in sides" :id="side" :key="side" type="source" :position="SIDE_POSITION[side]" class="dg-handle" />
  </div>
</template>
