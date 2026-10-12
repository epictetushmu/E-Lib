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

  // Lending panel (lent PDFs and physical books)
  readonly loanPanel: Locator;
  readonly borrowButton: Locator;
  readonly reserveButton: Locator;
  readonly readLoanButton: Locator;
  readonly renewButton: Locator;
  readonly returnButton: Locator;
  readonly startReadingButton: Locator;
  readonly cancelReservationButton: Locator;
  readonly joinWaitlistButton: Locator;
  readonly leaveWaitlistButton: Locator;
  readonly checkoutEmail: Locator;
  readonly checkoutButton: Locator;

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
    // 'Submit Review' for a new review, 'Update Review' when editing the user's existing one.
    this.submitReview = this.reviewForm.getByRole('button', { name: /^(Submit|Update) Review$/ });

    this.loanPanel = page.getByTestId('loan-panel');
    this.borrowButton = page.getByTestId('borrow-button');
    this.reserveButton = page.getByTestId('reserve-button');
    this.readLoanButton = page.getByTestId('read-loan-button');
    this.renewButton = page.getByTestId('renew-button');
    this.returnButton = page.getByTestId('return-button');
    this.startReadingButton = page.getByTestId('start-reading-button');
    this.cancelReservationButton = page.getByTestId('cancel-reservation-button');
    this.joinWaitlistButton = page.getByTestId('join-waitlist-button');
    this.leaveWaitlistButton = page.getByTestId('leave-waitlist-button');
    this.checkoutEmail = page.getByTestId('checkout-email');
    this.checkoutButton = page.getByTestId('checkout-form').getByRole('button', { name: 'Check out' });
  }

  /**
   * The loan panel's headline for where the viewer stands with the book:
   * 'open' (copies free), 'full' (all out), 'queued' (on the waitlist), 'held' (a copy kept for them),
   * 'out' (they have it) or 'reference' (physical, not lendable).
   */
  loanState(state: 'open' | 'full' | 'queued' | 'held' | 'out' | 'reference'): Locator {
    return this.page.getByTestId(`loan-state-${state}`);
  }

  /** A physical book's call number, drawn as a spine label. */
  spineLabel(callNumber: string): Locator {
    return this.page.getByLabel(`Shelf ${callNumber}`);
  }

  /** A review in the list, by its author's username. */
  review(username: string): Locator {
    return this.page.getByTestId('review').filter({ has: this.page.getByText(username, { exact: true }) });
  }

  /** Every reply under a review or a reply, at any depth. */
  replies(parent: Locator): Locator {
    return parent.getByTestId('reply');
  }

  /** Answers the review itself. */
  async replyTo(review: Locator, text: string): Promise<void> {
    await review.getByTestId('review-reply-button').click();
    await review.getByTestId('review-reply-input').fill(text);
    await review.getByTestId('review-post-reply-button').click();
  }

  /** Answers a reply, nesting the answer under it. */
  async answer(reply: Locator, text: string): Promise<void> {
    await reply.getByTestId('reply-button').first().click();
    await reply.getByTestId('reply-input').fill(text);
    await reply.getByTestId('post-reply-button').click();
  }

  /** Accepts the next confirm() dialog (deletes ask first). */
  acceptNextConfirm(): void {
    this.page.once('dialog', (dialog) => dialog.accept());
  }

  /** Picks 1–5 stars and submits (or, if the user already reviewed the book, updates) a review with the given comment. */
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
