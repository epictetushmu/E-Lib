import { expect, test } from '../../fixtures';
import { loginAsAdmin, setBookVisibility } from '../../support/api';
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

  test("a draft's reviews stay hidden, and a reply's parent must be a reply id", async ({
    reader,
    readerBook,
    request,
    adminCredentials,
  }) => {
    const auth = { Authorization: `Bearer ${reader.token}` };
    const me = ((await (await reader.api.get('/api/v1/user/profile', { headers: auth })).json()) as { data: { _id: string } }).data._id;
    expect(
      (await reader.api.post('/api/v1/reviews', { headers: auth, data: { book_id: readerBook.id, rating: 4, comment: 'Solid.' } })).ok(),
    ).toBeTruthy();

    // A malformed parent_id is refused rather than posted at the top of the thread.
    for (const parent_id of [123, { $ne: null }, 'not-an-id']) {
      const bad = await reader.api.post(`/api/v1/reviews/${readerBook.id}/replies`, {
        headers: auth,
        data: { review_user_id: me, parent_id, text: 'Misplaced?' },
      });
      expect(bad.status(), `parent_id ${JSON.stringify(parent_id)}`).toBe(400);
    }
    const reviews = async (as = request) => as.get(`/api/v1/reviews/${readerBook.id}`);
    const thread = ((await (await reviews()).json()) as { data: { replies: unknown[] }[] }).data[0].replies;
    expect(thread).toEqual([]);

    // Once the book is a draft, its reviews and threads are gone for readers (as posting to it already was)…
    const admin = await loginAsAdmin(request, adminCredentials.email, adminCredentials.password);
    await setBookVisibility(request, admin, readerBook.id, { status: 'draft' });
    try {
      expect((await reviews(reader.api)).status()).toBe(404);
      // …but not for admins.
      expect((await request.get(`/api/v1/reviews/${readerBook.id}`, { headers: { Authorization: `Bearer ${admin}` } })).status()).toBe(200);
    } finally {
      await setBookVisibility(request, admin, readerBook.id, { status: 'public' });
    }
  });
});
