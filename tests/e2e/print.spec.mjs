// Printing: the numbered list of ancestors (/predci/{slug}/tisk) and the print stylesheet.
import { expect, test } from './support.mjs';

test('the explorer leads to the numbered list of ancestors', async ({ page }) => {
	await page.goto('/predci');
	const name = (await page.locator('h1').first().textContent()).trim().split('\n')[0].trim();
	await page.getByRole('link', { name: 'Vytisknout vývod' }).click();

	await expect(page).toHaveURL(/\/predci\/.+\/tisk$/);
	await expect(page.locator('h1')).toContainText(name);
	await expect(page.locator('#c-1')).toBeVisible();
	await expect(page.locator('#c-2 .anc-num')).toHaveText('2');
});

test('the number of generations and the transcriptions can be chosen', async ({ page }) => {
	await page.goto('/predci');
	await page.getByRole('link', { name: 'Vytisknout vývod' }).click();
	// Wait for the list: counting right after the click could catch the page before it is drawn.
	await expect(page.locator('#c-1')).toBeVisible();
	const all = await page.locator('.anc-person').count();

	await page.getByLabel('Generací').selectOption('1');
	await expect(page).toHaveURL(/generace=1/);
	await expect(page.locator('.anc-person')).toHaveCount(3);
	expect(all).toBeGreaterThan(3);

	await expect(page.locator('.lead')).toContainText('1 generace předků');
	await page.getByLabel('s přepisy zápisů').check();
	await expect(page).toHaveURL(/zapisy=1/);
});

test('on paper there is no navigation, no controls and light colours', async ({ page }) => {
	await page.emulateMedia({ colorScheme: 'dark' });
	await page.goto('/predci');
	await page.getByRole('link', { name: 'Vytisknout vývod' }).click();
	await expect(page.locator('.print-toolbar')).toBeVisible();

	await page.emulateMedia({ media: 'print', colorScheme: 'dark' });
	await expect(page.locator('.top')).toBeHidden();
	await expect(page.locator('.print-toolbar')).toBeHidden();
	await expect(page.locator('.anc-person').first()).toBeVisible();
	const background = await page.evaluate(() => getComputedStyle(document.body).backgroundColor);
	expect(background).toBe('rgb(255, 255, 255)');
});

test('the person page prints with its transcriptions open', async ({ page }) => {
	await page.goto('/zapis/litovel_krest_josef_skopal_1921');
	const person = page.locator('.chips a').first();
	await person.click();
	await expect(page.getByRole('button', { name: 'Tisk / PDF' })).toBeVisible();
	await expect(page.locator('details.record-text').first()).not.toHaveAttribute('open');

	await page.evaluate(() => window.dispatchEvent(new Event('beforeprint')));
	await expect(page.locator('details.record-text').first()).toHaveAttribute('open');
	await page.emulateMedia({ media: 'print' });
	await expect(page.locator('.person-actions')).toBeHidden();
	await expect(page.locator('.minimap')).toBeHidden();

	await page.evaluate(() => window.dispatchEvent(new Event('afterprint')));
	await expect(page.locator('details.record-text').first()).not.toHaveAttribute('open');
});

test('Chromium saves the list as a PDF', async ({ page }) => {
	await page.goto('/predci');
	await page.getByRole('link', { name: 'Vytisknout vývod' }).click();
	// Wait for the list: printing right after the click could catch the page before it is drawn.
	await expect(page.locator('#c-1')).toBeVisible();
	const pdf = await page.pdf({ format: 'A4' });

	expect(pdf.subarray(0, 5).toString()).toBe('%PDF-');
	expect(pdf.length).toBeGreaterThan(5000);
});
