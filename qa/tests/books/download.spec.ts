import { createBookViaApi, deleteBookViaApi, loginAsAdmin, setBookVisibility } from '../../support/api';
import { buildSamplePdf } from '../../support/samplePdf';
import { uniqueBookTitle } from '../../support/testData';
import { expect, test } from '../../fixtures';
import { BookDetailPage } from '../../pages/BookDetailPage';
import { ProfilePage } from '../../pages/ProfilePage';

// book-detail.spec.ts only checks the Download button's *visibility* — clicking it was
// never exercised anywhere in the suite. This covers the actual mechanism end to end:
// the browser must receive the real file (not just "some download fired"), and the
// server-side effect of downloading (recordDownload) must be visible from a second,
// independent page (Profile's Downloaded Books tab) — not just inferred from the click.
//
// The "blocked" half of this mechanism (downloadable: false → 403, disabled button) is
// already covered by admin-actions.spec.ts's downloadable-toggle test.

test.describe('Downloading a book', () => {
  test('streams the exact uploaded file and records it in Downloaded Books', async ({
    authenticatedPage,
    request,
    adminCredentials,
  }) => {
    const adminToken = await loginAsAdmin(request, adminCredentials.email, adminCredentials.password);
    const title = uniqueBookTitle('QA Download');
    const pdf = buildSamplePdf(title);
    const id = await createBookViaApi(request, adminToken, { title, pdf });
    await setBookVisibility(request, adminToken, id, { status: 'public' });

    try {
      const bookDetail = new BookDetailPage(authenticatedPage);
      await bookDetail.openBook(id);
      await expect(bookDetail.downloadButton).toBeVisible();

      const [download] = await Promise.all([
        authenticatedPage.waitForEvent('download'),
        bookDetail.downloadButton.click(),
      ]);

      // The filename comes from the frontend's own `link.download = "${title}.pdf"`
      // (BookDetail.vue), not the server's Content-Disposition header — a blob-URL
      // anchor download always uses the attribute, so this also confirms the right
      // book's title made it into the click handler. WebKit sanitizes spaces to
      // underscores in the suggested filename; Chromium/Firefox don't — normalize
      // both sides so the check holds across browsers without assuming either.
      const normalizeFilename = (name: string) => name.replace(/\s+/g, '_');
      expect(normalizeFilename(download.suggestedFilename())).toBe(normalizeFilename(`${title}.pdf`));

      // Cross-check the actual bytes, not just that a download fired: byte-for-byte
      // against the exact PDF uploaded for this book, not merely "looks like a PDF".
      const stream = await download.createReadStream();
      const chunks: Buffer[] = [];
      for await (const chunk of stream) {
        chunks.push(chunk as Buffer);
      }
      expect(Buffer.concat(chunks).equals(pdf)).toBe(true);

      // Cross-check the server-side effect from a second, independent page — proves
      // BookController::downloadBook's recordDownload call actually happened, not
      // just that the browser received a file.
      const profile = new ProfilePage(authenticatedPage);
      await profile.open();
      await profile.downloadedTab.click();
      await expect(profile.downloadedBookById(id)).toBeVisible();
    } finally {
      await deleteBookViaApi(request, adminToken, id);
    }
  });
});
