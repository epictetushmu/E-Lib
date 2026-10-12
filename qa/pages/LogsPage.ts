import type { Locator, Page } from '@playwright/test';
import { BasePage } from './BasePage';

/**
 * /admin/logs — server error/request log viewer (frontend/src/views/admin/Logs.vue), fed by
 * GET /api/v1/admin/logs. Guarded client-side by `requireAdmin` and server-side by
 * UserController::viewLogs (401 without a token, 403 for a non-admin).
 */
export class LogsPage extends BasePage {
  readonly heading: Locator;
  readonly errorLogsTab: Locator;
  readonly requestLogsTab: Locator;
  readonly loadError: Locator;

  constructor(page: Page) {
    super(page);
    this.heading = page.getByRole('heading', { name: 'System Logs' });
    this.errorLogsTab = page.getByRole('button', { name: 'Error Logs' });
    this.requestLogsTab = page.getByRole('button', { name: 'Request Logs' });
    this.loadError = page.locator('.logs-page .alert-danger');
  }

  async open(): Promise<void> {
    await this.page.goto('/admin/logs');
  }
}
