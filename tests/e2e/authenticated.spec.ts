import { test, expect } from '@playwright/test';

test.describe('session-protected pages', () => {
  test('profile loads when logged in', async ({ page }) => {
    const res = await page.goto('/profile', { waitUntil: 'domcontentloaded' });
    expect(res?.ok()).toBeTruthy();
    expect(page.url()).toContain('/profile');
    await expect(page).toHaveTitle(/My Profile \| Epictetus Library/);
  });

  test('dashboard loads when logged in', async ({ page }) => {
    const res = await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });
    expect(res?.ok()).toBeTruthy();
    expect(page.url()).toContain('/dashboard');
    await expect(page.locator('body')).toBeVisible();
  });
});
