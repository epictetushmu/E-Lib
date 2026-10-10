import { expect, test } from '../../fixtures';
import { ReaderPage } from '../../pages/ReaderPage';
import { createAnnotation, listAnnotations } from '../../support/api';
import { READER_PDF } from '../../support/samplePdf';

test.describe('Reader: highlights and notes', () => {
  test('a guest who selects text is asked to log in, and nothing is saved', async ({ page, readerBook }) => {
    const reader = new ReaderPage(page);
    await reader.open(readerBook.id);

    await reader.selectText(READER_PDF.page1Line);

    await expect(reader.loginToAnnotate).toBeVisible();
    await expect(reader.selectionPopover.getByRole('link', { name: 'Log in' })).toHaveAttribute('href', `/login?redirect=%2Fread%2F${readerBook.id}`);
    await expect(reader.highlights).toHaveCount(0);
  });

  test('highlighting a selection draws it on the page, lists it, and keeps it after a reload', async ({ reader, readerBook }) => {
    const view = new ReaderPage(reader.page);
    await view.open(readerBook.id);

    await view.selectText(READER_PDF.page1Line);
    await expect(view.selectionPopover).toBeVisible();
    await view.highlightSelection('Olive');

    await expect(view.selectionPopover).toBeHidden();
    await expect(view.highlights.first()).toBeVisible();
    await expect(view.highlights.first()).toHaveClass(/hl-olive/);

    await view.openNotes();
    await expect(view.noteItems).toHaveCount(1);
    await expect(view.noteItems.first()).toContainText('p. 1');
    await expect(view.noteItems.first()).toContainText('brown fox');

    // Saved to the account, not the browser.
    const saved = await listAnnotations(reader.api, reader.token, readerBook.id);
    expect(saved).toHaveLength(1);
    expect(saved[0]).toMatchObject({ page: 1, color: 'olive' });
    expect(saved[0].quote).toContain('brown fox');

    await reader.page.reload();
    await expect(view.pageLine('Chapter 1')).toBeVisible();
    await expect(view.highlights.first()).toBeVisible();
  });

  test('a note can be added to a highlight from the page, then edited and deleted from the sidebar', async ({ reader, readerBook }) => {
    const view = new ReaderPage(reader.page);
    await view.open(readerBook.id);
    await view.selectText(READER_PDF.page1Line);

    // "Highlight with note" saves the highlight together with its note.
    await view.selectionPopover.getByRole('button', { name: 'Highlight with note' }).click();
    await view.popoverNoteEditor(view.selectionPopover).fill('Classic pangram.');
    await view.selectionPopover.getByRole('button', { name: 'Save highlight' }).click();
    await expect(view.highlights.first()).toBeVisible();

    await view.openNotes();
    const entry = view.noteItems.first();
    await expect(entry).toContainText('Classic pangram.');

    // Edit/delete fade in on hover (on devices without hover they're always shown).
    await entry.hover();
    await expect(view.editButton(entry)).toHaveCSS('opacity', '1');
    await view.editButton(entry).click();
    await view.entryEditor(entry).fill('Every letter of the alphabet.');
    await entry.getByRole('button', { name: 'Save' }).click();
    await expect(entry).toContainText('Every letter of the alphabet.');

    await entry.hover();
    await view.deleteButton(entry).click();
    await expect(view.noteItems).toHaveCount(0);
    await expect(view.highlights).toHaveCount(0);
  });

  test('clicking a highlight lets the reader recolour or delete it', async ({ reader, readerBook }) => {
    const view = new ReaderPage(reader.page);
    await view.open(readerBook.id);
    await view.selectText(READER_PDF.page1Line);
    await view.highlightSelection('Saffron');
    await expect(view.highlights.first()).toHaveClass(/hl-saffron/);

    await view.openHighlight();
    await expect(view.highlightPopover).toBeVisible();
    await view.highlightPopover.getByRole('radio', { name: 'Change to Rose' }).click();
    await expect(view.highlights.first()).toHaveClass(/hl-rose/);

    await view.openHighlight();
    await view.highlightPopover.getByRole('button', { name: 'Delete highlight' }).click();
    await expect(view.highlights).toHaveCount(0);
  });

  test('a page note is saved against the current page and jumps back to it', async ({ reader, readerBook }) => {
    const view = new ReaderPage(reader.page);
    await view.open(readerBook.id);
    await view.goToPage(4);
    await expect.poll(() => view.pageOffsetFromTop(4)).toBe(0);

    await view.openNotes();
    await expect(view.noteOnPageEditor).toHaveAccessibleName('Note on page 4');
    await view.addPageNote('Re-read this chapter.');
    await expect(view.noteItems.first()).toContainText('p. 4');
    await expect(view.noteItems.first()).toContainText('Re-read this chapter.');

    await view.goToPage(1);
    await expect.poll(() => view.pageOffsetFromTop(1)).toBe(0);
    await view.noteItems.first().getByRole('button', { name: 'p. 4' }).click();
    await expect.poll(() => view.pageOffsetFromTop(4)).toBe(0);
  });

  test("one reader never sees another reader's highlights or notes", async ({ reader, otherReader, readerBook }) => {
    // Two sign-ups, two browser logins and a book upload: the heaviest setup in the suite,
    // which the single-threaded dev server (php -S) can't always fit in 30s under parallel load.
    test.slow();
    await createAnnotation(reader.api, reader.token, readerBook.id, {
      page: 1,
      rects: [[60, 690, 400, 704]],
      quote: 'the quick brown fox',
      note: 'Only mine.',
      color: 'aegean',
    });
    await createAnnotation(reader.api, reader.token, readerBook.id, { page: 2, rects: [], quote: '', note: 'Private page note.', color: null });

    const owner = new ReaderPage(reader.page);
    await owner.open(readerBook.id);
    await owner.openNotes();
    await expect(owner.noteItems).toHaveCount(2);
    await expect(owner.highlights).toHaveCount(1);

    const other = new ReaderPage(otherReader.page);
    await other.open(readerBook.id);
    await other.openNotes();
    await expect(other.noteItems).toHaveCount(0);
    await expect(other.highlights).toHaveCount(0);
    await expect(other.notesPanel).not.toContainText('Only mine.');

    // And the API agrees: the other reader's list is empty.
    expect(await listAnnotations(otherReader.api, otherReader.token, readerBook.id)).toEqual([]);
  });

  test('the notes sidebar can be resized by dragging its edge, and remembers the width', async ({ reader, readerBook }) => {
    const view = new ReaderPage(reader.page);
    await view.open(readerBook.id);
    await view.openNotes();
    const initial = await view.notesPanelWidth();

    await view.dragNotesEdge(-150);
    await expect.poll(() => view.notesPanelWidth()).toBe(initial + 150);

    await reader.page.reload();
    await expect(view.pageLine('Chapter 1')).toBeVisible();
    await view.openNotes();
    expect(await view.notesPanelWidth()).toBe(initial + 150);

    await view.notesResizer.dblclick();
    await expect.poll(() => view.notesPanelWidth()).toBe(initial);
  });
});
