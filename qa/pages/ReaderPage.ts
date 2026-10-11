import { expect, type Locator, type Page } from '@playwright/test';
import { BasePage } from './BasePage';

/**
 * /read/:id (frontend/src/views/Reader.vue): the PDF reader, its in-book search,
 * highlights, and the notes sidebar.
 *
 * Pages are drawn on canvas; pdf.js lays a transparent, selectable text layer over
 * each one (`.textLayer span`), and search hits / highlights are absolutely
 * positioned overlays (`.search-hit`, `.user-highlight`) — those DOM details live here.
 */
export class ReaderPage extends BasePage {
  readonly currentPageInput: Locator;
  readonly pageCount: Locator;
  readonly zoomIn: Locator;
  readonly rotate: Locator;
  readonly searchInput: Locator;
  readonly searchStatus: Locator;
  readonly nextMatch: Locator;
  readonly previousMatch: Locator;
  readonly searchHits: Locator;
  readonly currentSearchHit: Locator;
  readonly highlights: Locator;
  readonly notesToggle: Locator;
  readonly notesPanel: Locator;
  readonly notesResizer: Locator;
  readonly noteItems: Locator;
  readonly noteOnPageEditor: Locator;
  readonly addNoteButton: Locator;
  readonly loginToAnnotate: Locator;
  readonly selectionPopover: Locator;
  readonly highlightPopover: Locator;
  readonly footer: Locator;
  readonly printButton: Locator;
  readonly errorMessage: Locator;

  constructor(page: Page) {
    super(page);
    this.currentPageInput = page.getByLabel('Page', { exact: true });
    this.pageCount = page.getByTestId('page-count');
    this.zoomIn = page.getByRole('button', { name: 'Zoom in' });
    this.rotate = page.getByRole('button', { name: 'Rotate pages' });
    this.searchInput = page.getByLabel('Search in document');
    this.searchStatus = page.getByTestId('search-status');
    this.nextMatch = page.getByRole('button', { name: 'Next match' });
    this.previousMatch = page.getByRole('button', { name: 'Previous match' });
    this.searchHits = page.locator('.search-hit');
    this.currentSearchHit = page.locator('.search-hit.is-current');
    this.highlights = page.locator('.user-highlight');
    this.notesToggle = page.getByTestId('notes-toggle');
    this.notesPanel = page.getByRole('complementary', { name: 'Notes' });
    this.notesResizer = page.getByRole('separator', { name: 'Resize notes panel' });
    this.noteItems = this.notesPanel.getByTestId('note-item');
    this.noteOnPageEditor = this.notesPanel.getByLabel(/^Note on page \d+$/);
    this.addNoteButton = this.notesPanel.getByRole('button', { name: 'Add note' });
    this.loginToAnnotate = page.getByText('Log in to highlight passages and keep private notes.');
    this.selectionPopover = page.getByRole('dialog', { name: 'Highlight selection' });
    this.highlightPopover = page.getByRole('dialog', { name: 'Edit highlight' });
    this.footer = page.locator('footer.site-footer');
    this.printButton = page.getByRole('button', { name: 'Print' });
    this.errorMessage = page.locator('.reader-error');
  }

  /** Opens the book and waits until the first page's text layer exists (the reader is ready). */
  async open(bookId: string): Promise<void> {
    await this.page.goto(`/read/${bookId}`);
    await expect(this.pageLine('Chapter 1')).toBeVisible({ timeout: 20_000 });
  }

  /** A rendered page (or its placeholder while not yet drawn) by number. */
  pageElement(pageNumber: number): Locator {
    return this.page.locator(`.pdf-container > [data-page-number="${pageNumber}"]`);
  }

  /** A run of selectable text on the page, by its content. */
  pageLine(text: string): Locator {
    return this.page.locator('.textLayer span', { hasText: text }).first();
  }

  async goToPage(pageNumber: number): Promise<void> {
    await this.currentPageInput.fill(String(pageNumber));
    await this.currentPageInput.press('Enter');
  }

  /**
   * Distance from the top of the page area to the top of a page, in px (0 = page starts at the top),
   * or null if it isn't there. Looked up fresh each call: a placeholder is swapped for the drawn page
   * once it renders, so a held element handle could be stale.
   */
  async pageOffsetFromTop(pageNumber: number): Promise<number | null> {
    return this.page.evaluate((n) => {
      const container = document.querySelector('.pdf-container');
      const el = container?.querySelector(`[data-page-number="${n}"]`);
      if (!container || !el) return null;
      // Truncated, not rounded: pages have fractional heights and WebKit scrolls in whole pixels,
      // so a page scrolled to the top can sit up to 1px off. `|| 0` turns -0 into 0 for toBe(0).
      return Math.trunc(el.getBoundingClientRect().top - container.getBoundingClientRect().top) || 0;
    }, pageNumber);
  }

  /** Whether the browser window itself (not just the page area) can scroll. */
  async windowScrolls(): Promise<boolean> {
    return this.page.evaluate(() => document.documentElement.scrollHeight > window.innerHeight + 1);
  }

  async search(term: string): Promise<void> {
    await this.searchInput.fill(term);
    await this.searchInput.press('Enter');
  }

  /**
   * Drags across a line of page text with the mouse, from `from` to `to`
   * (fractions of the line's width) — a real text selection, as a reader makes one.
   */
  async selectText(lineText: string, from = 0.3, to = 0.9): Promise<void> {
    const line = this.pageLine(lineText);
    await line.scrollIntoViewIfNeeded();
    const box = await line.boundingBox();
    if (!box) throw new Error(`Text "${lineText}" is not on screen`);
    const y = box.y + box.height / 2;
    await this.page.mouse.move(box.x + box.width * from, y);
    await this.page.mouse.down();
    await this.page.mouse.move(box.x + box.width * to, y, { steps: 8 });
    await this.page.mouse.up();
  }

  /** Highlights the current selection in a colour from the popover (Saffron, Olive, Aegean, Rose). */
  async highlightSelection(colour: string): Promise<void> {
    await this.selectionPopover.getByRole('radio', { name: `Highlight in ${colour}` }).click();
  }

  /** Clicks a saved highlight on the page, which opens its edit popover. */
  async openHighlight(index = 0): Promise<void> {
    const mark = this.highlights.nth(index);
    const box = await mark.boundingBox();
    if (!box) throw new Error('Highlight is not on screen');
    await this.page.mouse.click(box.x + Math.min(12, box.width / 2), box.y + box.height / 2);
  }

  /** The note editor inside a highlight popover or an entry being edited. */
  noteEditor(scope: Locator): Locator {
    return scope.getByLabel('Note', { exact: true });
  }

  async openNotes(): Promise<void> {
    if ((await this.notesToggle.getAttribute('aria-pressed')) !== 'true') await this.notesToggle.click();
    await expect(this.notesPanel).toBeVisible();
  }

  /** Writes and saves a note on the current page from the sidebar. */
  async addPageNote(text: string): Promise<void> {
    await this.noteOnPageEditor.fill(text);
    await this.addNoteButton.click();
  }

  /** The note entry's rendered note (Markdown, maths, code, diagrams). */
  renderedNote(item: Locator): Locator {
    return item.locator('.note-content');
  }

  /** Edit/delete buttons appear on hover (or focus) of an entry. */
  editButton(item: Locator): Locator {
    return item.getByRole('button', { name: /^(Edit note|Add note to highlight|Edit note on highlight)$/ });
  }

  deleteButton(item: Locator): Locator {
    return item.getByRole('button', { name: /^Delete (note|highlight)$/ });
  }

  /** The note-editor toolbar button (Code, Equation, Diagram) next to an editor. */
  insertButton(scope: Locator, name: 'Code' | 'Equation' | 'Diagram'): Locator {
    return scope.getByRole('group', { name: 'Insert' }).getByRole('button', { name });
  }

  previewToggle(scope: Locator): Locator {
    return scope.getByRole('button', { name: /^(Preview|Edit)$/ });
  }

  async notesPanelWidth(): Promise<number> {
    return this.notesPanel.evaluate((el) => Math.round(el.getBoundingClientRect().width));
  }

  /** Drags the sidebar's left edge horizontally by `dx` px (negative widens it). */
  async dragNotesEdge(dx: number): Promise<void> {
    const box = await this.notesResizer.boundingBox();
    if (!box) throw new Error('Notes resizer is not on screen');
    const x = box.x + box.width / 2;
    const y = box.y + 200;
    await this.page.mouse.move(x, y);
    await this.page.mouse.down();
    await this.page.mouse.move(x + dx, y, { steps: 10 });
    await this.page.mouse.up();
  }
}
