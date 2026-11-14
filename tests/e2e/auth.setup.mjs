// Logs in once and saves the login cookie for the tests of the "chromium" project (playwright.config.mjs).
import { expect, login, siteTitle, test } from './support.mjs';

test('log in', async ({ page }) => {
	await login(page);
	await expect(page).toHaveURL('/');
	await expect(page).toHaveTitle(new RegExp(siteTitle));
	await page.context().storageState({ path: 'var/e2e-auth.json' });
});
