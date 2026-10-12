import { expect, test } from '../../fixtures';
import { borrowViaApi } from '../../support/api';
import { FriendLibraryPage } from '../../pages/FriendLibraryPage';
import { ProfilePage } from '../../pages/ProfilePage';

// Readers become friends by request + accept, and then see only what the other chose to share:
// saved books and reviews by default, loans and downloads once switched on. The API enforces it
// too — a non-friend's library is a 403, whatever the UI shows.

test.describe('Friends', () => {
  test('request, accept, and see only what the friend shares', async ({ borrower, secondBorrower, lentBook }) => {
    const asker = borrower;
    const friend = secondBorrower;
    const friendAuth = { Authorization: `Bearer ${friend.token}` };
    const friendId = ((await (await friend.api.get('/api/v1/user/profile', { headers: friendAuth })).json()) as {
      data: { _id: string };
    }).data._id;
    const libraryStatus = async () =>
      (await asker.api.get(`/api/v1/friends/${friendId}/library`, { headers: { Authorization: `Bearer ${asker.token}` } })).status();

    // The friend has a saved book, a review and a loan of the same book.
    expect((await friend.api.post('/api/v1/save-book', { headers: friendAuth, data: { book_id: lentBook.id } })).ok()).toBeTruthy();
    expect(
      (await friend.api.post('/api/v1/reviews', { headers: friendAuth, data: { book_id: lentBook.id, rating: 5, comment: 'A keeper.' } })).ok(),
    ).toBeTruthy();
    expect(await borrowViaApi(friend.api, friend.token, lentBook.id)).toBe(200);

    // Ask…
    const askerProfile = new ProfilePage(asker.page);
    await askerProfile.open();
    await askerProfile.sendFriendRequest(friend.user.email);
    await expect(askerProfile.friend(friend.user.username)).toContainText('Sent');
    expect(await libraryStatus()).toBe(403);

    // …and accept.
    const friendProfile = new ProfilePage(friend.page);
    await friendProfile.open();
    await expect(friendProfile.friendsTab).toContainText('1'); // pending-request badge
    await friendProfile.friendsTab.click();
    await friendProfile.friend(asker.user.username).getByRole('button', { name: 'Accept' }).click();
    await expect(friendProfile.friend(asker.user.username)).toContainText('Friends since');

    // Defaults: saved books and reviews shown, the loan (and downloads) kept private.
    const library = new FriendLibraryPage(asker.page);
    await askerProfile.open();
    await askerProfile.friendsTab.click();
    await askerProfile.friend(friend.user.username).getByRole('link', { name: friend.user.username }).click();
    await expect(asker.page).toHaveURL(`/friends/${friendId}`);
    await expect(library.book('saved', lentBook.id)).toBeVisible();
    await expect(library.reviews.filter({ hasText: lentBook.title })).toContainText('A keeper.');
    await expect(library.book('loans', lentBook.id)).toHaveCount(0);
    await expect(library.privateNote).toHaveText('Keeps their books they’re borrowing and downloads private.');

    // The API sends only what a book card shows: never storage paths or every reader's embedded reviews.
    const shared = (await (
      await asker.api.get(`/api/v1/friends/${friendId}/library`, { headers: { Authorization: `Bearer ${asker.token}` } })
    ).json()) as { data: { saved: Record<string, unknown>[] } };
    expect(shared.data.saved).toHaveLength(1);
    for (const field of ['file_path', 'pdf_path', 'reviews']) {
      expect(shared.data.saved[0]).not.toHaveProperty(field);
    }

    // The friend hides saved books and shares what they're borrowing.
    await expect(friendProfile.sharing('saved')).toBeChecked();
    await expect(friendProfile.sharing('loans')).not.toBeChecked();
    await friendProfile.setSharing('saved', false);
    await friendProfile.setSharing('loans', true);
    await friendProfile.open();
    await friendProfile.friendsTab.click();
    await expect(friendProfile.sharing('saved')).not.toBeChecked(); // stored, not just ticked
    await expect(friendProfile.sharing('loans')).toBeChecked();

    await library.open(friendId);
    await expect(library.book('loans', lentBook.id)).toBeVisible();
    await expect(library.book('saved', lentBook.id)).toHaveCount(0);
    await expect(library.privateNote).toHaveText('Keeps their saved books and downloads private.');

    // Unfriending closes it again.
    await askerProfile.open();
    await askerProfile.friendsTab.click();
    asker.page.once('dialog', (dialog) => dialog.accept()); // "Remove … from your friends?"
    await askerProfile.friend(friend.user.username).getByRole('button', { name: 'Remove' }).click();
    await expect(askerProfile.friend(friend.user.username)).toHaveCount(0);
    expect(await libraryStatus()).toBe(403);
  });
});
