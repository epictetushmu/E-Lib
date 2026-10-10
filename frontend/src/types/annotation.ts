export const HIGHLIGHT_COLORS = [
  { id: 'saffron', label: 'Saffron' },
  { id: 'olive', label: 'Olive' },
  { id: 'aegean', label: 'Aegean' },
  { id: 'rose', label: 'Rose' },
] as const

export type HighlightColor = (typeof HIGHLIGHT_COLORS)[number]['id']

/** A private highlight (has rects) or page note (no rects). Rects are [x1, y1, x2, y2] in PDF user space. */
export interface Annotation {
  id: string
  page: number
  rects: number[][]
  quote: string
  note: string
  color: HighlightColor | null
  created_at: string | null
}

export type NewAnnotation = Pick<Annotation, 'page' | 'rects' | 'quote' | 'note' | 'color'>
