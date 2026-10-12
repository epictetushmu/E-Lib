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

/** A friend's highlight or note, shared with friends who can read the book; read-only here. */
export interface FriendAnnotation extends Annotation {
  user_id: string
  username: string
}

export type NewAnnotation = Pick<Annotation, 'page' | 'rects' | 'quote' | 'note' | 'color'>
