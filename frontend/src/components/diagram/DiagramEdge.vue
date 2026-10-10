<script setup lang="ts">
import { computed } from 'vue'
import { BaseEdge, EdgeLabelRenderer, Position, getSmoothStepPath, type EdgeProps } from '@vue-flow/core'
import { EDITOR_MARKERS, edgeMarkers, endLabelPoint, type EdgeKind, type Side } from '@/utils/diagram'

export interface DiagramEdgeData {
  kind: EdgeKind
  sourceLabel?: string
  targetLabel?: string
}

/** Connector in the editor: same path, end markers and end labels as the saved rendering. */
const props = defineProps<EdgeProps<DiagramEdgeData>>()

const SIDE: Record<Position, Side> = { [Position.Top]: 't', [Position.Right]: 'r', [Position.Bottom]: 'b', [Position.Left]: 'l' }

const path = computed(() =>
  getSmoothStepPath({
    sourceX: props.sourceX,
    sourceY: props.sourceY,
    sourcePosition: props.sourcePosition,
    targetX: props.targetX,
    targetY: props.targetY,
    targetPosition: props.targetPosition,
    borderRadius: 6,
  }),
)
const markers = computed(() => edgeMarkers(props.data?.kind ?? 'arrow', EDITOR_MARKERS))
const sourceEnd = computed(() => endLabelPoint(props.sourceX, props.sourceY, SIDE[props.sourcePosition]))
const targetEnd = computed(() => endLabelPoint(props.targetX, props.targetY, SIDE[props.targetPosition]))
</script>

<template>
  <BaseEdge
    :id="id"
    :path="path[0]"
    :label-x="path[1]"
    :label-y="path[2]"
    :label="label"
    :marker-start="markers.markerStart"
    :marker-end="markers.markerEnd"
    :class="['dg-edge', { 'is-dashed': markers.dashed, 'is-selected': selected }]"
    :interaction-width="16"
  />
  <EdgeLabelRenderer>
    <div
      v-if="data?.sourceLabel"
      class="dg-end-tag nodrag nopan"
      :style="{ transform: `translate(-50%, -50%) translate(${sourceEnd[0]}px, ${sourceEnd[1]}px)` }"
    >
      {{ data.sourceLabel }}
    </div>
    <div
      v-if="data?.targetLabel"
      class="dg-end-tag nodrag nopan"
      :style="{ transform: `translate(-50%, -50%) translate(${targetEnd[0]}px, ${targetEnd[1]}px)` }"
    >
      {{ data.targetLabel }}
    </div>
  </EdgeLabelRenderer>
</template>
