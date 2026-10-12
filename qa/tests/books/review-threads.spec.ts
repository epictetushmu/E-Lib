import { expect, test } from '../../fixtures';
import { BookDetailPage } from '../../pages/BookDetailPage';

// Every review has a nested reply thread. Reviews and replies can be edited and deleted only by whoever
// wrote them — checked both in the UI (the buttons) and against the API, which is what enforces it.
// Deleting a reply that others answered leaves a "[deleted]" placeholder so their answers keep their place.

test.describe('Review threads', () => {
  test('readers answer each other in nested replies; only authors edit or delete their own posts', async ({
    reader,
    otherReader,
    readerBook,
  }) => {
    const author = new BookDetailPage(reader.page);
    await author.openBook(readerBook.id);
    await author.writeReview(4, 'Clear chapters, good exercises.');
    const review = author.review(reader.user.username);
    await expect(review).toContainText('Clear chapters, good exercises.');

    // Another reader answers the review…
    const replier = new BookDetailPage(otherReader.page);
    await replier.openBook(readerBook.id);
    const theirView = replier.review(reader.user.username);
    await expect(theirView.getByTestId('delete-review-button')).toHaveCount(0);
    await replier.replyTo(theirView, 'Did you do the chapter 3 exercises?');
    await expect(replier.replies(theirView)).toHaveCount(1);

    // …and the review's author answers that reply, nested under it.
    await author.openBook(readerBook.id);
    const question = author.replies(review).first();
    await expect(question.getByTestId('edit-reply-button')).toHaveCount(0); // not theirs
    await author.answer(question, 'Yes, all of them.');
    await expect(author.replies(question)).toHaveCount(1);
    await expect(author.replies(question).first()).toContainText('Yes, all of them.');

    // The question's author edits it in place.
    await replier.openBook(readerBook.id);
    const theirQuestion = replier.replies(theirView).first();
    await theirQuestion.getByTestId('edit-reply-button').first().click();
    await theirQuestion.getByLabel('Edit your reply').fill('Did you do the chapter 4 exercises?');
    await theirQuestion.getByRole('button', { name: 'Save reply' }).click();
    await expect(theirQuestion).toContainText('chapter 4');
    await expect(theirQuestion).toContainText('(edited)');

    // The API enforces ownership too: the review's author can't edit or delete someone else's reply.
    const reviews = (await (await reader.api.get(`/api/v1/reviews/${readerBook.id}`)).json()) as {
      data: { replies: { id: string; parent_id: string | null }[] }[];
    };
    const questionId = reviews.data[0].replies.find((r) => r.parent_id === null)!.id;
    const auth = { Authorization: `Bearer ${reader.token}` };
    expect((await reader.api.put(`/api/v1/replies/${questionId}`, { headers: auth, data: { text: 'hijacked' } })).status()).toBe(404);
    expect((await reader.api.delete(`/api/v1/replies/${questionId}`, { headers: auth })).status()).toBe(404);

    // Deleting the answered question keeps a placeholder, so the answer still has its place.
    replier.acceptNextConfirm();
    await theirQuestion.getByTestId('delete-reply-button').first().click();
    await expect(theirQuestion).toContainText('[deleted]');
    await expect(replier.replies(theirQuestion).first()).toContainText('Yes, all of them.');

    // Once its last answer is deleted, the placeholder goes too.
    await author.openBook(readerBook.id);
    author.acceptNextConfirm();
    await author.replies(review).getByTestId('delete-reply-button').click();
    await expect(author.replies(review)).toHaveCount(0);

    // Deleting the review removes it (with whatever thread is left).
    author.acceptNextConfirm();
    await review.getByTestId('delete-review-button').click();
    await expect(review).toHaveCount(0);
  });
});
