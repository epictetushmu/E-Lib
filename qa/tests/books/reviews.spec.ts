import { expect, test } from '../../fixtures';
import { BookDetailPage } from '../../pages/BookDetailPage';
import { getBookViaApi } from '../../support/api';

// Was a crash: adding a review recalculated the book's average rating with
// array_column() on the reviews exactly as MongoDB returned them (a BSONArray,
// not a PHP array), which threw a fatal error — the review request failed.

test.describe('Book reviews', () => {
  test('a logged-in reader can review a book, and its rating updates', async ({ authenticatedPage, seededBook, request }) => {
    const detail = new BookDetailPage(authenticatedPage);
    await detail.openBook(seededBook.id);

    await detail.writeReview(4, 'Clear and well paced.');

    await expect(authenticatedPage.getByText('Your review has been submitted')).toBeVisible();
    await expect(detail.reviewForm.getByRole('alert')).toHaveCount(0);
    await expect(authenticatedPage.getByText('Clear and well paced.')).toBeVisible();

    expect(await getBookViaApi(request, seededBook.id)).toMatchObject({ average_rating: 4, review_count: 1 });
    await authenticatedPage.reload();
    await expect(detail.rating).toHaveText('(4)');
  });

  test('the rating is the average of every review', async ({ authenticatedPage, seededBook, request }) => {
    const detail = new BookDetailPage(authenticatedPage);
    await detail.openBook(seededBook.id);

    await detail.writeReview(5, 'Excellent.');
    await expect(authenticatedPage.getByText('Excellent.')).toBeVisible();
    await detail.writeReview(2, 'Second look: too dense.');
    await expect(authenticatedPage.getByText('Second look: too dense.')).toBeVisible();

    expect(await getBookViaApi(request, seededBook.id)).toMatchObject({ average_rating: 3.5, review_count: 2 });
  });
});
