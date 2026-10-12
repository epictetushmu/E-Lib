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

  /** "Keeps their … private." under the friend's name; absent when they share everything. */
  get privateNote(): Locator {
    return this.page.getByTestId('friend-private');
  }
}
