import { createBookViaApi, deleteBookViaApi, getBookByTitle, loginAsAdmin, setBookVisibility } from '../../support/api';
import { buildSamplePdf } from '../../support/samplePdf';
import { uniqueBookTitle } from '../../support/testData';
import { expect, test } from '../../fixtures';
import { DashboardPage } from '../../pages/DashboardPage';
import { BrowsePage } from '../../pages/BrowsePage';
import { BookDetailPage } from '../../pages/BookDetailPage';
import { HomePage } from '../../pages/HomePage';

// Three of the admin dashboard's mutating actions (frontend/src/views/admin/Dashboard.vue)
// have no coverage in dashboard.spec.ts, which only checks page access/display. All three
// are either irreversible or gate a server-enforced access rule, so each one here
// cross-checks the resulting server state — and, where relevant, another page entirely —
// not just the dashboard's own row/toast.
//
// Books here are created directly via the API rather than the `seededBook` fixture,
// since each test needs to control (or itself perform) the delete.

test.describe('Admin book management actions', () => {
  test('cancelling the delete confirmation leaves the book untouched', async ({ adminPage, request, adminCredentials }) => {
    const token = await loginAsAdmin(request, adminCredentials.email, adminCredentials.password);
    const title = uniqueBookTitle('QA Delete Cancel');
    const id = await createBookViaApi(request, token, { title, pdf: buildSamplePdf(title) });

    try {
      const dashboard = new DashboardPage(adminPage);
      await dashboard.open();
      const row = dashboard.rowById(id);
      await expect(row).toBeVisible();

      adminPage.once('dialog', (dialog) => dialog.dismiss());
      await dashboard.deleteButton(id).click();

      await expect(row).toBeVisible();
      expect(await getBookByTitle(request, title)).not.toBeNull();
    } finally {
      await deleteBookViaApi(request, token, id);
    }
  });

  test('confirming delete permanently removes the book', async ({ adminPage, request, adminCredentials }) => {
    const token = await loginAsAdmin(request, adminCredentials.email, adminCredentials.password);
    const title = uniqueBookTitle('QA Delete Confirm');
    const id = await createBookViaApi(request, token, { title, pdf: buildSamplePdf(title) });

    const dashboard = new DashboardPage(adminPage);
    await dashboard.open();
    const row = dashboard.rowById(id);
    await expect(row).toBeVisible();

    adminPage.once('dialog', (dialog) => dialog.accept());
    await dashboard.deleteButton(id).click();

    // Cross-check the row disappearing against the book actually being gone
    // server-side — not just removed from this one rendered list.
    await expect(row).toHaveCount(0);
    expect(await getBookByTitle(request, title)).toBeNull();
  });

  test('toggling status controls whether the book is publicly visible', async ({
    adminPage,
    request,
    adminCredentials,
  }, testInfo) => {
    // Five page loads plus several API round-trips against the dev server's
    // single-threaded `php -S` — comfortably under 30s alone, but tight when
    // the full suite's other workers are hammering the same server. Give it
    // more headroom rather than trim the round-trip coverage that's the point
    // of this test.
    testInfo.setTimeout(60_000);

    const token = await loginAsAdmin(request, adminCredentials.email, adminCredentials.password);
    const title = uniqueBookTitle('QA Status Toggle');
    const id = await createBookViaApi(request, token, { title, pdf: buildSamplePdf(title) });
    await setBookVisibility(request, token, id, { status: 'draft' });

    try {
      const browse = new BrowsePage(adminPage);
      const dashboard = new DashboardPage(adminPage);

      await browse.open();
      await expect(browse.cardById(id)).toHaveCount(0); // draft baseline

      await dashboard.open();
      await expect(dashboard.statusToggle(id)).toHaveText('Draft');
      await dashboard.statusToggle(id).click();
      await expect(dashboard.statusToggle(id)).toHaveText('Public');

      expect((await getBookByTitle(request, title))?.status).toBe('public');
      await browse.open();
      await expect(browse.cardById(id)).toBeVisible();

      // Full round-trip, not just a one-way flip.
      await dashboard.open();
      await dashboard.statusToggle(id).click();
      await expect(dashboard.statusToggle(id)).toHaveText('Draft');

      expect((await getBookByTitle(request, title))?.status).toBe('draft');
      await browse.open();
      await expect(browse.cardById(id)).toHaveCount(0);
    } finally {
      await deleteBookViaApi(request, token, id);
    }
  });

  test('disabling downloads is enforced both in the UI and by the server', async ({
    adminPage,
    request,
    adminCredentials,
  }) => {
    const token = await loginAsAdmin(request, adminCredentials.email, adminCredentials.password);
    const title = uniqueBookTitle('QA Downloadable Toggle');
    const id = await createBookViaApi(request, token, { title, pdf: buildSamplePdf(title) });
    await setBookVisibility(request, token, id, { status: 'public' });

    try {
      const dashboard = new DashboardPage(adminPage);
      await dashboard.open();
      await dashboard.editButton(id).click();
      await expect(dashboard.downloadableCheckbox).toBeChecked();
      await dashboard.downloadableCheckbox.uncheck();
      await dashboard.saveEditButton.click();
      // The modal closes only once the PUT succeeds; navigating before that can race the save.
      await expect(dashboard.editDialog).toHaveCount(0);

      // UI layer: the detail page shows "download disabled" instead of the download button.
      const bookDetail = new BookDetailPage(adminPage);
      await bookDetail.openBook(id);
      await expect(bookDetail.downloadDisabledButton).toBeVisible();
      await expect(bookDetail.downloadButton).toHaveCount(0);

      // Server layer: the endpoint itself refuses, independent of what the UI happens to show —
      // this is the check that actually catches a client/server enforcement mismatch.
      const response = await request.get(`/api/v1/books/${id}/download`, {
        headers: { Authorization: `Bearer ${token}` },
      });
      expect(response.status()).toBe(403);
    } finally {
      await deleteBookViaApi(request, token, id);
    }
  });

  test('featuring a book puts it in the home page Featured Collection, and unfeaturing removes it', async ({
    adminPage,
    request,
    adminCredentials,
  }, testInfo) => {
    // Four page loads plus API cross-checks against the single-threaded dev server.
    testInfo.setTimeout(60_000);

    const token = await loginAsAdmin(request, adminCredentials.email, adminCredentials.password);
    const title = uniqueBookTitle('QA Featured Toggle');
    const id = await createBookViaApi(request, token, { title, pdf: buildSamplePdf(title) });
    await setBookVisibility(request, token, id, { status: 'public', featured: false });

    try {
      const dashboard = new DashboardPage(adminPage);
      const home = new HomePage(adminPage);

      await dashboard.open();
      await expect(dashboard.featuredToggle(id)).toHaveAttribute('aria-pressed', 'false');
      await dashboard.featuredToggle(id).click();
      await expect(dashboard.featuredToggle(id)).toHaveAttribute('aria-pressed', 'true');

      expect((await getBookByTitle(request, title))?.featured).toBe(true);
      await home.open();
      await expect(home.bookCardById(id)).toBeVisible();

      await dashboard.open();
      await dashboard.featuredToggle(id).click();
      await expect(dashboard.featuredToggle(id)).toHaveAttribute('aria-pressed', 'false');

      expect((await getBookByTitle(request, title))?.featured).toBe(false);
      await home.open();
      await expect(adminPage.getByTestId('featured-section')).toBeVisible();
      await expect(home.bookCardById(id)).toHaveCount(0);
    } finally {
      await deleteBookViaApi(request, token, id);
    }
  });

  test('editing a book saves its details and shows them on the book page', async ({ adminPage, request, adminCredentials }) => {
    const token = await loginAsAdmin(request, adminCredentials.email, adminCredentials.password);
    const title = uniqueBookTitle('QA Edit Before');
    const id = await createBookViaApi(request, token, { title, pdf: buildSamplePdf(title) });
    const newTitle = uniqueBookTitle('QA Edit After');

    try {
      const dashboard = new DashboardPage(adminPage);
      await dashboard.open();
      await dashboard.editButton(id).click();
      await expect(dashboard.editDialog).toBeVisible();
      await expect(dashboard.editField('Title')).toHaveValue(title);

      await dashboard.editField('Title').fill(newTitle);
      await dashboard.editField('Author').fill('E. Kazantzakis');
      await dashboard.editField('Description').fill('A field guide to Cretan monasteries.');
      await dashboard.editField('ISBN').fill('9780000000002');
      await dashboard.editField('Categories').fill('QA Travel');
      await dashboard.editField('Categories').press('Enter');
      // The suggestions menu stays open after a pick (for adding several) and overlaps Save; close it like a user would.
      await dashboard.editField('Categories').press('Escape');
      await dashboard.saveEditButton.click();

      await expect(adminPage.getByText('Book updated successfully.')).toBeVisible();
      await expect(dashboard.editDialog).toHaveCount(0);
      await expect(dashboard.rowById(id)).toContainText(newTitle);
      await expect(dashboard.rowById(id)).toContainText('E. Kazantzakis');

      // Persisted server-side, not just patched into the page's own list.
      expect(await getBookByTitle(request, title)).toBeNull();
      expect(await getBookByTitle(request, newTitle)).toMatchObject({
        author: 'E. Kazantzakis',
        description: 'A field guide to Cretan monasteries.',
        isbn: '9780000000002',
        categories: ['QA Travel'],
      });

      const detail = new BookDetailPage(adminPage);
      await detail.openBook(id);
      await expect(detail.title).toHaveText(newTitle);
    } finally {
      await deleteBookViaApi(request, token, id);
    }
  });
});
