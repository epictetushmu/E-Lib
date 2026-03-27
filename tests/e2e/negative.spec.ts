import { test, expect } from '@playwright/test';

test.describe('negative — HTML', () => {
  test('unknown path shows error page with 404', async ({ page }) => {
    const res = await page.goto('/this-route-does-not-exist-xyz', { waitUntil: 'domcontentloaded' });
    expect(res?.status()).toBe(404);
    await expect(page.getByRole('heading', { name: '404' })).toBeVisible();
    await expect(page.getByText(/does not exist/i)).toBeVisible();
  });

  test('invalid book id shows error page', async ({ page }) => {
    const res = await page.goto('/book/not-a-valid-object-id', { waitUntil: 'domcontentloaded' });
    expect(res?.status()).toBe(404);
    await expect(page.getByRole('heading', { name: '404' })).toBeVisible();
  });

  test('profile redirects to home with login prompt when not logged in', async ({ page }) => {
    await page.goto('/profile', { waitUntil: 'domcontentloaded' });
    const url = new URL(page.url());
    expect(url.pathname).toBe('/');
    expect(url.searchParams.get('showLogin')).toBe('1');
    expect(url.searchParams.get('redirect')).toContain('/profile');
  });

  test('dashboard redirects to home with login prompt when not logged in', async ({ page }) => {
    await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });
    const url = new URL(page.url());
    expect(url.pathname).toBe('/');
    expect(url.searchParams.get('showLogin')).toBe('1');
    expect(url.searchParams.get('redirect')).toContain('/dashboard');
  });
});

test.describe('negative — API', () => {
  test('GET /api/v1/user without token returns 401', async ({ request }) => {
    const res = await request.get('/api/v1/user');
    expect(res.status()).toBe(401);
    const body = await res.json();
    expect(body.status).toBe('error');
  });

  test('GET /api/v1/unknown returns 404 JSON', async ({ request }) => {
    const res = await request.get('/api/v1/no-such-endpoint-ever');
    expect(res.status()).toBe(404);
    const body = await res.json();
    expect(body.status).toBe('error');
    expect(body.message).toMatch(/not found/i);
  });

  test('POST /api/v1/login with wrong credentials returns 401', async ({ request }) => {
    const res = await request.post('/api/v1/login', {
      headers: { 'Content-Type': 'application/json' },
      data: JSON.stringify({
        email: 'definitely-not-a-user@example.test',
        password: 'WrongPassword999!',
      }),
    });
    expect(res.status()).toBe(401);
    const body = await res.json();
    expect(body.status).toBe('error');
  });

  test('POST /api/v1/signup with invalid email returns error', async ({ request }) => {
    const res = await request.post('/api/v1/signup', {
      headers: { 'Content-Type': 'application/json' },
      data: JSON.stringify({
        username: 'bad',
        email: 'not-an-email',
        password: 'ValidPass123!',
      }),
    });
    expect(res.status()).toBe(400);
    const body = await res.json();
    expect(body.status).toBe('error');
  });
});
