// Every main page answers, shows its heading and runs its scripts without an error.
import { expect, samplePages, siteTitle, test } from './support.mjs';

const pages = [
	['/', 'home'],
	['/predci', 'ancestor explorer'],
	['/strom', 'full tree'],
	['/vejir', 'fan chart'],
	['/predci/marek-skopal/plakat', 'ancestor poster'],
	['/generace', 'generations'],
	['/mapa', 'map'],
	['/zapisy', 'register entries'],
	['/dokumenty', 'photos and documents'],
	['/zajimavosti', 'stories'],
	['/pribeh', 'family story'],
	['/rod/skopalovi', 'family line'],
	['/denik', 'research log'],
	['/hledame', 'open questions'],
	['/hledat', 'search'],
];

for (const [path, name] of pages) {
	test(`${name} (${path})`, async ({ page }) => {
		const response = await page.goto(path);
		expect(response?.status()).toBe(200);
		await expect(page).toHaveTitle(new RegExp(siteTitle));
		await expect(page.locator('h1').first()).not.toBeEmpty();
	});
}

test('person page and register entry', async ({ page }) => {
	const { person, record } = await samplePages(page.request);

	expect((await page.goto(person.u))?.status()).toBe(200);
	await expect(page.locator('h1').first()).toContainText(person.n);

	expect((await page.goto(record.u))?.status()).toBe(200);
	await expect(page.locator('h1').first()).toContainText(record.n);
});

test('the map draws its places', async ({ page }) => {
	await page.goto('/mapa');
	await expect(page.locator('#map.leaflet-container')).toBeVisible();
	await expect(page.locator('#map .leaflet-marker-icon, #map .leaflet-interactive').first()).toBeVisible();
});

test('the fan chart and the tree are drawn', async ({ page }) => {
	await page.goto('/vejir');
	await expect(page.locator('[data-fan-stage] a.fan-p').first()).toBeVisible();

	await page.goto('/strom');
	await expect(page.locator('[data-ft-tree] .pcard, [data-ft-tree] a').first()).toBeVisible();
});

test('an unknown page answers 404', async ({ page, consoleErrors }) => {
	const response = await page.goto('/neexistuje');
	expect(response?.status()).toBe(404);
	// Chromium reports the failed document load itself as a console error.
	consoleErrors.splice(0, consoleErrors.length, ...consoleErrors.filter((e) => !e.includes('404')));
});

test('the family story is in the menu and linked from its line', async ({ page }) => {
	await page.goto('/');
	await page.locator('#mainnav').getByRole('link', { name: 'Příběh Skopalových' }).click();
	await expect(page.locator('h1')).toHaveText('Příběh Skopalových');
	await expect(page.locator('.prose a', { hasText: 'Matouš Skopal' })).toHaveAttribute('href', '/osoba/matous-skopal-1756');

	await page.getByRole('link', { name: /Rod Skopalovi/ }).click();
	await expect(page).toHaveURL(/\/rod\/skopalovi$/);
	await page.getByRole('link', { name: /Přečíst příběh Skopalových/ }).click();
	await expect(page).toHaveURL(/\/pribeh$/);
});
