import type { Locator, Page } from '@playwright/test';
import { BasePage } from './BasePage';
import { HeaderComponent } from './HeaderComponent';

/** /search (frontend/src/views/SearchResults.vue), fed by GET /api/v1/catalog (title/author + filters). */
export class SearchResultsPage extends BasePage {
  readonly header: HeaderComponent;
  readonly message: Locator;

  constructor(page: Page) {
    super(page);
    this.header = new HeaderComponent(page);
    this.message = page.getByTestId('search-message');
  }

  async openWithTerm(term: string): Promise<void> {
    await this.page.goto(`/search?q=${encodeURIComponent(term)}`);
  }

  cardById(bookId: string): Locator {
    return this.page.getByTestId(`book-card-${bookId}`);
  }
}
