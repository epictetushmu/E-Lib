import { expect, test } from '../../fixtures';
import { HomePage } from '../../pages/HomePage';

test.describe('Home page', () => {
  test('loads and shows the site title', async ({ page }) => {
    const home = new HomePage(page);
    await home.open();

    await expect(page).toHaveTitle('Epictetus Library');
    await expect(home.header.brand).toContainText('Epictetus Library');
  });

  test('primary navigation links to the main sections', async ({ page }) => {
    const home = new HomePage(page);
    await home.open();

    await expect(home.header.homeLink).toHaveAttribute('href', '/');
    await expect(home.header.browseLink).toHaveAttribute('href', '/browse');
  });
});
