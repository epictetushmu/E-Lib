import type { Locator, Page } from '@playwright/test';
import { BasePage } from './BasePage';

/**
 * /login — a dedicated, guest-only page (frontend/src/views/Login.vue), not a
 * popup. The `guestOnly` router guard bounces an already-authenticated
 * visitor to '/' before this ever renders.
 */
export class LoginPage extends BasePage {
  readonly form: Locator;
  readonly email: Locator;
  readonly password: Locator;
  readonly submit: Locator;
  readonly errorMessage: Locator;

  constructor(page: Page) {
    super(page);
    this.form = page.getByTestId('login-form');
    this.email = page.getByTestId('login-email');
    this.password = page.getByTestId('login-password');
    this.submit = page.getByTestId('login-submit');
    this.errorMessage = page.getByTestId('login-error');
  }

  async open(): Promise<void> {
    await this.page.goto('/login');
  }

  async login(email: string, password: string): Promise<void> {
    await this.email.fill(email);
    await this.password.fill(password);
    await this.submit.click();
  }
}
