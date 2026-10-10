import type { PageViewport } from 'pdfjs-dist'

/**
 * Positions an absolutely placed overlay over a PDF-space rect ([x1, y1, x2, y2]).
 * Percentages, so it stays put if CSS resizes the page.
 */
export function placeOverPdfRect(el: HTMLElement, rect: number[], viewport: PageViewport): void {
  const [ax, ay] = viewport.convertToViewportPoint(rect[0], rect[1])
  const [bx, by] = viewport.convertToViewportPoint(rect[2], rect[3])
  el.style.left = `${(Math.min(ax, bx) / viewport.width) * 100}%`
  el.style.top = `${(Math.min(ay, by) / viewport.height) * 100}%`
  el.style.width = `${(Math.abs(bx - ax) / viewport.width) * 100}%`
  el.style.height = `${(Math.abs(by - ay) / viewport.height) * 100}%`
}

/** Screen point → PDF space, for a page rendered with `viewport` into `wrapper`. */
export function clientPointToPdf(x: number, y: number, wrapper: HTMLElement, viewport: PageViewport): number[] {
  const box = wrapper.getBoundingClientRect()
  return viewport.convertToPdfPoint(
    ((x - box.left) * viewport.width) / box.width,
    ((y - box.top) * viewport.height) / box.height,
  )
}

/** Screen rect → PDF-space rect, normalised so x1 < x2 and y1 < y2 whatever the page rotation. */
export function clientRectToPdf(r: DOMRect, wrapper: HTMLElement, viewport: PageViewport): number[] {
  const [ax, ay] = clientPointToPdf(r.left, r.top, wrapper, viewport)
  const [bx, by] = clientPointToPdf(r.right, r.bottom, wrapper, viewport)
  return [Math.min(ax, bx), Math.min(ay, by), Math.max(ax, bx), Math.max(ay, by)]
}

/**
 * Selection rects arrive one per text span (plus duplicates for fully selected elements);
 * fold them into one box per line so a highlight reads as a single stroke.
 */
export function mergeLineRects(rects: DOMRect[]): DOMRect[] {
  const sorted = rects.filter((r) => r.width > 1 && r.height > 1).sort((a, b) => a.top - b.top || a.left - b.left)
  const lines: DOMRect[] = []
  for (const r of sorted) {
    const line = lines.find((l) => {
      const overlap = Math.min(l.bottom, r.bottom) - Math.max(l.top, r.top)
      return overlap > Math.min(l.height, r.height) * 0.5 && r.left <= l.right + r.height && r.right >= l.left - r.height
    })
    if (line) {
      const left = Math.min(line.left, r.left)
      const top = Math.min(line.top, r.top)
      lines[lines.indexOf(line)] = new DOMRect(left, top, Math.max(line.right, r.right) - left, Math.max(line.bottom, r.bottom) - top)
    } else {
      lines.push(r)
    }
  }
  return lines
}
