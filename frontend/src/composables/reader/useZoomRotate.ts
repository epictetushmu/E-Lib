import { ref } from 'vue'

export const MIN_SCALE = 0.5
export const MAX_SCALE = 3
export const DEFAULT_SCALE = 1.5

function clamp(scale: number): number {
  return Math.min(MAX_SCALE, Math.max(MIN_SCALE, scale))
}

/** Zoom/fit-width/rotation state. Callers re-render visible pages when scale or rotation change. */
export function useZoomRotate(initialScale: number = DEFAULT_SCALE) {
  const scale = ref(clamp(initialScale))
  const rotation = ref(0)

  function setScale(next: number) {
    scale.value = clamp(next)
  }

  function zoomIn() {
    setScale(scale.value * 1.15)
  }

  function zoomOut() {
    setScale(scale.value / 1.15)
  }

  function resetZoom() {
    setScale(DEFAULT_SCALE)
  }

  /** containerWidth / a page's unscaled (scale=1) width. */
  function fitWidth(containerWidth: number, baseWidth: number) {
    if (containerWidth > 0 && baseWidth > 0) {
      setScale(containerWidth / baseWidth)
    }
  }

  function rotate() {
    rotation.value = (rotation.value + 90) % 360
  }

  return { scale, rotation, setScale, zoomIn, zoomOut, resetZoom, fitWidth, rotate }
}
