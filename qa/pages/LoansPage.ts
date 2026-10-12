import type { Locator, Page } from '@playwright/test';
import { BasePage } from './BasePage';

/**
 * /admin/loans (frontend/src/views/admin/Loans.vue): every loan, reservation and its history,
 * with the librarian's desk actions (Picked up, Cancel, Check in, Renew) per row.
 */
export class LoansPage extends BasePage {
  readonly heading: Locator;
  readonly searchInput: Locator;
  readonly rows: Locator;

  constructor(page: Page) {
    super(page);
    this.heading = page.getByRole('heading', { name: 'Loans', level: 1 });
    this.searchInput = page.getByRole('searchbox', { name: 'Find by book or reader' });
    this.rows = page.getByTestId('loan-row');
  }

  async open(): Promise<void> {
    await this.page.goto('/admin/loans');
  }

  /** Filter pills: 'All', 'Current', 'Awaiting pickup', 'Overdue', 'Past' (each followed by its count). */
  filter(label: string): Locator {
    return this.page.getByRole('button', { name: new RegExp(`^${label} \\d+$`) });
  }

  /** Narrows the table to one reader's loans (test users have unique emails), across every filter. */
  async showReader(email: string): Promise<void> {
    await this.filter('All').click();
    await this.searchInput.fill(email);
  }

  rowFor(text: string): Locator {
    return this.rows.filter({ hasText: text });
  }

  action(row: Locator, name: 'Picked up' | 'Cancel' | 'Check in' | 'Renew'): Locator {
    return row.getByRole('button', { name, exact: true });
  }
}
