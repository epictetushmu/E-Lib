import { expect, test } from '../../fixtures';
import { createAnnotation } from '../../support/api';
import { ReaderPage } from '../../pages/ReaderPage';

// Friends see each other's notes in the reader only once the author shares them (off by default:
// notes were written as private). Shared notes are read-only, drawn as underlines the reader can hide.

test.describe("Friends' notes in the reader", () => {
  test('appear only once shared, open from the page, and can be hidden', async ({ reader, otherReader, readerBook }) => {
    // Two sign-ups, two browser logins and a book upload, like the other two-reader reader spec.
    test.slow();
    const author = reader;
    const friend = otherReader;
    const asAuthor = { Authorization: `Bearer ${author.token}` };
    const asFriend = { Authorization: `Bearer ${friend.token}` };

    // Become friends through the API (the UI flow has its own spec).
    const authorId = ((await (await author.api.get('/api/v1/user/profile', { headers: asAuthor })).json()) as { data: { _id: string } })
      .data._id;
    expect((await author.api.post('/api/v1/friends', { headers: asAuthor, data: { email: friend.user.email } })).ok()).toBeTruthy();
    expect((await friend.api.post(`/api/v1/friends/${authorId}/accept`, { headers: asFriend })).ok()).toBeTruthy();

    await createAnnotation(author.api, author.token, readerBook.id, {
      page: 1,
      rects: [[60, 690, 400, 704]],
      quote: 'the quick brown fox',
      note: 'Remember this for the exam.',
      color: 'aegean',
    });
    await createAnnotation(author.api, author.token, readerBook.id, { page: 2, rects: [], quote: '', note: 'Page two matters.', color: null });

    // Friends, but notes aren't shared yet: nothing shows.
    const view = new ReaderPage(friend.page);
    await view.open(readerBook.id);
    await view.openNotes();
    await expect(view.friendHighlights).toHaveCount(0);
    await expect(view.friendNoteItems).toHaveCount(0);
    expect(
      ((await (await friend.api.get(`/api/v1/books/${readerBook.id}/annotations/friends`, { headers: asFriend })).json()) as { data: unknown[] })
        .data,
    ).toEqual([]);

    // The author shares their notes, and is told so in their own reader.
    expect((await author.api.post('/api/v1/user/sharing', { headers: asAuthor, data: { notes: true } })).ok()).toBeTruthy();
    const own = new ReaderPage(author.page);
    await own.open(readerBook.id);
    await own.openNotes();
    await expect(own.notesPrivacy).toContainText('Friends can see your notes');
    await expect(own.friendNoteItems).toHaveCount(0); // the friend has none of their own

    // Now the friend sees them: an underline on the page that opens the signed note, read-only.
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

    // Hiding them clears the page, and the choice sticks on this device.
    await view.showFriendNotes.uncheck();
    await expect(view.friendHighlights).toHaveCount(0);
    await view.open(readerBook.id);
    await expect(view.friendHighlights).toHaveCount(0);
  });
});
