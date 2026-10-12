import { buildTestUser } from '../../support/testData';
import { expect, test } from '../../fixtures';
import { SignupPage } from '../../pages/SignupPage';
import { LoginPage } from '../../pages/LoginPage';

// Accounts created below are not deleted afterwards — there's no self-service
// "delete my account" API, and this suite only ever talks to the app's HTTP/UI
// surface, never the database directly. They're tagged with the
// e2e.e-lib.test domain (support/testData.ts) so they're easy to spot.
//
// /signup is a dedicated, guest-only page (frontend/src/views/Signup.vue)
// rendering a single SignupForm instance — unlike the pre-SPA app, there's no
// duplicate-#signupForm bug here to work around.

test.describe('Signup', () => {
  test('a new user can sign up and is prompted to log in', async ({ page }) => {
    const user = buildTestUser();
    const signup = new SignupPage(page);
    await signup.open();

    await signup.signUp(user);

    await expect(signup.successMessage).toContainText('Account created successfully');
    // SignupForm redirects to /login ~1.2s after a successful signup.
    await expect(page).toHaveURL('/login', { timeout: 5_000 });

    // Cross-check against the server, not just the toast: the account must
    // actually work, not merely have shown a success message.
    const login = new LoginPage(page);
    await login.login(user.email, user.password);
    await expect(page).toHaveURL('/');
    await expect(page.getByTestId('nav-username')).toHaveText(user.username);
  });

  test('rejects mismatched passwords before calling the API', async ({ page }) => {
    const user = buildTestUser();
    const signup = new SignupPage(page);
    await signup.open();

    await signup.username.fill(user.username);
    await signup.email.fill(user.email);
    await signup.password.fill(user.password);
    await signup.confirmPassword.fill(`different-${user.password}`);
    await signup.terms.check();
    await signup.submit.click();

    await expect(signup.errorMessage).toContainText('Passwords do not match');
    // Client-side validation should short-circuit — no account should be created.
    await expect(page).toHaveURL('/signup');
  });

  test('requires agreeing to the Terms of Service', async ({ page }) => {
    const user = buildTestUser();
    const signup = new SignupPage(page);
    await signup.open();

    await signup.username.fill(user.username);
    await signup.email.fill(user.email);
    await signup.password.fill(user.password);
    await signup.confirmPassword.fill(user.password);
    // The checkbox has the native "required" attribute, so the browser blocks
    // the submit event before the app's own JS (which also checks it) ever runs.
    await signup.submit.click();

    const isValid = await signup.terms.evaluate((el: HTMLInputElement) => el.validity.valid);
    expect(isValid).toBe(false);
    await expect(page).toHaveURL('/signup'); // nothing was submitted
  });
});
