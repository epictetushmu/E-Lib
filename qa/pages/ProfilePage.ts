import type { Locator, Page } from '@playwright/test';
import { BasePage } from './BasePage';
import { HeaderComponent } from './HeaderComponent';

/**
 * /profile (frontend/src/views/Profile.vue) — a tabbed page, not the old
 * dropdown-menu-triggered modals. Username/password editing live inline
 * under the "Account" tab.
 */
export class ProfilePage extends BasePage {
  readonly header: HeaderComponent;
  readonly usernameHeading: Locator;
  readonly emailText: Locator;
  readonly accountTab: Locator;
  readonly downloadedTab: Locator;
  readonly loansTab: Locator;
  readonly notificationsTab: Locator;
  readonly loanItems: Locator;
  readonly notificationItems: Locator;

  readonly usernameInput: Locator;
  readonly saveUsernameButton: Locator;
  readonly usernameError: Locator;

  readonly currentPasswordInput: Locator;
  readonly newPasswordInput: Locator;
  readonly confirmNewPasswordInput: Locator;
  readonly updatePasswordButton: Locator;
  readonly changePasswordFeedback: Locator;

  constructor(page: Page) {
    super(page);
    this.header = new HeaderComponent(page);
    this.usernameHeading = page.getByTestId('profile-username-heading');
    this.emailText = page.getByTestId('profile-email');
    this.accountTab = page.getByTestId('profile-tab-account');
    this.downloadedTab = page.getByTestId('profile-tab-downloaded');
    this.loansTab = page.getByTestId('profile-tab-loans');
    this.notificationsTab = page.getByTestId('profile-tab-notifications');
    this.loanItems = page.getByTestId('my-loans').getByRole('listitem');
    this.notificationItems = page.getByTestId('notifications').getByRole('listitem');

    this.usernameInput = page.getByTestId('profile-username-input');
    this.saveUsernameButton = page.getByTestId('profile-save-username');
    this.usernameError = page.getByTestId('profile-username-error');

    this.currentPasswordInput = page.getByTestId('profile-current-password');
    this.newPasswordInput = page.getByTestId('profile-new-password');
    this.confirmNewPasswordInput = page.getByTestId('profile-confirm-new-password');
    this.updatePasswordButton = page.getByTestId('profile-update-password');
    this.changePasswordFeedback = page.getByTestId('profile-password-feedback');
  }

  async open(): Promise<void> {
    await this.page.goto('/profile');
  }

  async openEditUsername(newUsername: string): Promise<void> {
    await this.accountTab.click();
    await this.usernameInput.fill(newUsername);
    await this.saveUsernameButton.click();
  }

  async changePassword(current: string, next: string): Promise<void> {
    await this.accountTab.click();
    await this.currentPasswordInput.fill(current);
    await this.newPasswordInput.fill(next);
    await this.confirmNewPasswordInput.fill(next);
    await this.updatePasswordButton.click();
  }

  downloadedBookById(bookId: string): Locator {
    return this.page.getByTestId(`book-card-${bookId}`);
  }
}
