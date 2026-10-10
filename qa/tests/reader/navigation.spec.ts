import { expect, test } from '../../fixtures';
import { ReaderPage } from '../../pages/ReaderPage';
import { READER_PDF } from '../../support/samplePdf';

test.describe('Reader: layout and navigation', () => {
  test('fills the window: no footer, and only the pages scroll', async ({ page, readerBook }) => {
    const reader = new ReaderPage(page);
    await reader.open(readerBook.id);

    await expect(reader.pageCount).toHaveText(`of ${READER_PDF.pages}`);
    // Was a bug: the reader sat above the site footer, so the window scrolled
    // as well as the pages (two nested scroll areas).
    await expect(reader.footer).toHaveCount(0);
    expect(await reader.windowScrolls()).toBe(false);
  });

  test('jumps to a page typed into the page box', async ({ page, readerBook }) => {
    const reader = new ReaderPage(page);
    await reader.open(readerBook.id);

    await reader.goToPage(8);

    await expect.poll(() => reader.pageOffsetFromTop(8)).toBe(0);
    await expect(reader.pageLine('Chapter 8')).toBeVisible();
    expect(await reader.windowScrolls()).toBe(false);
  });

  // Was a bug: rotating or zooming rebuilt every page but kept the old pixel
  // scroll offset, which lands on a different page once page sizes change.
  test('stays on the same page when rotating', async ({ page, readerBook }) => {
    const reader = new ReaderPage(page);
    await reader.open(readerBook.id);
    await reader.goToPage(7);
    await expect.poll(() => reader.pageOffsetFromTop(7)).toBe(0);

    await reader.rotate.click();

    await expect(reader.currentPageInput).toHaveValue('7');
    await expect.poll(() => reader.pageOffsetFromTop(7)).toBe(0);
  });

  // Was a bug: the selectable text layer stayed unrotated over the rotated page,
  // so selections (and the highlights made from them) landed on the wrong text.
  test('the selectable text turns with the page', async ({ page, readerBook }) => {
    const reader = new ReaderPage(page);
    await reader.open(readerBook.id);

    await reader.rotate.click();

    const line = reader.pageLine(READER_PDF.page1Line);
    await expect(line).toBeVisible();
    // Both are on screen (toBeVisible above), so neither box is null.
    const pageBox = (await reader.pageElement(1).boundingBox())!;
    const lineBox = (await line.boundingBox())!;
    expect(lineBox.height).toBeGreaterThan(lineBox.width);
    expect(lineBox.x).toBeGreaterThanOrEqual(pageBox.x);
    expect(lineBox.x + lineBox.width).toBeLessThanOrEqual(pageBox.x + pageBox.width);
    expect(lineBox.y).toBeGreaterThanOrEqual(pageBox.y);
    expect(lineBox.y + lineBox.height).toBeLessThanOrEqual(pageBox.y + pageBox.height);
  });

  test('stays on the same page when zooming', async ({ page, readerBook }) => {
    const reader = new ReaderPage(page);
    await reader.open(readerBook.id);
    await reader.goToPage(6);
    await expect.poll(() => reader.pageOffsetFromTop(6)).toBe(0);

    await reader.zoomIn.click();

    await expect(reader.currentPageInput).toHaveValue('6');
    await expect.poll(() => reader.pageOffsetFromTop(6)).toBe(0);
  });
});
