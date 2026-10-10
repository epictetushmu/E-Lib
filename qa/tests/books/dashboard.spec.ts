import { expect, test } from '../../fixtures';
import { DashboardPage } from '../../pages/DashboardPage';
import { createBookViaApi, deleteBookViaApi, loginAsAdmin, setBookVisibility } from '../../support/api';
import { buildSamplePdf } from '../../support/samplePdf';
import { uniqueBookTitle } from '../../support/testData';

test.describe('Admin dashboard (/admin)', () => {
  test('a guest is redirected to the login page', async ({ page }) => {
    await page.goto('/admin');

    // `requireAdmin` router guard (frontend/src/router/guards.ts).
    await expect(page).toHaveURL('/login?redirect=/admin');
  });

  test('a logged-in non-admin is bounced back to the home page', async ({ authenticatedPage }) => {
    await authenticatedPage.goto('/admin');

    // `requireAdmin` router guard should block the page outright — check both
    // the URL and that the "Manage Books" table never rendered, not just
    // where we ended up.
    await expect(authenticatedPage).toHaveURL('/');
    await expect(authenticatedPage.getByTestId('dashboard-heading')).toHaveCount(0);
  });

  test('an admin sees the Manage Books table', async ({ adminPage }) => {
    const dashboard = new DashboardPage(adminPage);
    await dashboard.open();

    await expect(dashboard.heading).toBeVisible();
    await expect(dashboard.table).toBeVisible();
  });

  test('an admin sees a seeded book in the table', async ({ adminPage, seededBook }) => {
    const dashboard = new DashboardPage(adminPage);
    await dashboard.open();
    const row = dashboard.rowById(seededBook.id);

    await expect(row).toBeVisible();
    await expect(row).toContainText(seededBook.title);
    // The seededBook fixture sets status: 'public', featured: true — confirm
    // the table actually reflects that, not just that some row exists.
    await expect(row.getByRole('button', { name: 'Public' })).toBeVisible();
    await expect(row.getByRole('button', { name: 'Featured' })).toBeVisible();
  });

  test.describe('finding books', () => {
    // A public and a draft book sharing one unique word, so a search can isolate exactly these two.
    let token: string;
    let tag: string;
    let publicId: string;
    let draftId: string;

    test.beforeEach(async ({ request, adminCredentials }) => {
      token = await loginAsAdmin(request, adminCredentials.email, adminCredentials.password);
      tag = uniqueBookTitle('QAFind').replace(' ', '');
      const publicTitle = `${tag} Public`;
      const draftTitle = `${tag} Draft`;
      publicId = await createBookViaApi(request, token, { title: publicTitle, pdf: buildSamplePdf(publicTitle) });
      draftId = await createBookViaApi(request, token, { title: draftTitle, pdf: buildSamplePdf(draftTitle) });
      await setBookVisibility(request, token, publicId, { status: 'public' });
      await setBookVisibility(request, token, draftId, { status: 'draft' });
    });

    test.afterEach(async ({ request }) => {
      await deleteBookViaApi(request, token, publicId);
      await deleteBookViaApi(request, token, draftId);
    });

    test('status filters and search narrow the table', async ({ adminPage }) => {
      const dashboard = new DashboardPage(adminPage);
      await dashboard.open();
      await expect(dashboard.rowById(draftId)).toBeVisible();

      await dashboard.filter('Drafts').click();
      await expect(dashboard.filter('Drafts')).toHaveAttribute('aria-pressed', 'true');
      await expect(dashboard.rowById(draftId)).toBeVisible();
      await expect(dashboard.rowById(publicId)).toHaveCount(0);

      await dashboard.filter('Public').click();
      await expect(dashboard.rowById(publicId)).toBeVisible();
      await expect(dashboard.rowById(draftId)).toHaveCount(0);

      // Search applies on top of the "All" filter, matching on title.
      await dashboard.filter('All').click();
      await dashboard.searchInput.fill(tag.toLowerCase());
      await expect(adminPage.locator('[data-testid^="book-row-"]')).toHaveCount(2);

      await dashboard.searchInput.fill(`${tag} no such book`);
      await expect(dashboard.noMatchMessage).toBeVisible();
      await dashboard.showAllBooksButton.click();
      await expect(dashboard.searchInput).toHaveValue('');
      await expect(dashboard.rowById(publicId)).toBeVisible();
      await expect(dashboard.rowById(draftId)).toBeVisible();
    });

    test('clicking a book on the shelf jumps to its row, even when a filter hides it', async ({ adminPage }) => {
      const dashboard = new DashboardPage(adminPage);
      await dashboard.open();
      await dashboard.filter('Drafts').click();
      await expect(dashboard.rowById(publicId)).toHaveCount(0);

      await dashboard.spineById(publicId).click();

      await expect(dashboard.filter('All')).toHaveAttribute('aria-pressed', 'true');
      await expect(dashboard.rowById(publicId)).toBeInViewport();
      await expect(dashboard.rowById(publicId)).toHaveClass(/flash/);
    });
  });
});
