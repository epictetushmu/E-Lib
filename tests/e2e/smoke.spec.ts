import { test, expect } from '@playwright/test';

function assertNoDatabaseFailure(html: string) {
  expect(html, 'MongoDB bootstrap should not return fatal DB error page').not.toContain(
    'MongoDB is required but could not be reached',
  );
}

test.describe('public smoke routes', () => {
  test('home loads', async ({ page }) => {
    const res = await page.goto('/', { waitUntil: 'domcontentloaded' });
    expect(res?.ok()).toBeTruthy();
    const html = await page.content();
    assertNoDatabaseFailure(html);
    await expect(page).toHaveTitle(/Epictetus Library/);
  });

  test('view-books loads', async ({ page }) => {
    const res = await page.goto('/view-books', { waitUntil: 'domcontentloaded' });
    expect(res?.ok()).toBeTruthy();
    assertNoDatabaseFailure(await page.content());
    await expect(page).toHaveTitle(/View Books \| Epictetus Library/);
  });

  test('docs loads', async ({ page }) => {
    const res = await page.goto('/docs', { waitUntil: 'domcontentloaded' });
    expect(res?.ok()).toBeTruthy();
    assertNoDatabaseFailure(await page.content());
    await expect(page).toHaveTitle(/E-Lib Documentation/);
  });

  test('add-book loads', async ({ page }) => {
    const res = await page.goto('/add-book', { waitUntil: 'domcontentloaded' });
    expect(res?.ok()).toBeTruthy();
    assertNoDatabaseFailure(await page.content());
    await expect(page).toHaveTitle(/Add Book \| Epictetus Library/);
  });

  test('signup route loads', async ({ page }) => {
    const res = await page.goto('/signup', { waitUntil: 'domcontentloaded' });
    expect(res?.ok()).toBeTruthy();
    assertNoDatabaseFailure(await page.content());
    await expect(page).toHaveTitle(/Epictetus Library/);
  });
});
