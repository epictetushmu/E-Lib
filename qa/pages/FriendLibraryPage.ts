import type { Locator, Page } from '@playwright/test';
import { BasePage } from './BasePage';

/** /friends/:id (frontend/src/views/FriendLibrary.vue) — what a friend shares. */
export class FriendLibraryPage extends BasePage {
  readonly reviews: Locator;

  constructor(page: Page) {
    super(page);
    this.reviews = page.getByTestId('friend-reviews').getByRole('listitem');
  }

  async open(userId: string): Promise<void> {
    await this.page.goto(`/friends/${userId}`);
  }

  /** A book card in one of the book sections; absent when that section is empty or not shared. */
  book(section: 'saved' | 'loans' | 'downloads', bookId: string): Locator {
    return this.page.getByTestId(`friend-${section}`).getByTestId(`book-card-${bookId}`);
  }

  /** One "<name> doesn't share this." line per section the friend keeps private. */
  notShared(username: string): Locator {
    return this.page.getByText(`${username} doesn't share this.`);
  }
}
