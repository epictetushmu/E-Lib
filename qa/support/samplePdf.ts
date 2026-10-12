const escape = (s: string): string => s.replace(/\\/g, '\\\\').replace(/\(/g, '\\(').replace(/\)/g, '\\)');

/** Writes numbered PDF objects (in key order) plus a trailer pointing at object 1 as the catalog. */
function serializePdf(objects: Record<number, string>): Buffer {
  let pdf = '%PDF-1.4\n';
  for (const [num, body] of Object.entries(objects)) {
    pdf += `${num} 0 obj\n${body}\nendobj\n`;
  }
  pdf += 'trailer\n<< /Root 1 0 R >>\n';

  return Buffer.from(pdf, 'latin1');
}

/**
 * Generates a minimal-but-valid single-page PDF in memory (a colored cover
 * with title/author text) — used for upload tests instead of a committed
 * binary fixture, since .gitignore blanket-ignores *.pdf (matches the
 * generator in scripts/seed-sample-books.php).
 */
export function buildSamplePdf(title = 'QA Sample PDF', author = 'QA Test Author'): Buffer {
  const content =
    'q\n0.3 0.4 0.7 rg\n0 0 420 600 re f\nQ\n' +
    `BT /F1 22 Tf 1 0 0 1 30 520 Tm 1 1 1 rg (${escape(title)}) Tj ET\n` +
    `BT /F1 14 Tf 1 0 0 1 30 480 Tm 1 1 1 rg (by ${escape(author)}) Tj ET\n` +
    'BT /F1 10 Tf 1 0 0 1 30 60 Tm 1 1 1 rg (Dummy content - generated for E2E tests) Tj ET\n';

  const objects: Record<number, string> = {
    1: '<< /Type /Catalog /Pages 2 0 R >>',
    2: '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
    3:
      '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 420 600] ' +
      '/Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
    4: `<< /Length ${content.length} >>\nstream\n${content}endstream`,
    5: '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
  };

  return serializePdf(objects);
}

/** Where the reader fixture's PDF mentions "library" (1-based page numbers), and how often in total. */
export const READER_PDF = {
  pages: 12,
  /** "library" appears once on pages 2 and 9, twice on page 5 (once as "Library"). */
  libraryPages: [2, 5, 5, 9],
  /** A full line on page 1, handy for selecting text to highlight. */
  page1Line: 'Line 3 of page 1: the quick brown fox jumps over the lazy dog.',
} as const;

/**
 * A multi-page text PDF for reader tests (search, navigation, highlights). Each page is
 * a "Chapter N" heading and numbered lines of plain text, with "library" planted at the
 * spots listed in READER_PDF, so tests can assert exact match counts and pages.
 */
export function buildReaderPdf(): Buffer {
  const objects: Record<number, string> = {
    1: '<< /Type /Catalog /Pages 2 0 R >>',
    3: '<< /Type /Font /Subtype /Type1 /BaseFont /Times-Roman >>',
  };
  const kids: string[] = [];

  for (let page = 1; page <= READER_PDF.pages; page++) {
    const lines = [`Chapter ${page}`];
    for (let i = 1; i <= 20; i++) lines.push(`Line ${i} of page ${page}: the quick brown fox jumps over the lazy dog.`);
    if (page === 2) lines[6] = 'The library opened its doors at dawn.';
    if (page === 5) {
      lines[4] = 'A library is a garden of ideas.';
      lines[15] = 'Return the book to the Library desk.';
    }
    if (page === 9) lines[12] = 'She closed the library for the night.';

    const content = `BT /F1 12 Tf 50 740 Td 16 TL\n${lines.map((l) => `(${escape(l)}) '`).join('\n')}\nET\n`;
    const pageId = 2 + page * 2;
    objects[pageId] =
      `<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents ${pageId + 1} 0 R ` +
      '/Resources << /Font << /F1 3 0 R >> >> >>';
    objects[pageId + 1] = `<< /Length ${content.length} >>\nstream\n${content}endstream`;
    kids.push(`${pageId} 0 R`);
  }
  objects[2] = `<< /Type /Pages /Kids [${kids.join(' ')}] /Count ${READER_PDF.pages} >>`;

  return serializePdf(objects);
}
