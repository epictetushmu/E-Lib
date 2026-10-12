function getNum(key: string, parse: (raw: string) => number): number | null {
  try {
    const raw = localStorage.getItem(key)
    if (!raw) return null
    const n = parse(raw)
    return Number.isNaN(n) ? null : n
  } catch {
    return null
  }
}

function setNum(key: string, value: number) {
  try {
    // Storage may be unavailable (private browsing); the value just won't persist.
    localStorage.setItem(key, String(value))
  } catch {
    // ignore
  }
}

const PANEL_WIDTH_KEY = 'elib_notes_panel_width'

/** Notes panel width, remembered across books in this browser. */
export const getSavedPanelWidth = () => getNum(PANEL_WIDTH_KEY, Number)
export const setSavedPanelWidth = (width: number) => setNum(PANEL_WIDTH_KEY, width)

/** Reading-progress and zoom persisted per book in localStorage. */
export function useReaderPersistence(bookId: string) {
  const keyFor = (prefix: string) => `elib_pdf_${prefix}_${bookId}`
  return {
    getSavedPage: () => getNum(keyFor('page'), (raw) => parseInt(raw, 10)),
    setSavedPage: (page: number) => setNum(keyFor('page'), page),
    getSavedZoom: () => getNum(keyFor('zoom'), parseFloat),
    setSavedZoom: (zoom: number) => setNum(keyFor('zoom'), zoom),
  }
}
