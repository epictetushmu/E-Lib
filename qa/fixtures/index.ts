import { test as base, expect, type APIRequestContext, type Browser, type Page, type PlaywrightWorkerArgs } from '@playwright/test';
import {
  createBookViaApi,
  createPhysicalBookViaApi,
  deleteAllAnnotations,
  deleteBookViaApi,
  loginAsAdmin,
  loginViaApi,
  setBookVisibility,
  setLending,
} from '../support/api';
import { requireEnv } from '../support/env';
import { buildReaderPdf, buildSamplePdf } from '../support/samplePdf';
import { buildTestUser, uniqueBookTitle, type TestUser } from '../support/testData';
import { LoginPage } from '../pages/LoginPage';

/**
 * Signs up a user through the real API (not the UI — the signup *flow* itself
 * has its own dedicated spec) so other specs can assume a valid account
 * without re-testing signup every time.
 *
 * There's no self-service "delete my account" API, so these accounts are not
 * cleaned up afterwards — they're tagged with the e2e.e-lib.test domain
 * (see support/testData.ts) so they're easy to identify and purge separately
 * from the app/database side if that's ever needed. The suite itself only
 * ever talks to the app's HTTP/UI surface, never the database.
 */
async function registerViaApi(request: APIRequestContext, user: TestUser): Promise<void> {
  const response = await request.post('/api/v1/signup', {
    data: { username: user.username, email: user.email, password: user.password },
  });
  expect(response.ok(), `signup API setup call failed: ${await response.text()}`).toBeTruthy();
}

/** Logs in through the real /login page and waits for the post-login redirect to settle. */
async function loginViaUi(
  page: Page,
  credentials: { email: string; password: string; username?: string },
): Promise<void> {
  const login = new LoginPage(page);
  await login.open();
  await login.login(credentials.email, credentials.password);
  // LoginForm redirects to '/' on success (no `redirect` query param was set here) —
  // check the actual signal of a successful login (the nav's user chip), not just the URL.
  // The default 5s expect timeout is too tight for this specific redirect under heavy
  // parallel load against the single-threaded `php -S` dev server (observed flaking here,
  // never in the login flow's own dedicated tests) — every other assertion in this suite
  // keeps the default.
  await expect(page).toHaveURL('/', { timeout: 15_000 });
  await expect(page.getByTestId('nav-user-chip')).toBeVisible();
  // When we know who should be logged in, confirm it's actually them and not
  // just "someone" — the chip alone doesn't prove that.
  if (credentials.username) {
    await expect(page.getByTestId('nav-username')).toHaveText(credentials.username);
  }
}

interface AdminCredentials {
  email: string;
  password: string;
}

/**
 * A logged-in reader: their page, account, and their own API client + token for setup and
 * cross-checks as them. The API client is per reader on purpose: logging in through a shared
 * one would leave the last user's PHP session cookie on it, and the API trusts that cookie
 * over the Bearer token (AuthenticatedUser::id()), so calls would act as the wrong user.
 */
export interface ReaderSession {
  page: Page;
  user: TestUser;
  api: APIRequestContext;
  token: string;
}

interface Fixtures {
  registeredUser: TestUser;
  adminCredentials: AdminCredentials;
  authenticatedPage: Page;
  adminPage: Page;
  seededBook: { id: string; title: string };
  readerBook: { id: string; title: string };
  reader: ReaderSession;
  otherReader: ReaderSession;
  borrower: ReaderSession;
  secondBorrower: ReaderSession;
  lentBook: { id: string; title: string };
  physicalBook: { id: string; title: string };
}

/**
 * A freshly signed-up, logged-in reader in their own browser context, for the lending specs.
 * Unlike readerFixture there's nothing to clean up per user: their loans go with the book fixture
 * (deleting a book deletes its loans and waitlist).
 */
function borrowerFixture(prefix: string) {
  return async (
    { browser, playwright, baseURL }: { browser: Browser; playwright: PlaywrightWorkerArgs['playwright']; baseURL: string | undefined },
    use: (session: ReaderSession) => Promise<void>,
  ): Promise<void> => {
    const user = buildTestUser(prefix);
    const api = await playwright.request.newContext({ baseURL });
    await registerViaApi(api, user);
    const context = await browser.newContext({ baseURL });
    const page = await context.newPage();
    await loginViaUi(page, user);
    const { token } = await loginViaApi(api, user.email, user.password);

    await use({ page, user, api, token });

    await context.close();
    await api.dispose();
  };
}

/**
 * A logged-in reader of readerBook, signed up fresh and in its own browser context (so two
 * users can be open at once). Their highlights/notes on it are removed afterwards through the
 * real annotations API (the account itself stays, like every other e2e user — there's no
 * delete-account API).
 */
function readerFixture(prefix: string) {
  return async (
    {
      browser,
      playwright,
      baseURL,
      readerBook,
    }: {
      browser: Browser;
      playwright: PlaywrightWorkerArgs['playwright'];
      baseURL: string | undefined;
      readerBook: { id: string };
    },
    use: (session: ReaderSession) => Promise<void>,
  ): Promise<void> => {
    const user = buildTestUser(prefix);
    const api = await playwright.request.newContext({ baseURL });
    await registerViaApi(api, user);
    const context = await browser.newContext({ baseURL });
    const page = await context.newPage();
    await loginViaUi(page, user);
    const { token } = await loginViaApi(api, user.email, user.password);

    await use({ page, user, api, token });

    await deleteAllAnnotations(api, token, readerBook.id);
    await context.close();
    await api.dispose();
  };
}

export const test = base.extend<Fixtures>({
  registeredUser: async ({ request }, use) => {
    const user = buildTestUser();
    await registerViaApi(request, user);
    await use(user);
  },

  // The app has no self-service way to become an admin (by design — see
  // AuthenticatedUser::isAdmin()), so this is not something the suite can set
  // up for itself. Provision one out-of-band and point the suite at it:
  //   composer admin:create -- <email> <password>
  //   QA_ADMIN_EMAIL=<email> QA_ADMIN_PASSWORD=<password> npm run test:e2e
  adminCredentials: async ({}, use) => {
    const email = requireEnv(
      'QA_ADMIN_EMAIL',
      "Provision an admin first: composer admin:create -- <email> <password>, then set QA_ADMIN_EMAIL/QA_ADMIN_PASSWORD.",
    );
    const password = requireEnv(
      'QA_ADMIN_PASSWORD',
      "Provision an admin first: composer admin:create -- <email> <password>, then set QA_ADMIN_EMAIL/QA_ADMIN_PASSWORD.",
    );
    await use({ email, password });
  },

  authenticatedPage: async ({ page, registeredUser }, use) => {
    await loginViaUi(page, registeredUser);
    await use(page);
  },

  adminPage: async ({ page, request, adminCredentials }, use) => {
    // Verify the env-provided account is actually an admin *before* touching the
    // UI — loginAsAdmin already gives a clear, actionable error if it isn't;
    // without this, a misconfigured QA_ADMIN_EMAIL would only surface later as a
    // confusing "dashboard-heading not found" inside whatever test runs next.
    await loginAsAdmin(request, adminCredentials.email, adminCredentials.password);
    await loginViaUi(page, adminCredentials);
    await use(page);
  },

  // Creates a book through the real /api/v1/books upload + status/featured
  // toggle endpoints — the same ones the dashboard UI uses — rather than
  // inserting into MongoDB directly.
  seededBook: async ({ request, adminCredentials }, use) => {
    const token = await loginAsAdmin(request, adminCredentials.email, adminCredentials.password);
    const title = uniqueBookTitle();
    const id = await createBookViaApi(request, token, { title, pdf: buildSamplePdf(title) });
    await setBookVisibility(request, token, id, { status: 'public', featured: true });

    await use({ id, title });

    await deleteBookViaApi(request, token, id);
  },

  // A public, multi-page text book for the reader specs (search, navigation,
  // highlights, notes) — see READER_PDF in support/samplePdf.ts for its contents.
  readerBook: async ({ request, adminCredentials }, use) => {
    const token = await loginAsAdmin(request, adminCredentials.email, adminCredentials.password);
    const title = uniqueBookTitle('QA Reader Book');
    const id = await createBookViaApi(request, token, { title, pdf: buildReaderPdf() });
    await setBookVisibility(request, token, id, { status: 'public' });

    await use({ id, title });

    await deleteBookViaApi(request, token, id);
  },

  reader: readerFixture('qa_reader'),

  borrower: borrowerFixture('qa_borrower'),
  secondBorrower: borrowerFixture('qa_waiting'),

  // A public PDF lent with ONE copy, so a second reader always finds it taken. Built from the
  // reader PDF so ReaderPage.open() recognises it.
  lentBook: async ({ request, adminCredentials }, use) => {
    const token = await loginAsAdmin(request, adminCredentials.email, adminCredentials.password);
    const title = uniqueBookTitle('QA Lent Book');
    const id = await createBookViaApi(request, token, { title, pdf: buildReaderPdf() });
    await setBookVisibility(request, token, id, { status: 'public' });
    await setLending(request, token, id, { loan_copies: 1, loan_days: 14 });

    await use({ id, title });

    await deleteBookViaApi(request, token, id);
  },

  // A public physical book (no PDF) with ONE copy on the shelf.
  physicalBook: async ({ request, adminCredentials }, use) => {
    const token = await loginAsAdmin(request, adminCredentials.email, adminCredentials.password);
    const title = uniqueBookTitle('QA Shelf Book');
    const id = await createPhysicalBookViaApi(request, token, { title, copies: 1 });

    await use({ id, title });

    await deleteBookViaApi(request, token, id);
  },

  // A second, unrelated reader of the same book — for privacy checks.
  otherReader: readerFixture('qa_other'),
});

export { expect } from '@playwright/test';
