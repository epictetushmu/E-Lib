# E-Lib E2E Tests

Playwright + TypeScript end-to-end tests for E-Lib, using the Page Object Model.

**This suite only ever talks to the app's UI and HTTP API — never the database.**
Where a test needs state the UI/API can't produce (an admin account), that's
provisioned out-of-band by ops tooling under `scripts/`, and the suite just
consumes it via environment variables. See "Admin-gated tests" below.

## Layout

```
qa/
  tests/            # *.spec.ts files, grouped by feature (auth/, books/, profile/, navigation/,
                     # reader/ — search, navigation, highlights & notes, rich notes, diagrams)
  pages/            # Page Objects — one class per page/component, DOM details live here only
  fixtures/         # Custom Playwright fixtures (registeredUser, adminCredentials,
                     # authenticatedPage, adminPage, seededBook, readerBook, reader,
                     # otherReader) — test setup/teardown
  support/          # testData.ts (unique data generators), api.ts (thin wrappers around
                     # the app's real HTTP endpoints for setup/teardown), samplePdf.ts
                     # (generates dummy PDFs in memory — no binary fixture is
                     # committed, since .gitignore blanket-ignores *.pdf; buildReaderPdf()
                     # is a 12-page text book with known search terms), env.ts
playwright.config.ts   # repo root; testDir points at qa/tests
```

Specs only import from `pages/`, `fixtures/`, and `support/` — never assert on raw
selectors inline. If a test needs a new element, add it to the relevant Page Object
first.

## Setup

```bash
npm install
npx playwright install --with-deps   # downloads browser binaries
```

**On NixOS** (`nix-shell`), skip `playwright install`: the shell already provides
matching, pre-patched browsers via `pkgs.playwright-driver.browsers` and sets
`PLAYWRIGHT_BROWSERS_PATH` for you (see `shell.nix`). Playwright's own downloaded
binaries won't launch on NixOS (missing FHS shared libs). Just run `npm install`
inside `nix-shell`, then the commands below. If you bump `@playwright/test` in
`package.json`, bump the `playwright-driver.browsers` revision in `shell.nix` to
match, or launches will fail with a browser/revision mismatch.

## Running

The suite drives a real instance of the app, so start one first — MongoDB is required, there is no fallback (see `public/index.php`):

```bash
# Option A: PHP built-in server (needs MONGO_URI set in .env, e.g. Atlas)
php -S localhost:8000 -t public

# Option B: Docker Compose with the dev overrides (app on :8081 + a throwaway MongoDB; see README)
docker compose up -d
```

Then, from the repo root:

```bash
npm run test:e2e            # headless, all browsers
npm run test:e2e:headed     # headed, for debugging
npm run test:e2e:ui         # Playwright UI mode
npm run test:e2e:report     # open the last HTML report
```

By default tests target `http://localhost:8000`. Point them elsewhere with:

```bash
PLAYWRIGHT_BASE_URL=http://localhost:8081 npm run test:e2e
```

### Admin-gated tests

A few specs (dashboard, upload-pdf) need to act as an admin. The app has no
self-service way to become one (`AuthenticatedUser::isAdmin()` only trusts the
`isAdmin` flag on the Users document, set at login from the database — there's
no signup-as-admin or promote-yourself flow, by design). So the suite doesn't
attempt to create one either; provision one with the app's own ops tooling
first:

```bash
composer admin:create -- <email> <password>
```

Then point the suite at it:

```bash
QA_ADMIN_EMAIL=<email> QA_ADMIN_PASSWORD=<password> npm run test:e2e
```

Without these set, admin-gated tests fail fast with a message pointing back
here, rather than a confusing downstream error.

### Test data cleanup

- Books created via `support/api.ts` (the `seededBook` fixture, and the
  browse/upload-pdf specs) are deleted afterwards through the real
  `DELETE /api/v1/books/:id` endpoint.
- Highlights and notes made by the `reader` / `otherReader` fixtures are deleted
  afterwards through the real annotations API (`DELETE /api/v1/annotations/:id`).
  Each reader gets its own API client: the API trusts a PHP session cookie over
  a Bearer token, so a shared client would act as whichever user logged in last.
- Users created via signup (`registeredUser`, and the signup specs) are **not**
  deleted — there's no self-service "delete my account" API. They're tagged
  with the `e2e.e-lib.test` email domain (see `support/testData.ts`) so
  they're easy to identify if a database owner ever wants to purge them
  separately; the suite itself doesn't touch the database to do so.
