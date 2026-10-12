import { createBookViaApi, deleteBookViaApi, loginAsAdmin } from '../../support/api';
import { buildSamplePdf } from '../../support/samplePdf';
import { uniqueBookTitle } from '../../support/testData';
import { expect, test } from '../../fixtures';
import { BrowsePage } from '../../pages/BrowsePage';

// No dedicated "empty state" test: Books is shared, global state across the whole
// suite (there's no per-test DB), and other specs seed books concurrently — an
// assertion that zero books exist would be inherently flaky under parallel workers.

test.describe('Book browsing (/browse)', () => {
  test('lists a seeded public book', async ({ page, seededBook }) => {
    const browse = new BrowsePage(page);
    await browse.open();

    const card = browse.cardById(seededBook.id);
    await expect(card).toBeVisible();
    await card.click();

    await expect(page).toHaveURL(`/books/${seededBook.id}`);
  });

  test('does not list a draft book', async ({ page, request, adminCredentials }) => {
    // POST /api/v1/books always creates as draft — no need to touch status explicitly.
    const token = await loginAsAdmin(request, adminCredentials.email, adminCredentials.password);
    const title = uniqueBookTitle();
    const id = await createBookViaApi(request, token, { title, pdf: buildSamplePdf(title) });

    const browse = new BrowsePage(page);
    await browse.open();

    await expect(browse.cardById(id)).toHaveCount(0);

    await deleteBookViaApi(request, token, id);
  });
});
