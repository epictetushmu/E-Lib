import { expect, test } from '../../fixtures';
import { createAnnotation, deleteAllAnnotations } from '../../support/api';
import { ReaderPage } from '../../pages/ReaderPage';

// Friends see each other's notes in the reader only once the author shares them (off by default:
// notes were written as private). Shared notes are read-only, drawn as underlines the reader can hide.

test.describe("Friends' notes in the reader", () => {
  test('appear only once shared, open from the page, and can be filtered by friend', async ({
    reader,
    otherReader,
    borrower,
    readerBook,
  }) => {
    // Three sign-ups, three browser logins and a book upload, like the other multi-reader reader specs.
    test.slow();
    const author = reader;
    const friend = otherReader;
    const secondAuthor = borrower;
    const auth = (token: string) => ({ Authorization: `Bearer ${token}` });
    const userId = async (s: typeof author) =>
      ((await (await s.api.get('/api/v1/user/profile', { headers: auth(s.token) })).json()) as { data: { _id: string } }).data._id;
    const friendsNotes = async () =>
      ((await (await friend.api.get(`/api/v1/books/${readerBook.id}/annotations/friends`, { headers: auth(friend.token) })).json()) as {
        data: unknown[];
      }).data;

    // Both authors become friends with the reader (the UI flow has its own spec).
    const authorId = await userId(author);
    const secondAuthorId = await userId(secondAuthor);
    for (const a of [author, secondAuthor]) {
      expect((await a.api.post('/api/v1/friends', { headers: auth(a.token), data: { email: friend.user.email } })).ok()).toBeTruthy();
    }
    for (const id of [authorId, secondAuthorId]) {
      expect((await friend.api.post(`/api/v1/friends/${id}/accept`, { headers: auth(friend.token) })).ok()).toBeTruthy();
    }

    await createAnnotation(author.api, author.token, readerBook.id, {
      page: 1,
      rects: [[60, 690, 400, 704]],
      quote: 'the quick brown fox',
      note: 'Remember this for the exam.',
      color: 'aegean',
    });
    await createAnnotation(author.api, author.token, readerBook.id, { page: 2, rects: [], quote: '', note: 'Page two matters.', color: null });
    await createAnnotation(secondAuthor.api, secondAuthor.token, readerBook.id, {
      page: 1,
      rects: [[60, 650, 300, 664]],
      quote: 'jumps over',
      note: 'Second opinion.',
      color: 'rose',
    });

    // Friends, but notes aren't shared yet: nothing shows.
    const view = new ReaderPage(friend.page);
    await view.open(readerBook.id);
    await view.openNotes();
    await expect(view.friendHighlights).toHaveCount(0);
    await expect(view.friendNoteItems).toHaveCount(0);
    expect(await friendsNotes()).toEqual([]);

    // The first author shares their notes, and is told so in their own reader.
    expect((await author.api.post('/api/v1/user/sharing', { headers: auth(author.token), data: { notes: true } })).ok()).toBeTruthy();
    const own = new ReaderPage(author.page);
    await own.open(readerBook.id);
    await own.openNotes();
    await expect(own.notesPrivacy).toContainText('Friends can see your notes');

    // Now the friend sees them (and only theirs — the second author hasn't shared): an underline on the
    // page that opens the signed note, read-only.
    await view.open(readerBook.id);
    await expect(view.friendHighlights).toHaveCount(1);
    await expect(view.highlights).toHaveCount(0); // underlines, not the reader's own ink
    await view.openFriendHighlight();
    await expect(view.notesPanel).toBeVisible();
    await expect(view.friendNoteItems).toHaveCount(2);
    const opened = view.friendNoteItems.first();
    await expect(opened).toContainText(author.user.username);
    await expect(opened).toContainText('the quick brown fox');
    await expect(opened).toContainText('Remember this for the exam.');
    await expect(view.friendNoteItems.getByRole('button', { name: /edit|delete/i })).toHaveCount(0);
    await expect(view.noteItems).toHaveCount(0); // they're not the friend's own notes
    await expect(view.notesPrivacy).toHaveText('Only you can see your notes and highlights.');
    await expect(view.friendFilter(secondAuthorId)).toHaveCount(0);

    // The second author shares too: one filter pill each, both on.
    expect(
      (await secondAuthor.api.post('/api/v1/user/sharing', { headers: auth(secondAuthor.token), data: { notes: true } })).ok(),
    ).toBeTruthy();
    await view.open(readerBook.id);
    await view.openNotes();
    await expect(view.friendHighlights).toHaveCount(2);
    await expect(view.friendNoteItems).toHaveCount(3);
    await expect(view.friendFilter(authorId)).toHaveAttribute('aria-pressed', 'true');
    await expect(view.friendFilter(secondAuthorId)).toHaveAttribute('aria-pressed', 'true');

    // Turning the first author off hides only theirs, on the page and in the panel; it sticks on this device.
    await view.friendFilter(authorId).click();
    await expect(view.friendFilter(authorId)).toHaveAttribute('aria-pressed', 'false');
    await expect(view.friendHighlights).toHaveCount(1);
    await expect(view.friendNoteItems).toHaveCount(1);
    await expect(view.friendNoteItems.first()).toContainText('Second opinion.');
    await view.open(readerBook.id);
    await expect(view.friendHighlights).toHaveCount(1);
    await view.openNotes();
    await expect(view.friendFilter(authorId)).toHaveAttribute('aria-pressed', 'false');

    // The reader fixtures clean up their own notes; the borrower fixture doesn't.
    await deleteAllAnnotations(secondAuthor.api, secondAuthor.token, readerBook.id);
  });
});
