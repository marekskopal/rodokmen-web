// Zoom-and-pan viewer for scans and photos (public/assets/viewer.js) on the demo family's invented scan
// (tests/Support/demo: a 900 × 600 crop and the same crop in 2400 × 1600).
import { expect, test } from './support.mjs';

const recordUrl = '/zapis/litovel_krest_josef_skopal_1921';

/** Current zoom in % as the toolbar shows it (100 % = one pixel of the full image per screen pixel). */
const zoom = async (page) => Number((await page.locator('.viewer-zoom').textContent()).replace(/\D/g, ''));

/** The image's translate(x, y) and scale from its transform. */
const transform = async (page) => {
	const value = await page.locator('.viewer-img').evaluate((img) => img.style.transform);
	const [x, y, scale] = value.match(/-?[\d.]+/g).map(Number);
	return { x, y, scale };
};

/** Drops the console errors of the expected failed image loads. */
const allowFailedImages = (consoleErrors) => {
	consoleErrors.splice(0, consoleErrors.length, ...consoleErrors.filter((e) => !/status of 404|ERR_FAILED/.test(e)));
};

const open = async (page) => {
	await page.goto(recordUrl);
	await page.locator('a[data-lightbox]').click();
	await expect(page.locator('.viewer')).toBeVisible();
	// The full resolution replaces the crop.
	await expect(page.locator('.viewer-img')).toHaveAttribute('src', /\/obrazek\/plny\//);
	await expect(page.locator('.viewer-status')).toBeHidden();
};

test('opens the full scan fitted to the window and closes with Esc', async ({ page }) => {
	await open(page);
	const link = page.locator('a[data-lightbox]');

	await expect(page.getByRole('dialog', { name: 'Prohlížeč obrázku' })).toBeVisible();
	await expect(page.getByRole('button', { name: 'Zavřít' })).toBeFocused();
	await expect(page.locator('[data-viewer="orig"]')).toHaveAttribute('href', /\/obrazek\/plny\/litovel_krest_josef_skopal_1921\.jpg$/);
	// 2400 px wide fitted into a 1280 × 720 window (minus the margins) is well below 100 %.
	expect(await zoom(page)).toBeLessThan(60);
	const box = await page.locator('.viewer-img').boundingBox();
	expect(box.x).toBeGreaterThanOrEqual(0);
	expect(box.x + box.width).toBeLessThanOrEqual(1280);
	expect(box.y + box.height).toBeLessThanOrEqual(720);

	await page.keyboard.press('Escape');
	await expect(page.locator('.viewer')).toBeHidden();
	await expect(link).toBeFocused();
});

test('zooms with the buttons and the keys and fits back', async ({ page }) => {
	await open(page);
	const fitted = await zoom(page);

	await page.getByRole('button', { name: 'Přiblížit' }).click();
	await expect.poll(() => zoom(page)).toBeGreaterThan(fitted);
	await page.keyboard.press('+');
	const zoomed = await zoom(page);
	await expect.poll(() => zoom(page)).toBeGreaterThan(fitted * 2);

	await page.keyboard.press('-');
	await expect.poll(() => zoom(page)).toBeLessThan(zoomed);

	await page.getByRole('button', { name: 'Přizpůsobit oknu' }).click();
	await expect.poll(() => zoom(page)).toBe(fitted);

	await page.keyboard.press('+');
	await page.keyboard.press('0');
	await expect.poll(() => zoom(page)).toBe(fitted);

	await page.getByRole('button', { name: 'Oddálit' }).click();
	await expect.poll(() => zoom(page)).toBeLessThan(fitted);
});

test('zooms with the mouse wheel around the cursor', async ({ page }) => {
	await open(page);
	const fitted = await zoom(page);
	const box = await page.locator('.viewer-img').boundingBox();
	// A point in the image's upper left quarter stays under the cursor.
	const cursor = { x: box.x + box.width * 0.25, y: box.y + box.height * 0.25 };
	const before = await transform(page);
	const imagePoint = { x: (cursor.x - before.x) / before.scale, y: (cursor.y - before.y) / before.scale };

	await page.mouse.move(cursor.x, cursor.y);
	await page.mouse.wheel(0, -400);
	await expect.poll(() => zoom(page)).toBeGreaterThan(fitted);

	const after = await transform(page);
	expect(after.x + imagePoint.x * after.scale).toBeCloseTo(cursor.x, 0);
	expect(after.y + imagePoint.y * after.scale).toBeCloseTo(cursor.y, 0);
});

test('pans by dragging without closing', async ({ page }) => {
	await open(page);
	await page.keyboard.press('+');
	await page.keyboard.press('+');
	await page.waitForTimeout(250); // the zoom animation
	const before = await transform(page);

	await page.mouse.move(640, 360);
	await page.mouse.down();
	await page.mouse.move(540, 300, { steps: 5 });
	// The drag ends beside the image's original place; it must not count as a click on the backdrop.
	await page.mouse.move(20, 20, { steps: 5 });
	await page.mouse.up();

	const after = await transform(page);
	expect(after.x).toBeLessThan(before.x + 1);
	expect(after.x).not.toBe(before.x);
	await expect(page.locator('.viewer')).toBeVisible();

	await page.keyboard.press('ArrowLeft');
	await page.waitForTimeout(250);
	expect((await transform(page)).x).toBeGreaterThan(after.x);
});

test('double-click zooms in and fits back', async ({ page }) => {
	await open(page);
	const fitted = await zoom(page);

	await page.locator('.viewer-img').dblclick();
	await expect.poll(() => zoom(page)).toBeGreaterThan(fitted * 2);
	await page.waitForTimeout(250);

	await page.mouse.dblclick(640, 360);
	await expect.poll(() => zoom(page)).toBe(fitted);
	await expect(page.locator('.viewer')).toBeVisible();
});

test('a click beside the image closes the viewer, a click on it does not', async ({ page }) => {
	await open(page);

	await page.locator('.viewer-img').click();
	await page.waitForTimeout(400); // past the double-click window
	await expect(page.locator('.viewer')).toBeVisible();

	await page.mouse.click(5, 5);
	await expect(page.locator('.viewer')).toBeHidden();
});

test('leaves browser shortcuts alone', async ({ page }) => {
	await open(page);
	const fitted = await zoom(page);

	const prevented = await page.evaluate(() => {
		const event = new KeyboardEvent('keydown', { key: '+', ctrlKey: true, bubbles: true, cancelable: true });
		document.dispatchEvent(event);
		return event.defaultPrevented;
	});
	expect(prevented).toBe(false);
	expect(await zoom(page)).toBe(fitted);
});

test('keeps the crop when the full scan cannot be loaded', async ({ page, consoleErrors }) => {
	await page.route('**/obrazek/plny/**', (route) => route.fulfill({ status: 404, body: '' }));
	await page.goto(recordUrl);
	await page.locator('a[data-lightbox]').click();

	await expect(page.locator('.viewer-img')).toHaveAttribute('src', /\/obrazek\/nahled\//);
	await expect(page.locator('.viewer-status')).toBeHidden();
	await expect(page.locator('.viewer-img')).toBeVisible();
	allowFailedImages(consoleErrors);
});

test('tells when there is nothing to show', async ({ page, consoleErrors }) => {
	await page.route('**/obrazek/**', (route) => route.fulfill({ status: 404, body: '' }));
	await page.goto(recordUrl);
	// Both images fail, so the link has no size to click on: send the click to it directly.
	await page.locator('a[data-lightbox]').dispatchEvent('click');

	await expect(page.locator('.viewer-status')).toHaveText('Obrázek se nepodařilo načíst.');
	await expect(page.locator('.viewer-img')).toBeHidden();
	await expect(page.locator('.viewer-zoom')).toHaveText('');

	await page.mouse.click(640, 360); // no image: a click anywhere closes
	await expect(page.locator('.viewer')).toBeHidden();
	allowFailedImages(consoleErrors);
});

test.describe('phone', () => {
	test.use({ viewport: { width: 390, height: 780 }, hasTouch: true });

	test('fits the scan to the width and keeps the toolbar on screen', async ({ page }) => {
		await open(page);

		const image = await page.locator('.viewer-img').boundingBox();
		expect(image.x).toBeCloseTo(0, 0);
		expect(image.width).toBeCloseTo(390, 0);
		const bar = await page.locator('.viewer-bar').boundingBox();
		expect(bar.x).toBeGreaterThanOrEqual(0);
		expect(bar.x + bar.width).toBeLessThanOrEqual(390);
		expect(bar.y + bar.height).toBeLessThanOrEqual(780);

		await page.locator('.viewer-img').tap();
		await page.waitForTimeout(400);
		await expect(page.locator('.viewer')).toBeVisible();
	});
});
