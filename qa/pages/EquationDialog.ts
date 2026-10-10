import type { Locator, Page } from '@playwright/test';

/**
 * The visual equation editor (frontend/src/components/EquationDialog.vue), opened from a
 * note editor's "Equation" button. The writing area is MathLive's <math-field>.
 */
export class EquationDialog {
  readonly dialog: Locator;
  readonly field: Locator;
  readonly insert: Locator;

  constructor(private readonly page: Page) {
    this.dialog = page.getByRole('dialog', { name: /^(Equation|Edit equation)$/ });
    this.field = this.dialog.locator('math-field');
    this.insert = this.dialog.getByRole('button', { name: /^(Insert|Update) equation$/ });
  }

  /** A palette key by its name (e.g. "Fraction", "Square root", "pi"). */
  key(name: string): Locator {
    return this.dialog.getByRole('button', { name, exact: true });
  }

  /** Types into the maths field one key at a time, as a person would (1/2 becomes a fraction). */
  async type(keys: string): Promise<void> {
    for (const key of keys) await this.page.keyboard.type(key, { delay: 30 });
  }

  /** The LaTeX the field currently holds. */
  async latex(): Promise<string> {
    return this.field.evaluate((el) => (el as HTMLElement & { value: string }).value);
  }
}
