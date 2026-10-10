import { computed, ref, watch, type Ref } from 'vue'
import type { PDFDocumentProxy, PageViewport } from 'pdfjs-dist'
import type { TextItem } from 'pdfjs-dist/types/src/display/api'
import { scrollWithin } from '@/utils/dom'

/** One occurrence of the search term; rects are in PDF user space ([x1, y1, x2, y2]), one per text run it spans. */
export interface SearchMatch {
  page: number
  rects: number[][]
}

interface PageText {
  /** Folded text (see fold()) that queries are matched against. */
  norm: string
  /** norm index → index in the page's raw concatenated text. */
  toRaw: number[]
  items: { start: number; item: TextItem; font: string; ascent: number; descent: number }[]
}

let measureCtx: CanvasRenderingContext2D | null = null

/** Width of `text` in `font`, used only as a ratio within one text run. */
function measure(text: string, font: string): number {
  measureCtx ??= document.createElement('canvas').getContext('2d')
  if (!measureCtx) return text.length
  measureCtx.font = `100px ${font}`
  return measureCtx.measureText(text).width
}

/** Centres el in its scrolling page container without scrolling the window (scrollIntoView would). */
function centerInScroller(el: HTMLElement) {
  const scroller = el.closest<HTMLElement>('.pdf-container')
  if (!scroller) return
  const box = el.getBoundingClientRect()
  const view = scroller.getBoundingClientRect()
  scrollWithin(
    scroller,
    box.top - view.top - (view.height - box.height) / 2,
    box.left - view.left - (view.width - box.width) / 2,
  )
}

/** Case- and accent-insensitive, so "αρχη" finds "Αρχή"; final sigma matches σ. */
function fold(char: string): string {
  return char
    .normalize('NFD')
    .replace(/\p{M}/gu, '')
    .toLowerCase()
    .replace(/ς/g, 'σ')
}

function foldQuery(query: string): string {
  return Array.from(query.trim().replace(/\s+/g, ' '), fold).join('')
}

/** Full-text search across pages via PDF.js getTextContent(), with on-page hit positions. */
export function useReaderSearch(pdfDoc: Ref<PDFDocumentProxy | null>) {
  const term = ref('')
  const searching = ref(false)
  const searchedTerm = ref('')
  const matches = ref<SearchMatch[]>([])
  const current = ref(-1)

  const pageTexts = new Map<number, PageText>()
  let scrollToCurrent = false

  const status = computed(() => {
    if (searching.value) return 'Searching…'
    if (!searchedTerm.value) return ''
    if (!matches.value.length) return 'No matches'
    return `${current.value + 1} of ${matches.value.length}`
  })

  // Editing the query drops the old highlights so they never disagree with what's typed.
  watch(term, (value) => {
    if (searchedTerm.value && value !== searchedTerm.value) reset()
  })

  function reset() {
    searchedTerm.value = ''
    matches.value = []
    current.value = -1
  }

  async function getPageText(doc: PDFDocumentProxy, pageNumber: number): Promise<PageText> {
    const cached = pageTexts.get(pageNumber)
    if (cached) return cached

    const content = await (await doc.getPage(pageNumber)).getTextContent()
    let raw = ''
    const items: PageText['items'] = []
    for (const item of content.items) {
      if (!('str' in item)) continue
      const style = content.styles[item.fontName]
      items.push({
        start: raw.length,
        item,
        font: style?.fontFamily || 'sans-serif',
        ascent: style?.ascent || 0.88,
        descent: style?.descent || -0.22,
      })
      raw += item.str
      if (item.hasEOL) raw += ' '
    }

    let norm = ''
    const toRaw: number[] = []
    for (let i = 0; i < raw.length; i++) {
      const folded = /\s/.test(raw[i]) ? ' ' : fold(raw[i])
      if (folded === ' ' && norm.endsWith(' ')) continue
      for (const ch of folded) {
        norm += ch
        toRaw.push(i)
      }
    }

    const text = { norm, toRaw, items }
    pageTexts.set(pageNumber, text)
    return text
  }

  /**
   * Rect for each text run the raw range [from, to) touches.
   * ponytail: x offsets come from measuring the run in pdf.js's fallback font family (not the embedded font)
   * and rotated/vertical runs are treated as horizontal; switch to pdf.js TextLayer + DOM Ranges if hits drift.
   */
  function rectsFor(text: PageText, from: number, to: number): number[][] {
    const rects: number[][] = []
    for (const { start, item, font, ascent, descent } of text.items) {
      const len = item.str.length
      const a = Math.max(from, start) - start
      const b = Math.min(to, start + len) - start
      if (!len || b <= a) continue
      const [, , , , x, y] = item.transform as number[]
      const full = measure(item.str, font) || 1
      const x1 = x + (item.width * measure(item.str.slice(0, a), font)) / full
      const x2 = x + (item.width * measure(item.str.slice(0, b), font)) / full
      rects.push([x1, y + item.height * descent, x2, y + item.height * ascent])
    }
    return rects
  }

  async function search() {
    const doc = pdfDoc.value
    const query = foldQuery(term.value)
    if (!doc || !query || searching.value) return

    searching.value = true
    try {
      const found: SearchMatch[] = []
      for (let pageNumber = 1; pageNumber <= doc.numPages; pageNumber++) {
        const text = await getPageText(doc, pageNumber)
        for (let at = text.norm.indexOf(query); at !== -1; at = text.norm.indexOf(query, at + query.length)) {
          const from = text.toRaw[at]
          const to = text.toRaw[at + query.length - 1] + 1
          found.push({ page: pageNumber, rects: rectsFor(text, from, to) })
        }
      }
      matches.value = found
      searchedTerm.value = term.value
      current.value = found.length ? 0 : -1
    } finally {
      searching.value = false
    }
  }

  /** Moves to the next (+1) or previous (-1) match, wrapping around the document. */
  function step(delta: number) {
    const total = matches.value.length
    if (total) current.value = (current.value + delta + total) % total
  }

  /** Draws hit boxes onto a rendered page, as percentages so they track the canvas if CSS scales it. */
  function paintHits(pageNumber: number, wrapper: HTMLElement, viewport: PageViewport) {
    wrapper.querySelectorAll('.search-hit').forEach((el) => el.remove())
    matches.value.forEach((match, index) => {
      if (match.page !== pageNumber) return
      match.rects.forEach((rect) => {
        const [ax, ay] = viewport.convertToViewportPoint(rect[0], rect[1])
        const [bx, by] = viewport.convertToViewportPoint(rect[2], rect[3])
        const hit = document.createElement('div')
        hit.className = index === current.value ? 'search-hit is-current' : 'search-hit'
        hit.style.left = `${(Math.min(ax, bx) / viewport.width) * 100}%`
        hit.style.top = `${(Math.min(ay, by) / viewport.height) * 100}%`
        hit.style.width = `${(Math.abs(bx - ax) / viewport.width) * 100}%`
        hit.style.height = `${(Math.abs(by - ay) / viewport.height) * 100}%`
        wrapper.appendChild(hit)
      })
    })

    const currentHit = wrapper.querySelector('.search-hit.is-current')
    if (scrollToCurrent && currentHit) {
      scrollToCurrent = false
      centerInScroller(currentHit as HTMLElement)
    }
  }

  /** Next paint of the current match scrolls it into view (its page may not be rendered yet). */
  function requestScrollToCurrent() {
    scrollToCurrent = true
  }

  return { term, searching, matches, current, status, search, step, reset, paintHits, requestScrollToCurrent }
}
