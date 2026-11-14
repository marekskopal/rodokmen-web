// Login, the redirect to it and logout – without the saved login cookie.
import { rmSync } from 'node:fs';
import { expect, login, password, test } from './support.mjs';

test.use({ storageState: { cookies: [], origins: [] } });

test('a page asks for the login and returns to it afterwards', async ({ page }) => {
	await page.goto('/generace');
	await expect(page).toHaveURL(/\/prihlaseni\?k=%2Fgenerace$/);

	await page.getByPlaceholder('Heslo').fill(password);
	await page.getByRole('button', { name: 'Vstoupit' }).click();
	await expect(page).toHaveURL('/generace');
});

test('a wrong password shows an error', async ({ page, consoleErrors }) => {
	try {
		await page.goto('/prihlaseni');
		await page.getByPlaceholder('Heslo').fill('spatne-heslo');
		await page.getByRole('button', { name: 'Vstoupit' }).click();

		await expect(page.getByRole('alert')).toBeVisible();
		await expect(page).toHaveURL(/\/prihlaseni/);
	} finally {
		// Forget the failure: a reused server (make e2e-server) would otherwise lock 127.0.0.1 out of the login after
		// five runs within 15 minutes (LoginThrottle).
		rmSync(new URL('../../var/e2e/cache/throttle', import.meta.url), { recursive: true, force: true });
	}
	// The form answers with an error status, which Chromium reports as a console error.
	consoleErrors.splice(0, consoleErrors.length, ...consoleErrors.filter((e) => !/status of 40\d/.test(e)));
});

test('logout ends the login', async ({ page }) => {
	await login(page);
	await expect(page).toHaveURL('/');

	await page.getByRole('button', { name: 'Odhlásit' }).click();
	await expect(page).toHaveURL('/prihlaseni');

	await page.goto('/');
	await expect(page).toHaveURL(/\/prihlaseni/);
});

test('the health check needs no login', async ({ request }) => {
	expect((await request.get('/health')).ok()).toBe(true);
});
