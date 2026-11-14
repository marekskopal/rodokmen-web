// Basic interactions: search, the ancestor explorer, the theme and the mobile navigation.
import { expect, samplePages, test } from './support.mjs';

test('search finds a person and opens their page', async ({ page }) => {
	const { person } = await samplePages(page.request);
	const surname = person.n.split(' ').pop();

	await page.goto('/hledat');
	await page.getByRole('searchbox', { name: 'Hledat' }).fill(surname.toLowerCase());
	const hit = page.locator(`.search-results a[href="${person.u}"]`);
	await expect(hit).toBeVisible();

	await hit.click();
	await expect(page).toHaveURL(person.u);
});

test('the explorer moves up to a clicked ancestor', async ({ page }) => {
	await page.goto('/predci');
	const father = page.locator('.hg-level-1 a.pcard').first();
	const name = (await father.locator('.pcard-name').textContent()).trim();

	await father.click();
	await expect(page).toHaveURL(/\/predci\/.+/);
	await expect(page.locator('h1')).toContainText(name);
	await expect(page.locator('.trail a.current')).toHaveText(name);
});

test('the chosen theme survives a reload', async ({ page }) => {
	await page.emulateMedia({ colorScheme: 'light' });
	await page.goto('/');
	await page.getByRole('button', { name: 'Světlý / tmavý režim' }).click();
	await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');

	await page.reload();
	await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
});

test.describe('phone', () => {
	test.use({ viewport: { width: 390, height: 780 } });

	test('the menu opens from the button', async ({ page }) => {
		await page.goto('/');
		const nav = page.locator('#mainnav');
		const menu = page.getByRole('button', { name: 'Menu' });
		await expect(menu).toBeVisible();
		await expect(nav.getByRole('link', { name: 'Mapa' })).toBeHidden();

		await menu.click();
		await expect(menu).toHaveAttribute('aria-expanded', 'true');
		await nav.getByRole('link', { name: 'Mapa' }).click();
		await expect(page).toHaveURL('/mapa');
	});
});
