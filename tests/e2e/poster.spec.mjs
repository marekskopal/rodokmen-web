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
	await expect(page.locator('.poster-summary')).toContainText('841 × 594 mm');
	// The choices apply at once, there is nothing to confirm.
	await expect(page.getByRole('button', { name: 'Zobrazit' })).toBeHidden();

	await page.getByLabel('Rozvržení').selectOption('pulvejir');
	await page.getByLabel('Papír').selectOption('a3');
	await page.getByLabel('Orientace').selectOption('na-vysku');
	await page.getByLabel('Nadpis', { exact: true }).fill('Rod Skopalů');

	await expect(page).toHaveURL(/rozvrzeni=pulvejir.*format=a3.*orientace=na-vysku.*nadpis=Rod/);
	await expect(page.locator('.poster-sheet')).toHaveClass(/poster-a3-portrait/);
	await expect(page.locator('.poster-summary')).toContainText('297 × 420 mm');
	await expect(page.locator('.poster-sheet .pst-title')).toHaveText('Rod Skopalů');
	// Printing and the download follow the choices without a page load.
	await expect(page.getByRole('link', { name: 'Stáhnout SVG' })).toHaveAttribute('href', /format=a3&orientace=na-vysku/);
	expect(pageSizes(await page.pdf({ preferCSSPageSize: true }))).toEqual([[842, 1191]]);
});

test('the heading and the footer can be left out, with their parts', async ({ page }) => {
	await openPoster(page);
	const sheet = page.locator('.poster-sheet svg');
	await expect(sheet.locator('.pst-sub')).toContainText('Předci osoby');

	await page.getByRole('checkbox', { name: 'výchozí osoba' }).uncheck();
	await expect(page).toHaveURL(/casti=1/);
	await expect(sheet.locator('.pst-sub')).not.toContainText('Předci osoby');
	await expect(sheet.locator('.pst-sub')).toContainText('generac');

	const heading = page.getByRole('group', { name: 'Části nadpisu' }).getByRole('checkbox', { name: 'zobrazit' });
	await heading.uncheck();
	await expect(sheet.locator('.pst-title')).toHaveCount(0);
	// The parts of a hidden heading wait, still ticked, for it to come back.
	await expect(page.getByRole('checkbox', { name: 'počet generací' })).toBeChecked();
	await heading.check();
	await expect(sheet.locator('.pst-title')).toHaveCount(1);

	await page.getByRole('group', { name: 'Části patičky' }).getByRole('checkbox', { name: 'zobrazit' }).uncheck();
	await expect(sheet.locator('.pst-muted')).toHaveCount(0);
	await expect(page.getByRole('link', { name: 'Stáhnout SVG' })).toHaveAttribute('href', /casti=1&nadpis-zobrazit=1&nadpis-generace=1&paticka-nazev=1&paticka-datum=1&paticka-legenda=1$/);
});

test('the select boxes look like the text input', async ({ page }) => {
	await openPoster(page);
	const look = (locator) => locator.evaluate((el) => {
		const css = getComputedStyle(el);
		return [css.borderTopColor, css.borderTopLeftRadius, css.backgroundColor, css.fontFamily];
	});
	expect(await look(page.getByLabel('Papír'))).toEqual(await look(page.getByLabel('Nadpis', { exact: true })));
	expect(await page.getByLabel('Papír').evaluate((el) => getComputedStyle(el).appearance)).toBe('none');
});

test.describe('without JavaScript', () => {
	test.use({ javaScriptEnabled: false });

	test('the button applies the choices', async ({ page }) => {
		await page.goto('/predci/marek-skopal/plakat');
		await page.getByLabel('Papír').selectOption('a3');
		await page.getByRole('button', { name: 'Zobrazit' }).click();
		await expect(page).toHaveURL(/format=a3/);
		await expect(page.locator('.poster-sheet')).toHaveClass(/poster-a3-landscape/);
	});
});

test('the tree layouts draw boxes for the ancestors', async ({ page }) => {
	await openPoster(page);
	// Rounded boxes of the simple style (the historical one cuts their corners).
	await page.getByLabel('Styl').selectOption('jednoduchy');
	for (const layout of ['strom', 'strom-vpravo']) {
		await page.getByLabel('Rozvržení').selectOption(layout);
		await expect(page).toHaveURL(new RegExp(`rozvrzeni=${layout}`));
		await expect(page.locator('.poster-sheet svg rect[rx]').first()).toBeVisible();
		expect(await page.locator('.poster-sheet svg rect[rx]').count()).toBeGreaterThan(10);
	}
	expect(pageSizes(await page.pdf({ preferCSSPageSize: true }))).toEqual([[2384, 1684]]);
});

test('the painted tree grows a trunk, branches, leaves and name ribbons', async ({ page }) => {
	await openPoster(page);
	// The painted tree is the default layout.
	await expect(page.getByLabel('Rozvržení')).toHaveValue('maleny-strom');
	// Every leaf is a small turned group; wood and ribbons are paths.
	expect(await page.locator('.poster-sheet svg g[transform*="rotate"]').count()).toBeGreaterThan(50);
	expect(await page.locator('.poster-sheet svg path').count()).toBeGreaterThan(300);
	const first = await page.locator('.poster-sheet svg').innerHTML();
	await page.reload();
	expect(await page.locator('.poster-sheet svg').innerHTML()).toBe(first);
	await expect(page.locator('.poster-sheet svg text').filter({ hasText: 'Marek Skopal' }).first()).toBeVisible();
});

test('the historical style decorates the poster and the SVG can be downloaded', async ({ page }) => {
	await openPoster(page);
	// The historical style is the default.
	await expect(page.getByLabel('Styl')).toHaveValue('historicky');
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
