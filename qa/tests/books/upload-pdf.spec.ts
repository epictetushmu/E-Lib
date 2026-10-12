import { bookId, deleteBooksByTitle, getBookByTitle, loginAsAdmin, type UploadedBook } from '../../support/api';
import { buildSamplePdf } from '../../support/samplePdf';
import { uniqueBookTitle } from '../../support/testData';
import { expect, test } from '../../fixtures';
import { UploadPdfPage } from '../../pages/UploadPdfPage';

// The SPA has no single-book "add book" form anymore (see App.vue routes) —
// /admin/upload (frontend/src/views/admin/UploadPdf.vue) is the only upload
// flow, and it's admin-gated by the same `requireAdmin` router guard as the
// dashboard (see dashboard.spec.ts). It still needs covering both ways it's
// used: one PDF at a time, and several at once.

test.describe('Upload PDF (admin book upload)', () => {
  test('a logged-in non-admin is bounced back to the home page', async ({ authenticatedPage }) => {
    await authenticatedPage.goto('/admin/upload');

    // `requireAdmin` router guard should block the page outright — check both
    // the URL and that the upload form never rendered, not just where we
    // ended up.
    await expect(authenticatedPage).toHaveURL('/');
    await expect(authenticatedPage.getByTestId('file-input')).toHaveCount(0);
  });

  test('an admin can upload a single PDF', async ({ adminPage, request, adminCredentials }) => {
    const uploadPdf = new UploadPdfPage(adminPage);
    await uploadPdf.open();
    const title = uniqueBookTitle();

    await uploadPdf.uploadOne({ title, author: 'QA Test Author', pdf: buildSamplePdf(title) });

    await expect(uploadPdf.feedback).toContainText('uploaded successfully');

    // Cross-check against the server, not just the toast: the book must
    // actually exist with the fields the form submitted.
    const created = await getBookByTitle(request, title);
    expect(created).not.toBeNull();
    expect(created?.author).toBe('QA Test Author');
    expect(created?.file_extension).toBe('pdf');
    expect(created?.downloadable).toBe(true);
    expect(created?.status).toBe('draft');

    const token = await loginAsAdmin(request, adminCredentials.email, adminCredentials.password);
    await deleteBooksByTitle(request, token, [title]);

    // Confirm cleanup actually removed it, rather than assuming DELETE worked.
    expect(await getBookByTitle(request, title)).toBeNull();
  });

  test('an admin can upload multiple PDFs at once', async ({ adminPage, request, adminCredentials }) => {
    const uploadPdf = new UploadPdfPage(adminPage);
    await uploadPdf.open();
    const titles = [uniqueBookTitle(), uniqueBookTitle()];

    await uploadPdf.uploadMany(titles.map((title) => ({ title, author: 'QA Test Author', pdf: buildSamplePdf(title) })));

    await expect(uploadPdf.feedback).toContainText(`All ${titles.length} books were uploaded successfully`);

    // Cross-check that each file became its own book server-side — not that
    // the toast merely counted correctly, and not a dedup/overwrite bug where
    // two files collapse into one document.
    const created = await Promise.all(titles.map((title) => getBookByTitle(request, title)));
    const foundBooks = created.filter((book): book is UploadedBook => book !== null);
    expect(foundBooks).toHaveLength(titles.length);
    for (const book of foundBooks) {
      expect(book.author).toBe('QA Test Author');
      expect(book.file_extension).toBe('pdf');
    }
    expect(new Set(foundBooks.map(bookId)).size).toBe(titles.length);

    const token = await loginAsAdmin(request, adminCredentials.email, adminCredentials.password);
    await deleteBooksByTitle(request, token, titles);

    const afterCleanup = await Promise.all(titles.map((title) => getBookByTitle(request, title)));
    expect(afterCleanup.every((book) => book === null)).toBe(true);
  });
});
