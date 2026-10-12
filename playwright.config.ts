import { existsSync } from 'node:fs';
import path from 'node:path';
import { config as loadDotenv } from 'dotenv';
import { defineConfig, devices } from '@playwright/test';

// The app's own .env (loaded separately by App\Includes\Environment for PHP)
// isn't read by Node at all by default. Load it here too so QA_ADMIN_EMAIL/
// QA_ADMIN_PASSWORD (or PLAYWRIGHT_BASE_URL) can live there instead of having
// to be exported in the shell — but never override a value the shell already
// set, so explicit env vars still win.
loadDotenv({ path: path.join(__dirname, '.env'), quiet: true });

const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? 'http://localhost:8000';

// On NixOS, Playwright's own downloaded browsers can't launch (missing FHS
// shared libs like libglib-2.0.so.0). shell.nix provides a patched build via
// PLAYWRIGHT_BROWSERS_PATH — if that's unset here, we're not inside
// `nix-shell` and every test would fail with a cryptic native-library error.
// Fail fast with an actionable message instead.
if (!process.env.CI && existsSync('/etc/NIXOS') && !process.env.PLAYWRIGHT_BROWSERS_PATH) {
  throw new Error(
    'PLAYWRIGHT_BROWSERS_PATH is not set. Run tests from inside `nix-shell` ' +
      '(it exports this for you) — see shell.nix / qa/README.md.',
  );
}

export default defineConfig({
  testDir: './qa/tests',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : undefined,
  reporter: process.env.CI ? [['github'], ['html', { open: 'never' }]] : 'html',

  use: {
    baseURL,
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
  },

  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
    { name: 'firefox', use: { ...devices['Desktop Firefox'] } },
    { name: 'webkit', use: { ...devices['Desktop Safari'] } },
  ],

  // The app requires a live MongoDB connection to boot (see public/index.php),
  // so tests expect it already running (`php -S localhost:8000 -t public` or
  // `docker-compose up`) rather than having Playwright manage the server.
});
