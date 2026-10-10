import { expect, test } from '../../fixtures';
import { DashboardPage } from '../../pages/DashboardPage';
import { LogsPage } from '../../pages/LogsPage';
import { loginViaApi } from '../../support/api';

// Server logs can contain request paths, emails and stack traces, so the page must stay
// admin-only on both layers: the `requireAdmin` router guard *and* the API itself.

test.describe('Server logs (/admin/logs)', () => {
  test('a guest is redirected to the login page', async ({ page }) => {
    await page.goto('/admin/logs');

    await expect(page).toHaveURL('/login?redirect=/admin/logs');
  });

  test('a logged-in non-admin is bounced back to the home page', async ({ authenticatedPage }) => {
    const logs = new LogsPage(authenticatedPage);
    await logs.open();

    await expect(authenticatedPage).toHaveURL('/');
    await expect(logs.heading).toHaveCount(0);
  });

  test('the logs API refuses guests and non-admins, independent of the UI guard', async ({ request, registeredUser }) => {
    expect((await request.get('/api/v1/admin/logs')).status()).toBe(401);

    const { token } = await loginViaApi(request, registeredUser.email, registeredUser.password);
    const response = await request.get('/api/v1/admin/logs', { headers: { Authorization: `Bearer ${token}` } });
    expect(response.status()).toBe(403);
  });

  test('an admin reaches the logs from the dashboard and sees them load', async ({ adminPage }) => {
    const dashboard = new DashboardPage(adminPage);
    await dashboard.open();
    await dashboard.serverLogsLink.click();

    const logs = new LogsPage(adminPage);
    await expect(adminPage).toHaveURL('/admin/logs');
    await expect(logs.heading).toBeVisible();
    await expect(logs.errorLogsTab).toBeVisible();
    await expect(adminPage.getByRole('status', { name: 'Loading logs' })).toHaveCount(0);
    await expect(logs.loadError).toHaveCount(0);

    await logs.requestLogsTab.click();
    await expect(adminPage.getByRole('heading', { name: 'Request Logs' })).toBeVisible();
  });
});
