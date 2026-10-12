/** Triggers a browser save-as download for an in-memory blob. */
export function downloadBlob(blob: Blob, filename: string): void {
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  document.body.appendChild(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(url)
}

/** Falls back to the generic book placeholder when a cover/thumbnail image fails to load. */
export function onThumbnailError(event: Event): void {
  ;(event.target as HTMLImageElement).src = '/assets/uploads/thumbnails/placeholder-book.jpg'
}

/**
 * Scrolls `scroller` (not the window) by the given offsets. Smooth for short hops; long jumps are instant,
 * since smooth-scrolling across a book would lazily render every page passed on the way.
 */
export function scrollWithin(scroller: HTMLElement, top: number, left = 0): void {
  const far = Math.abs(top) > scroller.clientHeight * 2
  scroller.scrollBy({ top, left, behavior: far ? 'instant' : 'smooth' })
}
