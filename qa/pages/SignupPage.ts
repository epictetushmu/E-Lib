import type { Locator, Page } from '@playwright/test';
import { BasePage } from './BasePage';

/**
 * /signup — a dedicated, guest-only page (frontend/src/views/Signup.vue), not
 * a popup. Renders a single SignupForm instance, so (unlike the pre-SPA app)
 * there's no duplicate-id bug to work around here.
 */
export class SignupPage extends BasePage {
  readonly username: Locator;
  readonly email: Locator;
  readonly password: Locator;
  readonly confirmPassword: Locator;
  readonly terms: Locator;
  readonly submit: Locator;
  readonly errorMessage: Locator;
  readonly successMessage: Locator;

  constructor(page: Page) {
    super(page);
    this.username = page.getByTestId('signup-username');
    this.email = page.getByTestId('signup-email');
    this.password = page.getByTestId('signup-password');
    this.confirmPassword = page.getByTestId('signup-confirm');
    this.terms = page.getByTestId('signup-terms');
    this.submit = page.getByTestId('signup-submit');
    this.errorMessage = page.getByTestId('signup-error');
    this.successMessage = page.getByTestId('signup-success');
  }

  async open(): Promise<void> {
    await this.page.goto('/signup');
  }

  async signUp(input: { username: string; email: string; password: string }): Promise<void> {
    await this.username.fill(input.username);
    await this.email.fill(input.email);
    await this.password.fill(input.password);
    await this.confirmPassword.fill(input.password);
    await this.terms.check();
    await this.submit.click();
  }
}
