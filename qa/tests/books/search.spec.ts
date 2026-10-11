import { createBookViaApi, deleteBookViaApi, loginAsAdmin, setBookVisibility } from '../../support/api';
import { buildSamplePdf } from '../../support/samplePdf';
import { uniqueBookTitle } from '../../support/testData';
import { expect, test } from '../../fixtures';
import { SearchResultsPage } from '../../pages/SearchResultsPage';

// Was a known bug: GET /api/v1/search/:term was routed with `(\w+)`, which
// doesn't match spaces — so any multi-word title 404'd and the frontend
// showed a generic "An error occurred" message instead of real results or a
// clean "no results" message. Fixed in App/Router/ApiRouter.php (route now
// captures the whole segment) and App/Controllers/BookController::searchBooks
// (urldecodes it before querying, and no longer 404s on zero matches). These
// tests cover multi-word terms specifically to guard against a regression.

test.describe('Book search', () => {
  test('searching by a multi-word title shows matching results', async ({ page, request, seededBook, adminCredentials }) => {
    // A second, unrelated public book — proves the search actually filters by
    // term instead of just listing every public book (which would also make
    // the seeded book "visible" for the wrong reason).
    const token = await loginAsAdmin(request, adminCredentials.email, adminCredentials.password);
    const otherTitle = uniqueBookTitle('QA Unrelated Book');
    const otherId = await createBookViaApi(request, token, { title: otherTitle, pdf: buildSamplePdf(otherTitle) });
    await setBookVisibility(request, token, otherId, { status: 'public' });

    try {
      const results = new SearchResultsPage(page);
      await results.openWithTerm(seededBook.title);

      await expect(results.cardById(seededBook.id)).toBeVisible();
      await expect(results.cardById(otherId)).toHaveCount(0);
    } finally {
      await deleteBookViaApi(request, token, otherId);
    }
  });

  test('filters catalog results by subject and finds books by author', async ({ page, request, adminCredentials }) => {
    const token = await loginAsAdmin(request, adminCredentials.email, adminCredentials.password);
    // Unique subjects and author, so books other tests leave behind can't change the counts.
    const tag = uniqueBookTitle('QA Facet');
    const [subjectA, subjectB, author] = [`${tag} Optics`, `${tag} Botany`, `${tag} Author`];
    const titleA = `${tag} Lenses`;
    const titleB = `${tag} Ferns`;
    const idA = await createBookViaApi(request, token, { title: titleA, author, categories: [subjectA], pdf: buildSamplePdf(titleA) });
    const idB = await createBookViaApi(request, token, { title: titleB, author, categories: [subjectB], pdf: buildSamplePdf(titleB) });
    await setBookVisibility(request, token, idA, { status: 'public' });
    await setBookVisibility(request, token, idB, { status: 'public' });

    try {
      const results = new SearchResultsPage(page);
      // The author's name matches both books, though neither title contains it.
      await results.openWithTerm(author);
      await expect(results.cardById(idA)).toBeVisible();
      await expect(results.cardById(idB)).toBeVisible();

      await page.getByTestId(`facet-category-${subjectA}`).check();
      await expect(results.cardById(idB)).toHaveCount(0);
      await expect(results.cardById(idA)).toBeVisible();
      await expect(page).toHaveURL(/category=/);
      // The other subject still shows how many books picking it would add.
      await expect(page.getByTestId(`facet-category-${subjectB}`).locator('xpath=..')).toContainText('1');

      await page.getByTestId('clear-filters').click();
      await expect(results.cardById(idB)).toBeVisible();
    } finally {
      await deleteBookViaApi(request, token, idA);
      await deleteBookViaApi(request, token, idB);
    }
  });

  test('shows a "no results" message for an unmatched multi-word search', async ({ page }) => {
    const results = new SearchResultsPage(page);
    const term = 'this title definitely does not exist anywhere';
    await results.openWithTerm(term);

    // Confirms the message actually reflects what was searched, not just a
    // generic substring that would pass for any term.
    await expect(results.message).toContainText(`No books found for "${term}"`);
  });
});
