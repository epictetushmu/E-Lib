import { mkdirSync } from 'fs';
import { dirname } from 'path';
import { test as setup, expect } from '@playwright/test';

const authFile = 'playwright/.auth/user.json';

setup('register and login', async ({ request }) => {
  mkdirSync(dirname(authFile), { recursive: true });

  const suffix = Date.now();
  const email = `e2e-${suffix}@example.test`;
  const username = `e2euser${suffix}`;
  const password = 'TestPassword123!';

  const signupRes = await request.post('/api/v1/signup', {
    headers: { 'Content-Type': 'application/json' },
    data: JSON.stringify({ username, email, password }),
  });
  expect(signupRes.ok(), await signupRes.text()).toBeTruthy();

  const loginRes = await request.post('/api/v1/login', {
    headers: { 'Content-Type': 'application/json' },
    data: JSON.stringify({ email, password }),
  });
  expect(loginRes.ok(), await loginRes.text()).toBeTruthy();

  await request.storageState({ path: authFile });
});
