import { expect, test } from '../../fixtures';
import { EquationDialog } from '../../pages/EquationDialog';
import { ReaderPage } from '../../pages/ReaderPage';

test.describe('Reader: code and maths in notes', () => {
  test('a code block in a note is shown highlighted', async ({ reader, readerBook }) => {
    const view = new ReaderPage(reader.page);
    await view.open(readerBook.id);
    await view.openNotes();

    await view.addPageNote('Binary search:\n\n```python\ndef search(xs, target):\n    return -1\n```');

    const note = view.renderedNote(view.noteItems.first());
    await expect(note.locator('pre code')).toContainText('def search(xs, target):');
    await expect(note.locator('.hljs-keyword').first()).toHaveText('def');
  });

  test('HTML typed into a note is shown as text, never run', async ({ reader, readerBook }) => {
    const view = new ReaderPage(reader.page);
    await view.open(readerBook.id);
    await view.openNotes();

    await view.addPageNote('<img src=x onerror="document.title=\'pwned\'"> stays text');

    const note = view.renderedNote(view.noteItems.first());
    await expect(note).toContainText('<img src=x');
    await expect(note.locator('img')).toHaveCount(0);
    await expect(reader.page).not.toHaveTitle('pwned');
  });

  test('the equation editor builds maths visually and the note renders it', async ({ reader, readerBook }) => {
    const view = new ReaderPage(reader.page);
    const equation = new EquationDialog(reader.page);
    await view.open(readerBook.id);
    await view.openNotes();

    await view.noteOnPageEditor.fill('Roots: ');
    await view.insertButton(view.notesPanel, 'Equation').click();
    await expect(equation.dialog).toBeVisible();
    await expect(equation.field).toBeFocused();

    // Typed as it looks: "1/2" becomes a fraction; palette keys insert symbols.
    await equation.type('x=1/2');
    await expect.poll(() => equation.latex()).toBe('x=\\frac12');
    await reader.page.keyboard.press('ArrowRight');
    await equation.key('Plus or minus').click();
    await equation.key('pi').click();
    await expect.poll(() => equation.latex()).toBe('x=\\frac12\\pm\\pi');

    await equation.insert.click();
    await expect(equation.dialog).toBeHidden();
    await expect(view.noteOnPageEditor).toHaveValue('Roots: \n$$\nx=\\frac12\\pm\\pi\n$$');

    await view.previewToggle(view.notesPanel).click();
    await expect(view.notesPanel.locator('.note-editor-preview .katex-display')).toBeVisible();

    await view.previewToggle(view.notesPanel).click();
    await view.addNoteButton.click();
    await expect(view.renderedNote(view.noteItems.first()).locator('.katex-display')).toBeVisible();
  });

  test('putting the cursor in an equation and pressing Equation edits that equation', async ({ reader, readerBook }) => {
    const view = new ReaderPage(reader.page);
    const equation = new EquationDialog(reader.page);
    await view.open(readerBook.id);
    await view.openNotes();

    await view.noteOnPageEditor.fill('Area is $\\pi r^2$ here.');
    // Cursor inside the $…$
    await view.noteOnPageEditor.evaluate((el: HTMLTextAreaElement) => el.setSelectionRange(11, 11));
    await view.insertButton(view.notesPanel, 'Equation').click();

    await expect(equation.dialog).toHaveAccessibleName('Edit equation');
    await expect.poll(() => equation.latex()).toBe('\\pi r^2');
    await equation.field.focus();
    await reader.page.keyboard.press('End');
    await equation.type('+1');
    await equation.insert.click();

    await expect(view.noteOnPageEditor).toHaveValue('Area is $\\pi r^2+1$ here.');
  });

  test('Esc closes only the equation editor, not the highlight popover under it', async ({ reader, readerBook }) => {
    const view = new ReaderPage(reader.page);
    const equation = new EquationDialog(reader.page);
    await view.open(readerBook.id);
    await view.selectText('Line 3 of page 1: the quick brown fox jumps over the lazy dog.');
    await view.selectionPopover.getByRole('button', { name: 'Highlight with note' }).click();

    await view.insertButton(view.selectionPopover, 'Equation').click();
    await expect(equation.dialog).toBeVisible();
    await reader.page.keyboard.press('Escape');

    await expect(equation.dialog).toBeHidden();
    await expect(view.selectionPopover).toBeVisible();
  });
});
