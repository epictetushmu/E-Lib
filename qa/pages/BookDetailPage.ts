import type { Locator, Page } from '@playwright/test';
import { BasePage } from './BasePage';
import { HeaderComponent } from './HeaderComponent';

/** /books/:id (frontend/src/views/BookDetail.vue). */
export class BookDetailPage extends BasePage {
  readonly header: HeaderComponent;
  readonly title: Locator;
  readonly previewButton: Locator;
  readonly saveButton: Locator;
  readonly downloadButton: Locator;
  readonly downloadDisabledButton: Locator;
  readonly loginHint: Locator;
  readonly rating: Locator;
  readonly reviewForm: Locator;
  readonly reviewComment: Locator;
  readonly submitReview: Locator;

  constructor(page: Page) {
    super(page);
    this.header = new HeaderComponent(page);
    this.title = page.getByTestId('book-title');
    this.previewButton = page.getByTestId('preview-button');
    this.saveButton = page.getByTestId('save-button');
    this.downloadButton = page.getByTestId('download-button');
    this.downloadDisabledButton = page.getByTestId('download-disabled-button');
    this.loginHint = page.getByTestId('login-hint');
    this.rating = page.getByTestId('book-rating');
    this.reviewForm = page.locator('form.review-form');
    this.reviewComment = this.reviewForm.getByLabel('Comment');
    this.submitReview = this.reviewForm.getByRole('button', { name: 'Submit Review' });
  }

  /** Picks 1–5 stars and submits a review with the given comment. */
  async writeReview(stars: number, comment: string): Promise<void> {
    await this.reviewForm.getByRole('radio', { name: `${stars} star${stars > 1 ? 's' : ''}` }).click();
    await this.reviewComment.fill(comment);
    await this.submitReview.click();
  }

  async openBook(bookId: string): Promise<void> {
    await this.page.goto(`/books/${bookId}`);
    await this.title.waitFor({ state: 'visible' });
  }
}
