import * as pdfjsLib from 'pdfjs-dist'
import workerSrc from 'pdfjs-dist/build/pdf.worker.min.mjs?url'
import type { TextItem } from 'pdfjs-dist/types/src/display/api'

pdfjsLib.GlobalWorkerOptions.workerSrc = workerSrc

export { pdfjsLib }

/** Word/LaTeX exports often leave junk like "Microsoft Word - ch1.docx" or "Untitled" as the document title. */
const JUNK_TITLE = /^(microsoft \w+ - |untitled\b)|\.(docx?|pdf|tex)$/i

/** Valid ISBN-10/13 (checksum checked), digits only, or null. */
export function normalizeIsbn(raw: string): string | null {
  const s = raw.replace(/[^0-9X]/gi, '').toUpperCase()
  if (/^\d{13}$/.test(s) && /^97[89]/.test(s)) {
    const sum = [...s].reduce((acc, d, i) => acc + Number(d) * (i % 2 ? 3 : 1), 0)
    return sum % 10 === 0 ? s : null
  }
  if (/^\d{9}[\dX]$/.test(s)) {
    const sum = [...s].reduce((acc, d, i) => acc + (d === 'X' ? 10 : Number(d)) * (10 - i), 0)
    return sum % 11 === 0 ? s : null
  }
  return null
}

/** First valid ISBN printed after an "ISBN" label (the copyright page), or null. */
export function findIsbn(text: string): string | null {
  for (const m of text.matchAll(/ISBN(?:-1[03])?\s*:?\s*([0-9][0-9 -]{8,16}[0-9Xx])/gi)) {
    const isbn = normalizeIsbn(m[1])
    if (isbn) return isbn
  }
  return null
}

/**
 * The PDF's own Title/Author properties and an ISBN from its copyright page (first pages, or the last ones for
 * some publishers). Empty strings when absent; throws on unreadable/encrypted files.
 */
export async function readPdfDetails(file: File): Promise<{ title: string; author: string; isbn: string }> {
  const task = pdfjsLib.getDocument({ data: await file.arrayBuffer() })
  try {
    const doc = await task.promise
    const { info } = (await doc.getMetadata()) as { info: { Title?: unknown; Author?: unknown } }
    const title = typeof info.Title === 'string' ? info.Title.trim() : ''
    const author = typeof info.Author === 'string' ? info.Author.trim() : ''

    const pages = new Set([1, 2, 3, 4, 5, 6, doc.numPages - 1, doc.numPages].filter((p) => p >= 1 && p <= doc.numPages))
    let isbn: string | null = null
    for (const p of pages) {
      const content = await (await doc.getPage(p)).getTextContent()
      isbn = findIsbn(content.items.map((item) => (item as TextItem).str ?? '').join(' '))
      if (isbn) break
    }
    return { title: JUNK_TITLE.test(title) ? '' : title, author, isbn: isbn ?? '' }
  } finally {
    await task.destroy()
  }
}
