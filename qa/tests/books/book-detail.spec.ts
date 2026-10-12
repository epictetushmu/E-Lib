import { expect, test } from '../../fixtures';
import { BookDetailPage } from '../../pages/BookDetailPage';
import { HomePage } from '../../pages/HomePage';

test.describe('Book detail page', () => {
  test('shows book details to a guest, with preview but no save/download', async ({
    page,
    seededBook,
  }) => {
    const detail = new BookDetailPage(page);
    await detail.openBook(seededBook.id);

    await expect(detail.title).toHaveText(seededBook.title);
    await expect(detail.previewButton).toBeVisible();
    await expect(detail.saveButton).toBeHidden();
    await expect(detail.downloadButton).toBeHidden();
    await expect(detail.loginHint).toBeVisible();
  });

  test('the "Online Preview" button opens the reader for the book, even for a guest', async ({
    page,
    seededBook,
  }) => {
    const detail = new BookDetailPage(page);
    await detail.openBook(seededBook.id);

    await detail.previewButton.click();

    // /read/:id has no auth guard — guests can preview, just not save/download.
    await expect(page).toHaveURL(`/read/${seededBook.id}`);
  });

  test('a logged-in user also sees save and download actions', async ({
    authenticatedPage,
    seededBook,
  }) => {
    const detail = new BookDetailPage(authenticatedPage);
    await detail.openBook(seededBook.id);

    await expect(detail.saveButton).toBeVisible();
    await expect(detail.downloadButton).toBeVisible();
  });

  test('the home page Featured Collection links to the book detail page', async ({
    page,
    seededBook,
  }) => {
    const home = new HomePage(page);
    await home.open();

    const card = home.bookCardById(seededBook.id);
    await expect(card).toBeVisible();
    await expect(card).toHaveAttribute('href', `/books/${seededBook.id}`);
  });
});
