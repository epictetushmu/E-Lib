import { borrowViaApi, deleteBookViaApi, getBookByTitle, bookId, loginAsAdmin } from '../../support/api';
import { uniqueBookTitle } from '../../support/testData';
import { expect, test } from '../../fixtures';
import { BookDetailPage } from '../../pages/BookDetailPage';
import { DashboardPage } from '../../pages/DashboardPage';
import { LoansPage } from '../../pages/LoansPage';
import { ProfilePage } from '../../pages/ProfilePage';

// Physical books exist only on the library's shelves: readers reserve online, a librarian hands
// the copy over (Loans page "Picked up", or a walk-in checked out by email from the book page) and
// checks it back in. Each step is cross-checked from the other side — the reader's book page,
// profile Loans/Notifications — not just the page that performed it.
//
// Overdue / due-soon notices and lapsing reservations need the scheduler and a clock; they're
// covered by the PHPUnit suite.

test.describe('Physical books', () => {
  test('an admin catalogues a physical book from the dashboard', async ({ adminPage, request, adminCredentials }) => {
    const token = await loginAsAdmin(request, adminCredentials.email, adminCredentials.password);
    const title = uniqueBookTitle('QA Added Shelf Book');
    const dashboard = new DashboardPage(adminPage);
    await dashboard.open();
    await dashboard.addPhysicalButton.click();

    const dialog = dashboard.addPhysicalDialog;
    await dialog.getByLabel('Title', { exact: true }).fill(title);
    await dialog.getByLabel('Copies on the shelf').fill('2');
    await dialog.getByLabel('Shelf / call number').fill('QA76.9 .D35');
    await dialog.getByRole('button', { name: 'Add book' }).click();
    await expect(dialog).toBeHidden();

    const book = await getBookByTitle(request, title);
    expect(book).toMatchObject({ file_extension: 'physical', downloadable: false });
    const id = bookId(book!);

    try {
      await expect(dashboard.rowById(id)).toContainText('Shelf');

      const detail = new BookDetailPage(adminPage);
      await detail.openBook(id);
      await expect(detail.spineLabel('QA76.9 .D35')).toBeVisible();
      await expect(detail.loanState('open')).toHaveText('2 of 2 on the shelf');
      await expect(detail.previewButton).toHaveCount(0);
      await expect(detail.downloadButton).toHaveCount(0);
      await expect(detail.downloadDisabledButton).toHaveCount(0);
    } finally {
      await deleteBookViaApi(request, token, id);
    }
  });

  test('reserve, pick up and check in — the reader is told at each step', async ({ borrower, adminPage, physicalBook }) => {
    const detail = new BookDetailPage(borrower.page);
    await detail.openBook(physicalBook.id);
    await detail.reserveButton.click();
    await expect(detail.loanState('held')).toContainText('Held for you until');
    await expect(detail.cancelReservationButton).toBeVisible();

    const loans = new LoansPage(adminPage);
    await loans.open();
    await loans.showReader(borrower.user.email);
    const row = loans.rowFor(physicalBook.title);
    await expect(row).toContainText('Held for pickup');
    await loans.action(row, 'Picked up').click();
    await expect(row).toContainText('On loan');

    await detail.openBook(physicalBook.id);
    await expect(detail.loanState('out')).toContainText('Due back');
    // A picked-up copy can only be returned at the desk, in the UI and the API alike.
    await expect(detail.returnButton).toHaveCount(0);
    const selfReturn = await borrower.api.post(`/api/v1/books/${physicalBook.id}/return`, {
      headers: { Authorization: `Bearer ${borrower.token}` },
    });
    expect(selfReturn.status()).toBe(400);

    const profile = new ProfilePage(borrower.page);
    await profile.open();
    await profile.loansTab.click();
    await expect(profile.loanItems.filter({ hasText: physicalBook.title })).toContainText('On loan');

    await loans.action(row, 'Check in').click();
    await expect(row).toContainText('Returned');

    await profile.open();
    await profile.notificationsTab.click();
    const notices = profile.notificationItems.filter({ hasText: physicalBook.title });
    await expect(notices.filter({ hasText: 'You borrowed' })).toHaveCount(1);
    await expect(notices.filter({ hasText: 'was checked back in' })).toHaveCount(1);

    await detail.openBook(physicalBook.id);
    await expect(detail.loanState('open')).toBeVisible();
  });

  test('a cancelled reservation frees the copy', async ({ borrower, adminPage, physicalBook }) => {
    const detail = new BookDetailPage(borrower.page);
    await detail.openBook(physicalBook.id);
    await detail.reserveButton.click();
    await detail.cancelReservationButton.click();
    await expect(detail.loanState('open')).toHaveText('1 of 1 on the shelf');

    const loans = new LoansPage(adminPage);
    await loans.open();
    await loans.showReader(borrower.user.email);
    await expect(loans.rowFor(physicalBook.title)).toContainText('Cancelled');
  });

  test('a walk-in checkout at the desk; on check-in the copy goes to whoever is waiting', async ({
    adminPage,
    borrower,
    secondBorrower,
    physicalBook,
  }) => {
    const desk = new BookDetailPage(adminPage);
    await desk.openBook(physicalBook.id);
    await desk.checkoutEmail.fill(borrower.user.email);
    await desk.checkoutButton.click();
    await expect(desk.loanState('full')).toBeVisible();

    const holder = new BookDetailPage(borrower.page);
    await holder.openBook(physicalBook.id);
    await expect(holder.loanState('out')).toContainText('Due back');

    const waiting = new BookDetailPage(secondBorrower.page);
    await waiting.openBook(physicalBook.id);
    expect(await borrowViaApi(secondBorrower.api, secondBorrower.token, physicalBook.id)).toBe(409);
    await waiting.joinWaitlistButton.click();
    await expect(waiting.loanState('queued')).toHaveText("You're number 1 in line");

    const loans = new LoansPage(adminPage);
    await loans.open();
    await loans.showReader(borrower.user.email);
    await loans.action(loans.rowFor(physicalBook.title), 'Check in').click();
    await expect(loans.rowFor(physicalBook.title)).toContainText('Returned');

    await waiting.openBook(physicalBook.id);
    await expect(waiting.loanState('held')).toContainText('Held for you until');
    const profile = new ProfilePage(secondBorrower.page);
    await profile.open();
    await profile.notificationsTab.click();
    await expect(profile.notificationItems.filter({ hasText: physicalBook.title }).first()).toContainText(
      'waiting for you at the library desk',
    );
  });

  test('readers who are not admins are kept off the Loans page', async ({ authenticatedPage }) => {
    await new LoansPage(authenticatedPage).open();
    await expect(authenticatedPage).toHaveURL('/');
  });
});
