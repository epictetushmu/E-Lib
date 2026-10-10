import { expect, test } from '../../fixtures';
import { BookDetailPage } from '../../pages/BookDetailPage';
import { getBookViaApi, loginViaApi } from '../../support/api';
import { buildTestUser } from '../../support/testData';

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

  test('reviewing again edits the existing review instead of adding a second one', async ({ authenticatedPage, seededBook, request }) => {
    const detail = new BookDetailPage(authenticatedPage);
    await detail.openBook(seededBook.id);

    await detail.writeReview(5, 'Excellent.');
    await expect(authenticatedPage.getByText('Excellent.')).toBeVisible();

    // The form now holds the saved review, ready to edit.
    await expect(detail.reviewForm.getByRole('heading', { name: 'Edit Your Review' })).toBeVisible();
    await expect(detail.reviewComment).toHaveValue('Excellent.');

    await detail.writeReview(2, 'Second look: too dense.');
    await expect(authenticatedPage.getByText('Your review has been updated')).toBeVisible();
    await expect(authenticatedPage.getByText('Second look: too dense.')).toBeVisible();
    await expect(authenticatedPage.getByText('Excellent.')).toHaveCount(0);

    expect(await getBookViaApi(request, seededBook.id)).toMatchObject({ average_rating: 2, review_count: 1 });
  });

  test('the rating is the average of every user\'s review', async ({ authenticatedPage, seededBook, request }) => {
    const detail = new BookDetailPage(authenticatedPage);
    await detail.openBook(seededBook.id);

    await detail.writeReview(5, 'Excellent.');
    await expect(authenticatedPage.getByText('Excellent.')).toBeVisible();
    // A second reader, reviewing through the API (only signup + login needed, no browser).
    const other = buildTestUser();
    expect((await request.post('/api/v1/signup', { data: other })).ok()).toBeTruthy();
    const { token } = await loginViaApi(request, other.email, other.password);
    const response = await request.post('/api/v1/reviews', {
      headers: { Authorization: `Bearer ${token}` },
      data: { book_id: seededBook.id, rating: 2, comment: 'Too dense.' },
    });
    expect(response.ok()).toBeTruthy();

    expect(await getBookViaApi(request, seededBook.id)).toMatchObject({ average_rating: 3.5, review_count: 2 });
  });
});
