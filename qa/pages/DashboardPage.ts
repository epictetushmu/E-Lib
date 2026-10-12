import type { Locator, Page } from '@playwright/test';
import { BasePage } from './BasePage';
import { HeaderComponent } from './HeaderComponent';

/**
 * /admin — the admin "Manage Books" console (frontend/src/views/admin/Dashboard.vue),
 * fed by GET /api/v1/books (all statuses). Guarded client-side by the `requireAdmin`
 * router guard (frontend/src/router/guards.ts): an unauthenticated visitor is sent to
 * /login, an authenticated non-admin is bounced to '/'.
 */
export class DashboardPage extends BasePage {
  readonly header: HeaderComponent;
  readonly heading: Locator;
  readonly table: Locator;
  readonly searchInput: Locator;
  readonly noMatchMessage: Locator;
  readonly showAllBooksButton: Locator;
  readonly serverLogsLink: Locator;
  readonly loansLink: Locator;
  readonly addPhysicalButton: Locator;
  readonly addPhysicalDialog: Locator;

  readonly editDialog: Locator;
  readonly downloadableCheckbox: Locator;
  readonly saveEditButton: Locator;

  constructor(page: Page) {
    super(page);
    this.header = new HeaderComponent(page);
    this.heading = page.getByTestId('dashboard-heading');
    this.table = page.getByTestId('books-table');
    this.searchInput = page.getByRole('searchbox', { name: 'Find a book by title or author' });
    this.noMatchMessage = page.getByText('No books match these filters.');
    this.showAllBooksButton = page.getByRole('button', { name: 'Show all books' });
    this.serverLogsLink = page.getByRole('link', { name: 'Server logs' });
    this.loansLink = page.getByTestId('loans-link');
    this.addPhysicalButton = page.getByTestId('add-physical-button');
    // The edit modal doubles as the "Add physical book" form.
    this.addPhysicalDialog = page.getByRole('dialog', { name: 'Add physical book' });

    // Edit modal — only one is ever open at a time, so these don't need a book id.
    this.editDialog = page.getByRole('dialog', { name: 'Edit Book' });
    this.downloadableCheckbox = page.getByTestId('edit-downloadable-checkbox');
    this.saveEditButton = page.getByTestId('edit-save-button');
  }

  async open(): Promise<void> {
    await this.page.goto('/admin');
  }

  /** Filter pills above the table: 'All', 'Public', 'Drafts', 'Featured', 'Downloads off' (each followed by its count). */
  filter(label: string): Locator {
    return this.page.getByRole('button', { name: new RegExp(`^${label} \\d+$`) });
  }

  /** A book's spine on the shelf overview (decorative, so it has no accessible name — found by test id). */
  spineById(bookId: string): Locator {
    return this.page.getByTestId(`shelf-spine-${bookId}`);
  }

  /** A text field in the edit modal, by its label ('Title', 'Author', 'Description', 'ISBN', 'Categories'). */
  editField(label: string): Locator {
    return this.editDialog.getByLabel(label, { exact: true });
  }

  rowById(bookId: string): Locator {
    return this.page.getByTestId(`book-row-${bookId}`);
  }

  statusToggle(bookId: string): Locator {
    return this.page.getByTestId(`status-toggle-${bookId}`);
  }

  featuredToggle(bookId: string): Locator {
    return this.page.getByTestId(`featured-toggle-${bookId}`);
  }

  editButton(bookId: string): Locator {
    return this.page.getByTestId(`edit-book-${bookId}`);
  }

  deleteButton(bookId: string): Locator {
    return this.page.getByTestId(`delete-book-${bookId}`);
  }
}
