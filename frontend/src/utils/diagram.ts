import { getSmoothStepPath, Position } from '@vue-flow/core'

/**
 * Diagrams drawn in the visual editor: flowcharts, UML class diagrams, database schemas, plus free text
 * and sticky notes. A note stores one as a ```diagram block of this JSON. Positions are kept, so the
 * note shows the diagram exactly as it was laid out.
 *
 * The editor and the note renderer both size nodes with nodeSize(), fill them with nodeHtml() and draw
 * connector ends with markerDefs(), so the two can't drift apart.
 */

export type FlowKind = 'terminal' | 'step' | 'decision' | 'io'
export type NodeKind = FlowKind | 'class' | 'table' | 'text' | 'note'
export type Side = 't' | 'r' | 'b' | 'l'
export type Stereotype = '' | 'interface' | 'abstract'

export interface Column {
  name: string
  type: string
  pk?: boolean
  fk?: boolean
}

export interface DiagramNode {
  id: string
  kind: NodeKind
  x: number
  y: number
  /** Shape text, class name, table name, or note/text body. */
  label: string
  stereotype?: Stereotype
  attributes?: string[]
  methods?: string[]
  columns?: Column[]
}

export type EdgeKind =
  | 'arrow'
  | 'line'
  | 'association'
  | 'inherit'
  | 'implement'
  | 'depend'
  | 'compose'
  | 'aggregate'
  | 'one-one'
  | 'one-many'
  | 'many-many'

export interface DiagramEdge {
  id: string
  source: string
  target: string
  sourceHandle: Side
  targetHandle: Side
  kind: EdgeKind
  label?: string
  /** Small labels at either end, e.g. UML multiplicities "1" and "*". */
  sourceLabel?: string
  targetLabel?: string
}

export interface DiagramDoc {
  nodes: DiagramNode[]
  edges: DiagramEdge[]
}

/* ---------- Node kinds and sizes ---------- */

export const NODE_KINDS: Record<NodeKind, { name: string }> = {
  terminal: { name: 'Start / end' },
  step: { name: 'Step' },
  decision: { name: 'Decision' },
  io: { name: 'Input / output' },
  class: { name: 'Class' },
  table: { name: 'Table' },
  text: { name: 'Text' },
  note: { name: 'Note' },
}

/**
 * Flowchart shapes have fixed sizes; half the difference between any two is a multiple of 8 (the editor's
 * snap grid), so a shape centred under another stays centred and connectors run straight.
 */
const FLOW_SIZE: Record<FlowKind, { w: number; h: number }> = {
  terminal: { w: 144, h: 48 },
  step: { w: 176, h: 64 },
  decision: { w: 160, h: 112 },
  io: { w: 176, h: 64 },
}

export const isFlowKind = (kind: NodeKind): kind is FlowKind => kind in FLOW_SIZE
/** Classes and tables are edited in the side panel; every other kind is renamed in place. */
export const sidePanelKind = (kind: NodeKind) => kind === 'class' || kind === 'table'

const ROW = 20
const lineCount = (text: string) => Math.max(1, text.split('\n').length)

/** Content-driven size: classes and tables grow with their rows; rows never wrap, so heights are exact. */
export function nodeSize(node: Pick<DiagramNode, 'kind' | 'label' | 'stereotype' | 'attributes' | 'methods' | 'columns'>) {
  switch (node.kind) {
    case 'class': {
      const head = node.stereotype === 'interface' ? 44 : 32
      const section = (rows?: string[]) => Math.max(1, rows?.length ?? 0) * ROW + 8
      return { w: 224, h: head + section(node.attributes) + section(node.methods) }
    }
    case 'table':
      return { w: 240, h: 32 + Math.max(1, node.columns?.length ?? 0) * 24 + 6 }
    case 'text':
      return { w: 208, h: lineCount(node.label) * 20 + 12 }
    case 'note':
      return { w: 192, h: Math.max(80, lineCount(node.label) * 19 + 28) }
    default:
      return FLOW_SIZE[node.kind]
  }
}

/** SVG outline for flowchart shapes at the origin (other kinds draw their box in HTML). */
export function flowOutline(kind: FlowKind): string {
  const { w, h } = FLOW_SIZE[kind]
  switch (kind) {
    case 'terminal':
      return `<rect x="1" y="1" width="${w - 2}" height="${h - 2}" rx="${(h - 2) / 2}" />`
    case 'decision':
      return `<polygon points="${w / 2},1 ${w - 1},${h / 2} ${w / 2},${h - 1} 1,${h / 2}" />`
    case 'io':
      return `<polygon points="16,1 ${w - 1},1 ${w - 16},${h - 1} 1,${h - 1}" />`
    default:
      return `<rect x="1" y="1" width="${w - 2}" height="${h - 2}" rx="4" />`
  }
}

function escapeHtml(text: string): string {
  return text.replace(/[&<>"']/g, (c) => `&#${c.charCodeAt(0)};`)
}

/**
 * The inside of a node as HTML (escaped). `editing` shows placeholders for empty parts so a fresh
 * class or table isn't invisible; the saved rendering leaves them blank.
 */
export function nodeHtml(node: DiagramNode, editing = false): string {
  const hint = (text: string) => (editing ? `<span class="dg-placeholder">${text}</span>` : '')
  const rows = (items: string[] | undefined, empty: string) =>
    `<ul class="dg-rows">${items?.length ? items.map((r) => `<li>${escapeHtml(r)}</li>`).join('') : `<li>${hint(empty)}</li>`}</ul>`

  switch (node.kind) {
    case 'class': {
      const tag = node.stereotype === 'interface' ? '<small>«interface»</small>' : ''
      const name = node.label ? escapeHtml(node.label) : hint('Class name')
      return (
        `<div class="dg-class-head${node.stereotype === 'abstract' ? ' is-abstract' : ''}">${tag}<strong>${name}</strong></div>` +
        rows(node.attributes, 'attributes') +
        rows(node.methods, 'methods')
      )
    }
    case 'table': {
      const cols = node.columns?.length
        ? node.columns
            .map(
              (c) =>
                `<li><span class="dg-key">${c.pk ? 'PK' : ''}${c.pk && c.fk ? ' ' : ''}${c.fk ? 'FK' : ''}</span>` +
                `<span class="dg-col${c.pk ? ' is-pk' : ''}">${escapeHtml(c.name)}</span><span class="dg-type">${escapeHtml(c.type)}</span></li>`,
            )
            .join('')
        : `<li>${hint('No columns yet')}</li>`
      return `<div class="dg-table-head">${node.label ? escapeHtml(node.label) : hint('table_name')}</div><ul class="dg-cols">${cols}</ul>`
    }
    default:
      return `<div class="dg-label">${node.label ? escapeHtml(node.label) : hint(node.kind === 'note' ? 'Note' : 'Text')}</div>`
  }
}

/* ---------- Connectors ---------- */

type MarkerName = 'arrow' | 'open' | 'triangle' | 'diamond' | 'diamond-filled' | 'one' | 'many'

export const EDGE_KINDS: Record<EdgeKind, { name: string; group: string; dashed?: boolean; start?: MarkerName; end?: MarkerName }> = {
  arrow: { name: 'Arrow', group: 'General', end: 'arrow' },
  line: { name: 'Line', group: 'General' },
  association: { name: 'Association', group: 'UML', end: 'open' },
  inherit: { name: 'Inherits from', group: 'UML', end: 'triangle' },
  implement: { name: 'Implements', group: 'UML', dashed: true, end: 'triangle' },
  depend: { name: 'Depends on', group: 'UML', dashed: true, end: 'open' },
  compose: { name: 'Composition (owns)', group: 'UML', start: 'diamond-filled' },
  aggregate: { name: 'Aggregation (has)', group: 'UML', start: 'diamond' },
  'one-one': { name: 'One to one', group: 'Database', start: 'one', end: 'one' },
  'one-many': { name: 'One to many', group: 'Database', start: 'one', end: 'many' },
  'many-many': { name: 'Many to many', group: 'Database', start: 'many', end: 'many' },
}

/**
 * Connector end markers. Each is drawn pointing along +x into the node it touches, so with
 * orient="auto-start-reverse" the same marker works at either end.
 */
export function markerDefs(prefix: string): string {
  const m = (name: MarkerName, w: number, body: string) =>
    `<marker id="${prefix}-${name}" class="dg-marker dg-marker-${name}" viewBox="0 0 ${w} 12" refX="${w}" refY="6" ` +
    `markerWidth="${w}" markerHeight="12" markerUnits="userSpaceOnUse" orient="auto-start-reverse">${body}</marker>`
  return (
    m('arrow', 11, '<path d="M0,1 L11,6 L0,11 z" />') +
    m('open', 11, '<path d="M0,1 L11,6 L0,11" />') +
    m('triangle', 13, '<path d="M1,0.5 L13,6 L1,11.5 z" />') +
    m('diamond', 18, '<path d="M1,6 L9.5,1 L18,6 L9.5,11 z" />') +
    m('diamond-filled', 18, '<path d="M1,6 L9.5,1 L18,6 L9.5,11 z" />') +
    m('one', 14, '<path d="M0,6 L14,6 M5,1 L5,11 M9,1 L9,11" />') +
    m('many', 14, '<path d="M0,6 L14,6 M4,1 L4,11 M4,6 L14,0.5 M4,6 L14,11.5" />')
  )
}

/** The editor dialog renders markerDefs() once under this prefix for all its connectors. */
export const EDITOR_MARKERS = 'dg-editor'

export function edgeMarkers(kind: EdgeKind, prefix: string) {
  const spec = EDGE_KINDS[kind] ?? EDGE_KINDS.arrow
  return {
    markerStart: spec.start ? `url(#${prefix}-${spec.start})` : undefined,
    markerEnd: spec.end ? `url(#${prefix}-${spec.end})` : undefined,
    dashed: !!spec.dashed,
  }
}

/** A sensible default for a new connection, from what it joins. */
export function defaultEdgeKind(source: NodeKind, target: NodeKind): EdgeKind {
  if (source === 'table' && target === 'table') return 'one-many'
  if (source === 'class' && target === 'class') return 'association'
  if (source === 'note' || target === 'note' || source === 'text' || target === 'text') return 'line'
  return 'arrow'
}

export const SIDE_POSITION: Record<Side, Position> = { t: Position.Top, r: Position.Right, b: Position.Bottom, l: Position.Left }
const NORMAL: Record<Side, [number, number]> = { t: [0, -1], r: [1, 0], b: [0, 1], l: [-1, 0] }

function anchor(node: DiagramNode, side: Side): [number, number] {
  const { w, h } = nodeSize(node)
  if (side === 't') return [node.x + w / 2, node.y]
  if (side === 'b') return [node.x + w / 2, node.y + h]
  if (side === 'l') return [node.x, node.y + h / 2]
  return [node.x + w, node.y + h / 2]
}

/** Where an end label sits: just off the node, beside the connector. */
export function endLabelPoint(x: number, y: number, side: Side): [number, number] {
  const [nx, ny] = NORMAL[side]
  return [x + nx * 18 + (ny !== 0 ? 12 : 0), y + ny * 18 + (nx !== 0 ? -10 : 0)]
}

export function edgePath(sx: number, sy: number, sourceSide: Side, tx: number, ty: number, targetSide: Side) {
  return getSmoothStepPath({
    sourceX: sx,
    sourceY: sy,
    sourcePosition: SIDE_POSITION[sourceSide],
    targetX: tx,
    targetY: ty,
    targetPosition: SIDE_POSITION[targetSide],
    borderRadius: 6,
  })
}

/* ---------- Parsing ---------- */

const SIDES = ['t', 'r', 'b', 'l']
const str = (v: unknown, max = 500) => (typeof v === 'string' ? v.slice(0, max) : '')
const strList = (v: unknown) => (Array.isArray(v) ? v.slice(0, 100).map((x) => str(x)) : undefined)

/** Parses and validates a diagram block; malformed parts are dropped. */
export function parseDiagram(json: string): DiagramDoc {
  const raw = JSON.parse(json) as { nodes?: unknown[]; edges?: unknown[] }
  const nodes: DiagramNode[] = (raw.nodes ?? []).slice(0, 500).flatMap((n) => {
    const node = n as Record<string, unknown>
    const kind = node.kind as NodeKind
    if (typeof node.id !== 'string' || !(kind in NODE_KINDS) || !Number.isFinite(node.x) || !Number.isFinite(node.y)) return []
    const out: DiagramNode = { id: node.id, kind, x: Number(node.x), y: Number(node.y), label: str(node.label, 5000) }
    if (kind === 'class') {
      out.stereotype = (['interface', 'abstract'].includes(node.stereotype as string) ? node.stereotype : '') as Stereotype
      out.attributes = strList(node.attributes) ?? []
      out.methods = strList(node.methods) ?? []
    }
    if (kind === 'table') {
      out.columns = (Array.isArray(node.columns) ? node.columns : []).slice(0, 100).map((c) => {
        const col = c as Record<string, unknown>
        return { name: str(col.name), type: str(col.type), pk: !!col.pk, fk: !!col.fk }
      })
    }
    return [out]
  })
  const ids = new Set(nodes.map((n) => n.id))
  const edges: DiagramEdge[] = (raw.edges ?? []).slice(0, 1000).flatMap((e) => {
    const edge = e as Record<string, unknown>
    if (typeof edge.id !== 'string' || !ids.has(edge.source as string) || !ids.has(edge.target as string)) return []
    if (!SIDES.includes(edge.sourceHandle as string) || !SIDES.includes(edge.targetHandle as string)) return []
    return [
      {
        id: edge.id,
        source: edge.source as string,
        target: edge.target as string,
        sourceHandle: edge.sourceHandle as Side,
        targetHandle: edge.targetHandle as Side,
        kind: (edge.kind as string) in EDGE_KINDS ? (edge.kind as EdgeKind) : 'arrow',
        label: str(edge.label) || undefined,
        sourceLabel: str(edge.sourceLabel) || undefined,
        targetLabel: str(edge.targetLabel) || undefined,
      },
    ]
  })
  return { nodes, edges }
}

/* ---------- Static rendering for notes ---------- */

let renderCount = 0

/** Static SVG of a diagram. Colours are CSS variables, so it follows the reader's theme. */
export function renderDiagramSvg(doc: DiagramDoc): string {
  if (!doc.nodes.length) return '<p class="dg-empty">Empty diagram</p>'
  const sized = doc.nodes.map((n) => ({ n, ...nodeSize(n) }))
  const pad = 28
  const minX = Math.min(...sized.map((s) => s.n.x)) - pad
  const minY = Math.min(...sized.map((s) => s.n.y)) - pad
  const width = Math.max(...sized.map((s) => s.n.x + s.w)) + pad - minX
  const height = Math.max(...sized.map((s) => s.n.y + s.h)) + pad - minY
  const prefix = `dgm${++renderCount}`
  const byId = new Map(doc.nodes.map((n) => [n.id, n]))

  const edges = doc.edges
    .map((edge) => {
      const [sx, sy] = anchor(byId.get(edge.source)!, edge.sourceHandle)
      const [tx, ty] = anchor(byId.get(edge.target)!, edge.targetHandle)
      const [path, labelX, labelY] = edgePath(sx, sy, edge.sourceHandle, tx, ty, edge.targetHandle)
      const { markerStart, markerEnd, dashed } = edgeMarkers(edge.kind, prefix)
      const text = (x: number, y: number, value?: string, cls = 'dg-edge-label') =>
        value ? `<text class="${cls}" x="${x}" y="${y}" text-anchor="middle" dominant-baseline="middle">${escapeHtml(value)}</text>` : ''
      return (
        `<path class="dg-edge${dashed ? ' is-dashed' : ''}" d="${path}"` +
        `${markerStart ? ` marker-start="${markerStart}"` : ''}${markerEnd ? ` marker-end="${markerEnd}"` : ''} />` +
        text(labelX, labelY, edge.label) +
        text(...endLabelPoint(sx, sy, edge.sourceHandle), edge.sourceLabel, 'dg-edge-label dg-end-label') +
        text(...endLabelPoint(tx, ty, edge.targetHandle), edge.targetLabel, 'dg-edge-label dg-end-label')
      )
    })
    .join('')

  const nodes = sized
    .map(({ n, w, h }) => {
      const outline = isFlowKind(n.kind) ? `<g class="dg-outline">${flowOutline(n.kind)}</g>` : ''
      return (
        `<g class="dg-shape dg-shape-${n.kind}" transform="translate(${n.x} ${n.y})">${outline}` +
        `<foreignObject width="${w}" height="${h}"><div xmlns="http://www.w3.org/1999/xhtml" class="dg-node dg-${n.kind}">${nodeHtml(n)}</div></foreignObject></g>`
      )
    })
    .join('')

  const summary = escapeHtml(doc.nodes.map((n) => n.label).filter(Boolean).slice(0, 20).join(', '))
  return (
    `<svg class="diagram-svg" viewBox="${minX} ${minY} ${width} ${height}" width="${width}" height="${height}" role="img" aria-label="Diagram: ${summary}">` +
    `<defs>${markerDefs(prefix)}</defs>${edges}${nodes}</svg>`
  )
}
