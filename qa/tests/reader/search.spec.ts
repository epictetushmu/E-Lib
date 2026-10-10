import { expect, test } from '../../fixtures';
import { ReaderPage } from '../../pages/ReaderPage';
import { READER_PDF } from '../../support/samplePdf';

// In-book search used to stop at the first page containing the term, scroll to
// the top of that page and do nothing on a second Enter: no count, no
// highlighting. It now finds every match across the book, counts them, and
// steps through them with highlights on the page.

const total = READER_PDF.libraryPages.length;

test.describe('Reader: search in the book', () => {
  test('counts every match across the book and steps through them', async ({ page, readerBook }) => {
    const reader = new ReaderPage(page);
    await reader.open(readerBook.id);

    await reader.search('library');

    await expect(reader.searchStatus).toHaveText(`1 of ${total}`);
    await expect(reader.currentPageInput).toHaveValue(String(READER_PDF.libraryPages[0]));
    await expect(reader.currentSearchHit).toBeInViewport();

    // Enter again goes to the next match; the button does the same.
    await reader.searchInput.press('Enter');
    await expect(reader.searchStatus).toHaveText(`2 of ${total}`);
    await expect(reader.currentPageInput).toHaveValue(String(READER_PDF.libraryPages[1]));

    await reader.nextMatch.click();
    await expect(reader.searchStatus).toHaveText(`3 of ${total}`);
    // Two matches on that page, both highlighted; only one is the current one.
    await expect(reader.pageElement(READER_PDF.libraryPages[2]).locator('.search-hit')).toHaveCount(2);
    await expect(reader.currentSearchHit).toHaveCount(1);

    // Shift+Enter steps back.
    await reader.searchInput.press('Shift+Enter');
    await expect(reader.searchStatus).toHaveText(`2 of ${total}`);
  });

  test('wraps from the first match back to the last', async ({ page, readerBook }) => {
    const reader = new ReaderPage(page);
    await reader.open(readerBook.id);

    await reader.search('library');
    await expect(reader.searchStatus).toHaveText(`1 of ${total}`);

    await reader.previousMatch.click();

    await expect(reader.searchStatus).toHaveText(`${total} of ${total}`);
    await expect(reader.currentPageInput).toHaveValue(String(READER_PDF.libraryPages[total - 1]));
    await expect(reader.currentSearchHit).toBeInViewport();
  });

  test('ignores case and extra spaces', async ({ page, readerBook }) => {
    const reader = new ReaderPage(page);
    await reader.open(readerBook.id);

    await reader.search('  LIBRARY  ');

    await expect(reader.searchStatus).toHaveText(`1 of ${total}`);
  });

  test('says when nothing matches, and clears when the term changes', async ({ page, readerBook }) => {
    const reader = new ReaderPage(page);
    await reader.open(readerBook.id);

    await reader.search('xylophone');
    await expect(reader.searchStatus).toHaveText('No matches');
    await expect(reader.searchHits).toHaveCount(0);

    await reader.searchInput.fill('library');
    await expect(reader.searchStatus).toHaveText('');
  });

  test('Ctrl+F goes to the book search instead of the browser find bar', async ({ page, readerBook }) => {
    const reader = new ReaderPage(page);
    await reader.open(readerBook.id);

    await page.keyboard.press('ControlOrMeta+f');

    await expect(reader.searchInput).toBeFocused();
  });
});
