import { expect, test } from '../../fixtures';
import { ProfilePage } from '../../pages/ProfilePage';
import { LoginPage } from '../../pages/LoginPage';

test.describe('Profile', () => {
  test('shows the logged-in user\'s account info', async ({ authenticatedPage, registeredUser }) => {
    const profile = new ProfilePage(authenticatedPage);
    await profile.open();

    await expect(profile.usernameHeading).toHaveText(registeredUser.username);
    await expect(profile.emailText).toHaveText(registeredUser.email);
  });

  test('can update the username', async ({ authenticatedPage, registeredUser }) => {
    const profile = new ProfilePage(authenticatedPage);
    await profile.open();
    const newUsername = `${registeredUser.username}-renamed`;

    await profile.openEditUsername(newUsername);

    await expect(profile.usernameHeading).toHaveText(newUsername);

    // Cross-check persistence, not just the client's own optimistic state.
    await authenticatedPage.reload();
    await expect(profile.usernameHeading).toHaveText(newUsername);
  });

  test('rejects a too-short username', async ({ authenticatedPage }) => {
    const profile = new ProfilePage(authenticatedPage);
    await profile.open();

    await profile.openEditUsername('ab');

    await expect(profile.usernameError).toContainText('at least 3 characters');
  });

  test('can change the password', async ({ authenticatedPage, registeredUser }) => {
    const profile = new ProfilePage(authenticatedPage);
    await profile.open();
    const newPassword = `${registeredUser.password}-2`;

    await profile.changePassword(registeredUser.password, newPassword);

    await expect(profile.changePasswordFeedback).toContainText('Password updated successfully');

    // Cross-check against the server, not just the toast: log out and prove
    // the new password actually works and the old one no longer does.
    await profile.header.logout();
    const login = new LoginPage(authenticatedPage);
    await login.open();

    await login.login(registeredUser.email, registeredUser.password);
    await expect(login.errorMessage).toHaveText('Invalid credentials');

    await login.login(registeredUser.email, newPassword);
    await expect(authenticatedPage).toHaveURL('/');
    await expect(authenticatedPage.getByTestId('nav-username')).toHaveText(registeredUser.username);
  });

  test('rejects the wrong current password', async ({ authenticatedPage }) => {
    const profile = new ProfilePage(authenticatedPage);
    await profile.open();

    await profile.changePassword('definitely-the-wrong-password', 'NewPassw0rd!');

    // Exact backend copy (App/Controllers/UserController.php) — not just "some error showed".
    await expect(profile.changePasswordFeedback).toHaveText('Current password is incorrect');
  });
});
