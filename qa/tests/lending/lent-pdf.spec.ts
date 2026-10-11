import { borrowViaApi, fileStatus, getLoanStatus } from '../../support/api';
import { expect, test } from '../../fixtures';
import { BookDetailPage } from '../../pages/BookDetailPage';
import { ProfilePage } from '../../pages/ProfilePage';
import { ReaderPage } from '../../pages/ReaderPage';

// A PDF lent with a copy limit (loan_copies > 0) can only be read while borrowed and never
// downloaded. The access rule lives in BookController::streamBookFile (/file, the reader's PDF
// source) — so every test cross-checks that endpoint directly, not just the buttons the UI shows.
//
// Time-driven behaviour (a hold lapsing, the loan ending at its due date) needs the scheduler and
// a clock, so it's covered by the PHPUnit suite (LoansTest / BookServiceTest), not here.

test.describe('Borrowing a lent PDF', () => {
  test('the PDF only reaches a reader while they hold a copy', async ({ borrower, lentBook, playwright, baseURL }) => {
    const { page, api, token } = borrower;
    // A fresh context: the shared `request` fixture carries the admin session the book fixture logged in with.
    const anonymous = await playwright.request.newContext({ baseURL });
    expect(await fileStatus(anonymous, lentBook.id)).toBe(401);
    await anonymous.dispose();
    expect(await fileStatus(api, lentBook.id, token)).toBe(403);
    const download = await api.get(`/api/v1/books/${lentBook.id}/download`, { headers: { Authorization: `Bearer ${token}` } });
    expect(download.status()).toBe(403);

    const detail = new BookDetailPage(page);
    await detail.openBook(lentBook.id);
    await expect(detail.loanState('open')).toHaveText('1 of 1 copy free');
    await expect(detail.previewButton).toHaveCount(0);
    await expect(detail.downloadButton).toHaveCount(0);

    // Going straight to the reader without a loan explains what to do instead of a raw 403.
    const reader = new ReaderPage(page);
    await page.goto(`/read/${lentBook.id}`);
    await expect(reader.errorMessage).toContainText('borrow it');

    await detail.openBook(lentBook.id);
    await detail.borrowButton.click();
    await expect(detail.loanState('out')).toContainText('Yours until');
    expect(await fileStatus(api, lentBook.id, token)).toBe(200);

    await detail.readLoanButton.click();
    await expect(reader.pageLine('Chapter 1')).toBeVisible({ timeout: 20_000 });
    // Printing is "Save as PDF", which would outlive the loan.
    await expect(reader.printButton).toHaveCount(0);

    await detail.openBook(lentBook.id);
    await detail.returnButton.click();
    await expect(detail.loanState('open')).toBeVisible();
    expect(await fileStatus(api, lentBook.id, token)).toBe(403);
  });

  test('when the only copy is out, the next reader queues and gets it on return', async ({
    borrower,
    secondBorrower,
    lentBook,
  }) => {
    expect(await borrowViaApi(borrower.api, borrower.token, lentBook.id)).toBe(200);

    const waiting = new BookDetailPage(secondBorrower.page);
    await waiting.openBook(lentBook.id);
    await expect(waiting.loanState('full')).toHaveText('All copies are out');
    // The API refuses too: no taking the copy past the queue.
    expect(await borrowViaApi(secondBorrower.api, secondBorrower.token, lentBook.id)).toBe(409);

    await waiting.joinWaitlistButton.click();
    await expect(waiting.loanState('queued')).toHaveText("You're number 1 in line");

    const holder = new BookDetailPage(borrower.page);
    await holder.openBook(lentBook.id);
    await holder.returnButton.click();
    await expect(holder.loanState('full')).toBeVisible();

    // Returning hands the copy straight to the head of the line as a hold.
    await waiting.openBook(lentBook.id);
    await expect(waiting.loanState('held')).toContainText('Held for you until');
    await waiting.startReadingButton.click();
    await expect(waiting.loanState('out')).toBeVisible();
    expect(await fileStatus(secondBorrower.api, lentBook.id, secondBorrower.token)).toBe(200);

    const profile = new ProfilePage(secondBorrower.page);
    await profile.open();
    await profile.notificationsTab.click();
    await expect(profile.notificationItems.filter({ hasText: lentBook.title }).first()).toContainText('is held for you');
  });

  test('a loan renews twice, and not while someone is waiting', async ({ borrower, secondBorrower, lentBook }) => {
    const detail = new BookDetailPage(borrower.page);
    await detail.openBook(lentBook.id);
    await detail.borrowButton.click();
    await expect(detail.loanState('out')).toBeVisible();

    await detail.renewButton.click();
    await expect.poll(async () => (await getLoanStatus(borrower.api, lentBook.id, borrower.token))?.renewals_left).toBe(1);
    await detail.renewButton.click();
    await expect.poll(async () => (await getLoanStatus(borrower.api, lentBook.id, borrower.token))?.renewals_left).toBe(0);
    await expect(detail.renewButton).toHaveCount(0);

    // A fresh loan for the queue check: return, re-borrow, and let someone join the line.
    await detail.returnButton.click();
    await detail.borrowButton.click();
    await expect(detail.renewButton).toBeVisible();
    const join = await secondBorrower.api.post(`/api/v1/books/${lentBook.id}/waitlist`, {
      headers: { Authorization: `Bearer ${secondBorrower.token}` },
    });
    expect(join.ok()).toBeTruthy();

    const renew = await borrower.api.post(`/api/v1/books/${lentBook.id}/renew`, {
      headers: { Authorization: `Bearer ${borrower.token}` },
    });
    expect(renew.status()).toBe(409);
    expect(await renew.text()).toContain('Someone is waiting');
  });
});
