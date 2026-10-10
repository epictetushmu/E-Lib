<script setup lang="ts">
import { computed, markRaw, nextTick, onMounted, ref } from 'vue'
import { ConnectionMode, VueFlow, useVueFlow, type Connection, type Edge, type GraphEdge, type GraphNode, type Node } from '@vue-flow/core'
import '@vue-flow/core/dist/style.css'
import '@/css/diagram-shapes.css'
import DiagramNodeView, { type DiagramNodeData } from './DiagramNode.vue'
import DiagramEdgeView, { type DiagramEdgeData } from './DiagramEdge.vue'
import {
  EDGE_KINDS,
  EDITOR_MARKERS,
  NODE_KINDS,
  defaultEdgeKind,
  flowOutline,
  isFlowKind,
  markerDefs,
  nodeSize,
  type Column,
  type DiagramDoc,
  type DiagramNode,
  type EdgeKind,
  type NodeKind,
  type Side,
  type Stereotype,
} from '@/utils/diagram'

/**
 * Visual diagram editor (flowcharts, UML classes, database tables, text and notes).
 * Pick a shape to add it; in a flowchart it follows the selected shape, already connected.
 * Drag between the dots to connect; the side panel edits whatever is selected.
 */
const props = defineProps<{ doc?: DiagramDoc; editing?: boolean }>()
const emit = defineEmits<{ insert: [doc: DiagramDoc]; close: [] }>()

let counter = 0
const newId = (prefix: string) => `${prefix}${Date.now().toString(36)}${(counter++).toString(36)}`

function toNode(n: DiagramNode, editing = false): Node<DiagramNodeData> {
  const { id, x, y, ...data } = n
  return { id, type: 'diagram', position: { x, y }, data: { ...data, editing } }
}

function toEdge(e: { id: string; source: string; target: string; sourceHandle: Side; targetHandle: Side; kind: EdgeKind; label?: string; sourceLabel?: string; targetLabel?: string }): Edge<DiagramEdgeData> {
  return {
    id: e.id,
    type: 'diagram',
    source: e.source,
    target: e.target,
    sourceHandle: e.sourceHandle,
    targetHandle: e.targetHandle,
    label: e.label ?? '',
    data: { kind: e.kind, sourceLabel: e.sourceLabel, targetLabel: e.targetLabel },
  }
}

const FLOW_ID = 'note-diagram-editor'
// The store is seeded once here; the <VueFlow> element doesn't also bind nodes/edges (that double-syncs).
const {
  nodes,
  edges,
  addNodes,
  addEdges,
  onConnect,
  findNode,
  removeNodes,
  removeEdges,
  getSelectedNodes,
  getSelectedEdges,
  addSelectedNodes,
  removeSelectedElements,
  updateNodeData,
  updateEdgeData,
  fitView,
  screenToFlowCoordinate,
} = useVueFlow({
  id: FLOW_ID,
  nodes: (props.doc?.nodes ?? []).map((n) => toNode(n)),
  edges: (props.doc?.edges ?? []).map(toEdge),
})

const dialog = ref<HTMLDialogElement | null>(null)
const canvas = ref<HTMLElement | null>(null)
const nameField = ref<HTMLInputElement | null>(null)
const nodeTypes = { diagram: markRaw(DiagramNodeView) }
const edgeTypes = { diagram: markRaw(DiagramEdgeView) }
const markers = markerDefs(EDITOR_MARKERS)

/* ---------- Shape sets ---------- */

const SETS = {
  flowchart: { name: 'Flowchart', kinds: ['terminal', 'step', 'decision', 'io'] },
  uml: { name: 'UML', kinds: ['class'] },
  database: { name: 'Database', kinds: ['table'] },
} satisfies Record<string, { name: string; kinds: NodeKind[] }>
type SetId = keyof typeof SETS

function initialSet(): SetId {
  const kinds = new Set(props.doc?.nodes.map((n) => n.kind))
  if (kinds.has('table')) return 'database'
  if (kinds.has('class')) return 'uml'
  return 'flowchart'
}
const activeSet = ref<SetId>(initialSet())

const shapeLabel = (kind: NodeKind) => NODE_KINDS[kind].name

/* ---------- Adding shapes ---------- */

const asNode = (n: GraphNode) => n as GraphNode<DiagramNodeData>

/** A flowchart shape follows the selected flowchart shape, below it (a decision's second branch goes right). */
function placeAfter(from: GraphNode<DiagramNodeData>, kind: NodeKind) {
  const fromSize = nodeSize(from.data)
  const size = nodeSize({ kind, label: '' })
  const outgoing = edges.value.filter((e) => e.source === from.id).length
  const isDecision = from.data.kind === 'decision'
  const label = isDecision ? (['Yes', 'No'][outgoing] ?? '') : ''
  if (isDecision && outgoing === 1) {
    return { x: from.position.x + fromSize.w + 72, y: from.position.y + fromSize.h / 2 - size.h / 2, sides: ['r', 'l'] as Side[], label }
  }
  const sideStep = outgoing > 0 ? (outgoing - (isDecision ? 1 : 0)) * (size.w + 48) : 0
  return {
    x: from.position.x + fromSize.w / 2 - size.w / 2 + sideStep,
    y: from.position.y + fromSize.h + 64,
    sides: ['b', 't'] as Side[],
    label,
  }
}

/** Moves a new shape right, past anything it would overlap, so shapes never land on top of each other. */
function freeSpot(x: number, y: number, w: number, h: number) {
  const gap = 32
  for (let tries = 0; tries < 100; tries++) {
    const hit = nodes.value.find((n) => {
      const size = nodeSize(n.data as DiagramNodeData)
      return x < n.position.x + size.w + gap && x + w + gap > n.position.x && y < n.position.y + size.h + gap && y + h + gap > n.position.y
    })
    if (!hit) break
    x = hit.position.x + nodeSize(hit.data as DiagramNodeData).w + 48
  }
  return { x: Math.round(x / 8) * 8, y: Math.round(y / 8) * 8 }
}

function freshNode(kind: NodeKind, x: number, y: number, preset: Partial<DiagramNode> = {}): DiagramNode {
  const node: DiagramNode = { id: newId('n'), kind, x, y, label: '' }
  if (kind === 'class') Object.assign(node, { stereotype: '', attributes: [], methods: [] })
  if (kind === 'table') node.columns = [{ name: 'id', type: 'int', pk: true }]
  return Object.assign(node, preset)
}

async function addShape(kind: NodeKind, preset: Partial<DiagramNode> = {}) {
  const from = selectedNode.value
  const size = nodeSize({ kind, label: '', columns: kind === 'table' ? [{ name: '', type: '' }] : undefined })
  let node: DiagramNode

  if (from && isFlowKind(kind) && isFlowKind(from.data.kind)) {
    const place = placeAfter(from, kind)
    const spot = freeSpot(place.x, place.y, size.w, size.h)
    node = freshNode(kind, spot.x, spot.y, preset)
    addNodes(toNode(node, true))
    addEdges(toEdge({ id: newId('e'), source: from.id, target: node.id, sourceHandle: place.sides[0], targetHandle: place.sides[1], kind: 'arrow', label: place.label }))
  } else if (from) {
    // Anything else goes beside the selection, unconnected.
    const spot = freeSpot(from.position.x + nodeSize(from.data).w + 80, from.position.y, size.w, size.h)
    node = freshNode(kind, spot.x, spot.y, preset)
    addNodes(toNode(node, !['class', 'table'].includes(kind)))
  } else {
    // Nothing selected: the middle of the view, or, if that's taken, the nearest clear spot to its right.
    const box = canvas.value?.getBoundingClientRect()
    const centre = box ? screenToFlowCoordinate({ x: box.left + box.width / 2, y: box.top + box.height / 2 }) : { x: 0, y: 0 }
    const spot = freeSpot(centre.x - size.w / 2, centre.y - size.h / 2, size.w, size.h)
    node = freshNode(kind, spot.x, spot.y, preset)
    addNodes(toNode(node, !['class', 'table'].includes(kind)))
  }

  await nextTick()
  removeSelectedElements()
  const added = findNode(node.id)
  if (added) addSelectedNodes([added])
  fitView({ padding: 0.25, maxZoom: 1, duration: 200 })
  // Classes and tables are named in the side panel.
  if (kind === 'class' || kind === 'table') focusName()
}

async function focusName() {
  await nextTick()
  nameField.value?.focus()
  nameField.value?.select()
}

onConnect((connection: Connection) => {
  const source = findNode(connection.source)?.data as DiagramNodeData | undefined
  const target = findNode(connection.target)?.data as DiagramNodeData | undefined
  addEdges(
    toEdge({
      id: newId('e'),
      source: connection.source,
      target: connection.target,
      sourceHandle: (connection.sourceHandle ?? 'b') as Side,
      targetHandle: (connection.targetHandle ?? 't') as Side,
      kind: source && target ? defaultEdgeKind(source.kind, target.kind) : 'arrow',
    }),
  )
})

const isValidConnection = (c: Connection) => c.source !== c.target

/* ---------- Side panel: edit what's selected ---------- */

const selectedNode = computed(() => (getSelectedNodes.value.length === 1 ? asNode(getSelectedNodes.value[0]) : null))
const selectedEdge = computed(() =>
  !getSelectedNodes.value.length && getSelectedEdges.value.length === 1 ? (getSelectedEdges.value[0] as GraphEdge<DiagramEdgeData>) : null,
)

function patchNode(patch: Partial<DiagramNodeData>) {
  if (selectedNode.value) updateNodeData(selectedNode.value.id, patch)
}

const nodeLabel = computed({ get: () => selectedNode.value?.data.label ?? '', set: (label: string) => patchNode({ label }) })
const stereotype = computed({ get: () => selectedNode.value?.data.stereotype ?? '', set: (v: Stereotype) => patchNode({ stereotype: v }) })
/** One entry per line; blank lines are kept while typing and dropped when the diagram is saved. */
const lines = (key: 'attributes' | 'methods') =>
  computed({ get: () => (selectedNode.value?.data[key] ?? []).join('\n'), set: (v: string) => patchNode({ [key]: v.split('\n') }) })
const attributes = lines('attributes')
const methods = lines('methods')

const columns = computed(() => selectedNode.value?.data.columns ?? [])
function setColumn(index: number, patch: Partial<Column>) {
  patchNode({ columns: columns.value.map((c, i) => (i === index ? { ...c, ...patch } : c)) })
}
async function addColumn() {
  patchNode({ columns: [...columns.value, { name: '', type: '' }] })
  await nextTick()
  document.querySelector<HTMLInputElement>('.dg-columns tr:last-child input')?.focus()
}
function removeColumn(index: number) {
  patchNode({ columns: columns.value.filter((_, i) => i !== index) })
}

const edgeKind = computed({
  get: () => selectedEdge.value?.data?.kind ?? 'arrow',
  set: (kind: EdgeKind) => selectedEdge.value && updateEdgeData(selectedEdge.value.id, { kind }),
})
const edgeLabel = computed({
  get: () => String(selectedEdge.value?.label ?? ''),
  set: (v: string) => {
    if (selectedEdge.value) selectedEdge.value.label = v
  },
})
const endLabel = (key: 'sourceLabel' | 'targetLabel') =>
  computed({ get: () => selectedEdge.value?.data?.[key] ?? '', set: (v: string) => selectedEdge.value && updateEdgeData(selectedEdge.value.id, { [key]: v }) })
const sourceLabel = endLabel('sourceLabel')
const targetLabel = endLabel('targetLabel')

const edgeGroups = computed(() => {
  const groups: Record<string, { id: EdgeKind; name: string }[]> = {}
  for (const [id, spec] of Object.entries(EDGE_KINDS)) (groups[spec.group] ??= []).push({ id: id as EdgeKind, name: spec.name })
  return groups
})

function reverseEdge() {
  const e = selectedEdge.value
  if (!e) return
  removeEdges([e.id])
  addEdges(
    toEdge({
      id: newId('e'),
      source: e.target,
      target: e.source,
      sourceHandle: (e.targetHandle ?? 't') as Side,
      targetHandle: (e.sourceHandle ?? 'b') as Side,
      kind: e.data?.kind ?? 'arrow',
      label: e.label ? String(e.label) : undefined,
      sourceLabel: e.data?.targetLabel,
      targetLabel: e.data?.sourceLabel,
    }),
  )
}

function deleteSelected() {
  if (selectedNode.value) removeNodes([selectedNode.value.id])
  else if (selectedEdge.value) removeEdges([selectedEdge.value.id])
}

/* ---------- Saving ---------- */

const clean = (list?: string[]) => (list ?? []).map((s) => s.trim()).filter(Boolean)

function toDoc(): DiagramDoc {
  return {
    nodes: nodes.value.map((n) => {
      const data = { ...(n.data as DiagramNodeData) }
      delete data.editing
      const node: DiagramNode = { ...data, id: n.id, x: Math.round(n.position.x), y: Math.round(n.position.y), label: data.label.trim() }
      if (data.kind === 'class') Object.assign(node, { attributes: clean(data.attributes), methods: clean(data.methods) })
      if (data.kind === 'table') node.columns = (data.columns ?? []).filter((c) => c.name.trim()).map((c) => ({ ...c, name: c.name.trim(), type: c.type.trim() }))
      return node
    }),
    edges: edges.value.map((e) => {
      const data = e.data as DiagramEdgeData
      return {
        id: e.id,
        source: e.source,
        target: e.target,
        sourceHandle: (e.sourceHandle ?? 'b') as Side,
        targetHandle: (e.targetHandle ?? 't') as Side,
        kind: data?.kind ?? 'arrow',
        ...(e.label ? { label: String(e.label) } : {}),
        ...(data?.sourceLabel ? { sourceLabel: data.sourceLabel } : {}),
        ...(data?.targetLabel ? { targetLabel: data.targetLabel } : {}),
      }
    }),
  }
}

function submit() {
  // Finish any in-place rename first so its text is included.
  ;(document.activeElement as HTMLElement | null)?.blur()
  nextTick(() => emit('insert', toDoc()))
}

function onNodeDoubleClick() {
  // Other kinds rename in place; classes and tables are edited in the side panel.
  const kind = selectedNode.value?.data.kind
  if (kind === 'class' || kind === 'table') focusName()
}

onMounted(() => dialog.value?.showModal())
</script>

<template>
  <!-- Esc closes this dialog only, never the popover or note editor underneath. -->
  <dialog
    ref="dialog"
    class="diagram-editor"
    aria-labelledby="diagram-editor-title"
    @cancel.prevent="emit('close')"
    @keydown.esc.stop="emit('close')"
  >
    <!-- Connector end markers for every connector on the canvas. -->
    <!-- eslint-disable-next-line vue/no-v-html -- fixed marker markup -->
    <svg class="dg-marker-defs" aria-hidden="true"><defs v-html="markers"></defs></svg>

    <div class="dg-head">
      <h2 id="diagram-editor-title">{{ editing ? 'Edit diagram' : 'Diagram' }}</h2>
      <button type="button" class="dg-close" aria-label="Close" @click="emit('close')">&times;</button>
    </div>

    <div class="dg-toolbar">
      <div class="dg-sets" role="tablist" aria-label="Shape set">
        <button
          v-for="(set, id) in SETS"
          :key="id"
          type="button"
          role="tab"
          :aria-selected="activeSet === id"
          @click="activeSet = id"
        >
          {{ set.name }}
        </button>
      </div>
      <div class="dg-shapes" role="group" aria-label="Add a shape">
        <button v-for="kind in SETS[activeSet].kinds" :key="kind" type="button" class="dg-shape-btn" @click="addShape(kind)">
          <!-- eslint-disable-next-line vue/no-v-html -- fixed outline markup -->
          <svg v-if="isFlowKind(kind)" :class="['dg-kind-icon', `dg-shape-${kind}`]" :viewBox="`0 0 ${nodeSize({ kind, label: '' }).w} ${nodeSize({ kind, label: '' }).h}`" aria-hidden="true" v-html="flowOutline(kind)"></svg>
          <span v-else :class="['dg-kind-glyph', `dg-glyph-${kind}`]" aria-hidden="true"></span>
          {{ shapeLabel(kind) }}
        </button>
        <button v-if="activeSet === 'uml'" type="button" class="dg-shape-btn" @click="addShape('class', { stereotype: 'interface' })">
          <span class="dg-kind-glyph dg-glyph-class" aria-hidden="true"></span>
          Interface
        </button>
      </div>
      <div class="dg-shapes dg-shapes-common" role="group" aria-label="Add text">
        <button type="button" class="dg-shape-btn" @click="addShape('text')"><span class="dg-kind-glyph dg-glyph-text" aria-hidden="true">T</span>Text</button>
        <button type="button" class="dg-shape-btn" @click="addShape('note')"><span class="dg-kind-glyph dg-glyph-note" aria-hidden="true"></span>Note</button>
      </div>
    </div>

    <div class="dg-body">
      <div ref="canvas" class="dg-canvas">
        <VueFlow
          :id="FLOW_ID"
          :node-types="nodeTypes"
          :edge-types="edgeTypes"
          :connection-mode="ConnectionMode.Loose"
          :is-valid-connection="isValidConnection"
          :delete-key-code="['Backspace', 'Delete']"
          :snap-to-grid="true"
          :snap-grid="[8, 8]"
          :min-zoom="0.25"
          :max-zoom="2"
          @pane-ready="fitView({ padding: 0.25, maxZoom: 1 })"
          @node-double-click="onNodeDoubleClick"
        />
        <p v-if="!nodes.length" class="dg-empty-hint">Pick a shape above to start. You can mix flowchart, UML and database shapes.</p>
      </div>

      <aside class="dg-panel" aria-label="Selected item">
        <template v-if="selectedNode">
          <h3>{{ shapeLabel(selectedNode.data.kind) }}</h3>

          <template v-if="selectedNode.data.kind === 'class'">
            <label for="dg-name">Name</label>
            <input id="dg-name" ref="nameField" v-model="nodeLabel" type="text" placeholder="Book" />
            <label for="dg-stereotype">Kind</label>
            <select id="dg-stereotype" v-model="stereotype">
              <option value="">Class</option>
              <option value="interface">Interface</option>
              <option value="abstract">Abstract class</option>
            </select>
            <label for="dg-attributes">Attributes <small>one per line</small></label>
            <textarea id="dg-attributes" v-model="attributes" rows="4" placeholder="- title: string&#10;- year: int"></textarea>
            <label for="dg-methods">Methods <small>one per line</small></label>
            <textarea id="dg-methods" v-model="methods" rows="3" placeholder="+ borrow(user): Loan"></textarea>
          </template>

          <template v-else-if="selectedNode.data.kind === 'table'">
            <label for="dg-name">Table name</label>
            <input id="dg-name" ref="nameField" v-model="nodeLabel" type="text" placeholder="books" />
            <table class="dg-columns">
              <thead>
                <tr><th>Column</th><th>Type</th><th title="Primary key">PK</th><th title="Foreign key">FK</th><th><span class="visually-hidden">Remove</span></th></tr>
              </thead>
              <tbody>
                <tr v-for="(col, i) in columns" :key="i">
                  <td><input :value="col.name" type="text" aria-label="Column name" placeholder="name" @input="setColumn(i, { name: ($event.target as HTMLInputElement).value })" /></td>
                  <td><input :value="col.type" type="text" list="dg-types" aria-label="Column type" placeholder="text" @input="setColumn(i, { type: ($event.target as HTMLInputElement).value })" /></td>
                  <td><input :checked="col.pk" type="checkbox" aria-label="Primary key" @change="setColumn(i, { pk: ($event.target as HTMLInputElement).checked })" /></td>
                  <td><input :checked="col.fk" type="checkbox" aria-label="Foreign key" @change="setColumn(i, { fk: ($event.target as HTMLInputElement).checked })" /></td>
                  <td><button type="button" class="dg-remove" aria-label="Remove column" @click="removeColumn(i)">&times;</button></td>
                </tr>
              </tbody>
            </table>
            <datalist id="dg-types">
              <option v-for="t in ['int', 'bigint', 'text', 'varchar(255)', 'boolean', 'date', 'timestamp', 'decimal', 'uuid', 'json']" :key="t" :value="t" />
            </datalist>
            <button type="button" class="dg-add-row" @click="addColumn">Add column</button>
          </template>

          <template v-else>
            <label for="dg-name">Text</label>
            <textarea id="dg-name" v-model="nodeLabel" rows="3" placeholder="Name this shape"></textarea>
          </template>

          <button type="button" class="dg-delete" @click="deleteSelected">Delete {{ shapeLabel(selectedNode.data.kind).toLowerCase() }}</button>
        </template>

        <template v-else-if="selectedEdge">
          <h3>Connector</h3>
          <label for="dg-edge-kind">Style</label>
          <select id="dg-edge-kind" v-model="edgeKind">
            <optgroup v-for="(kinds, group) in edgeGroups" :key="group" :label="group">
              <option v-for="k in kinds" :key="k.id" :value="k.id">{{ k.name }}</option>
            </optgroup>
          </select>
          <label for="dg-edge-label">Label</label>
          <input id="dg-edge-label" v-model="edgeLabel" type="text" placeholder="e.g. Yes" />
          <div class="dg-ends">
            <div>
              <label for="dg-edge-start">Start label</label>
              <input id="dg-edge-start" v-model="sourceLabel" type="text" placeholder="e.g. 1" />
            </div>
            <div>
              <label for="dg-edge-end">End label</label>
              <input id="dg-edge-end" v-model="targetLabel" type="text" placeholder="e.g. *" />
            </div>
          </div>
          <button type="button" class="dg-add-row" @click="reverseEdge">Reverse direction</button>
          <button type="button" class="dg-delete" @click="deleteSelected">Delete connector</button>
        </template>

        <div v-else class="dg-panel-help">
          <h3>Getting around</h3>
          <ul>
            <li>Pick a shape above to add it. In a flowchart, it follows the selected shape, already connected.</li>
            <li>Drag from a dot on a shape's edge to another shape to connect them.</li>
            <li>Select a shape or connector to edit it here.</li>
            <li>Double-click to rename. Delete removes what's selected. Scroll to zoom, drag the background to pan.</li>
          </ul>
        </div>
      </aside>
    </div>

    <div class="dg-foot">
      <button type="button" class="link-btn" @click="emit('close')">Cancel</button>
      <button type="button" class="btn btn-primary" :disabled="!nodes.length" @click="submit">
        {{ editing ? 'Update diagram' : 'Insert diagram' }}
      </button>
    </div>
  </dialog>
</template>

<style scoped src="@/css/components/DiagramDialog.css"></style>
