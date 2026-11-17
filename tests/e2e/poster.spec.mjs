// Ancestor poster (/predci/{slug}/plakat): choices, print stylesheet and the paper size of the PDF.
import { expect, test } from './support.mjs';

/** Width and height (pt) of every page of a PDF made by Chromium (its page objects are not compressed). */
function pageSizes(pdf) {
	const text = pdf.toString('latin1');
	return [...text.matchAll(/\/MediaBox\s*\[\s*0 0 ([\d.]+) ([\d.]+)\s*\]/g)].map((m) => [Math.round(m[1]), Math.round(m[2])]);
}

async function openPoster(page) {
	await page.goto('/predci');
	await page.locator('.explorer-actions').getByRole('link', { name: 'Plakát předků' }).click();
	await expect(page).toHaveURL(/\/predci\/.+\/plakat$/);
	await expect(page.locator('.poster-sheet svg')).toBeVisible();
}

test('the explorer leads to the poster, which follows the choices', async ({ page }) => {
	await openPoster(page);
	await expect(page.locator('.poster-sheet svg')).toBeVisible();
	await expect(page.locator('.poster-sheet')).toHaveClass(/poster-a1-landscape/);
	await expect(page.locator('.poster-hint')).toContainText('841 × 594 mm');

	await page.getByLabel('Rozvržení').selectOption('pulvejir');
	await page.getByLabel('Papír').selectOption('a3');
	await page.getByLabel('Orientace').selectOption('na-vysku');
	await page.getByLabel('Nadpis').fill('Rod Skopalů');
	await page.getByRole('button', { name: 'Zobrazit' }).click();

	await expect(page).toHaveURL(/rozvrzeni=pulvejir.*format=a3.*orientace=na-vysku/);
	await expect(page.locator('.poster-sheet')).toHaveClass(/poster-a3-portrait/);
	await expect(page.locator('.poster-sheet .pst-title')).toHaveText('Rod Skopalů');
});

test('the tree layouts draw boxes for the ancestors', async ({ page }) => {
	await openPoster(page);
	for (const layout of ['strom', 'strom-vpravo']) {
		await page.getByLabel('Rozvržení').selectOption(layout);
		await page.getByRole('button', { name: 'Zobrazit' }).click();
		await expect(page).toHaveURL(new RegExp(`rozvrzeni=${layout}`));
		await expect(page.locator('.poster-sheet svg rect[rx]').first()).toBeVisible();
		expect(await page.locator('.poster-sheet svg rect[rx]').count()).toBeGreaterThan(10);
	}
	expect(pageSizes(await page.pdf({ preferCSSPageSize: true }))).toEqual([[2384, 1684]]);
});

test('the painted tree grows a trunk, branches and medallions', async ({ page }) => {
	await openPoster(page);
	await page.getByLabel('Rozvržení').selectOption('maleny-strom');
	await page.getByRole('button', { name: 'Zobrazit' }).click();
	await expect(page).toHaveURL(/rozvrzeni=maleny-strom/);
	expect(await page.locator('.poster-sheet svg ellipse').count()).toBeGreaterThan(10);
	await expect(page.locator('.poster-sheet svg text').filter({ hasText: 'Marek Skopal' }).first()).toBeVisible();
});

test('the historical style decorates the poster and the SVG can be downloaded', async ({ page }) => {
	await openPoster(page);
	await page.getByLabel('Styl').selectOption('historicky');
	await page.getByRole('button', { name: 'Zobrazit' }).click();
	await expect(page).toHaveURL(/styl=historicky/);
	await expect(page.locator('.poster-sheet svg rect[fill="url(#pst-paper)"]')).toHaveCount(1);
	// The font of the historical style is self-hosted and loads under the Content-Security-Policy.
	await page.evaluate(() => document.fonts.ready);
	expect(await page.evaluate(() => document.fonts.check('600 12px "EB Garamond"', 'Řehoř Dvořák'))).toBe(true);

	const download = page.waitForEvent('download');
	await page.getByRole('link', { name: 'Stáhnout SVG' }).click();
	const file = await download;
	expect(file.suggestedFilename()).toMatch(/^plakat-.+\.svg$/);
	const svg = (await import('node:fs')).readFileSync(await file.path(), 'utf8');
	expect(svg).toContain('@font-face{font-family:"EB Garamond"');
	expect(svg).toContain('data:font/woff2;base64,');
});

test('on paper there is only the sheet, at its full size', async ({ page }) => {
	await openPoster(page);
	await page.emulateMedia({ media: 'print' });

	await expect(page.locator('.top')).toBeHidden();
	await expect(page.locator('.poster-toolbar')).toBeHidden();
	await expect(page.locator('.poster-head')).toBeHidden();
	const box = await page.locator('.poster-sheet svg').boundingBox();
	// 841 mm at 96 CSS pixels per inch
	expect(Math.round(box.width)).toBe(Math.round(841 / 25.4 * 96));
});

test('Chromium saves the poster as one PDF page of the chosen paper', async ({ page }) => {
	await openPoster(page);
	expect(pageSizes(await page.pdf({ preferCSSPageSize: true }))).toEqual([[2384, 1684]]);

	await page.goto(page.url() + '?format=a4&orientace=na-vysku');
	expect(pageSizes(await page.pdf({ preferCSSPageSize: true }))).toEqual([[595, 842]]);
});
