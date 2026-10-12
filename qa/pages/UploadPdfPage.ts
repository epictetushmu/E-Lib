import type { Locator, Page } from '@playwright/test';
import { BasePage } from './BasePage';
import { HeaderComponent } from './HeaderComponent';

/**
 * /admin/upload — the only book-upload flow in the SPA
 * (frontend/src/views/admin/UploadPdf.vue). There's no single-book "add
 * book" form anymore; even a single PDF goes through this uploader, which
 * also accepts multiple files at once.
 * Admin-only, guarded the same way as /admin (see DashboardPage).
 */
export class UploadPdfPage extends BasePage {
  readonly header: HeaderComponent;
  readonly fileInput: Locator;
  readonly uploadButton: Locator;
  readonly feedback: Locator;

  constructor(page: Page) {
    super(page);
    this.header = new HeaderComponent(page);
    this.fileInput = page.getByTestId('file-input');
    this.uploadButton = page.getByTestId('upload-button');
    this.feedback = page.getByTestId('upload-feedback');
  }

  async open(): Promise<void> {
    await this.page.goto('/admin/upload');
  }

  titleInput(index: number): Locator {
    return this.page.getByTestId(`file-title-input-${index}`);
  }

  authorInput(index: number): Locator {
    return this.page.getByTestId(`file-author-input-${index}`);
  }

  /** Uploads one PDF. */
  async uploadOne(input: { title: string; author?: string; pdf: Buffer; fileName?: string }): Promise<void> {
    await this.uploadMany([input]);
  }

  /** Uploads several PDFs in one go — files are appended in order, so row N matches input N. */
  async uploadMany(inputs: { title: string; author?: string; pdf: Buffer; fileName?: string }[]): Promise<void> {
    await this.fileInput.setInputFiles(
      inputs.map((input, index) => ({
        name: input.fileName ?? `sample-${index}.pdf`,
        mimeType: 'application/pdf',
        buffer: input.pdf,
      })),
    );

    for (const [index, input] of inputs.entries()) {
      await this.titleInput(index).fill(input.title);
      if (input.author) {
        await this.authorInput(index).fill(input.author);
      }
    }

    await this.uploadButton.click();
  }
}
