// Pages load WebP copies of the scans sized to the screen (srcset, src/Image/Thumbnails.php).
import { expect, test } from './support.mjs';

const recordUrl = '/zapis/litovel_krest_josef_skopal_1921';

test('the scan loads a WebP copy instead of the JPEG', async ({ page }) => {
	const webp = page.waitForResponse((response) => /\/obrazek\/nahled\/\d+\/[a-z0-9_-]+\.webp$/.test(response.url()));
	await page.goto(recordUrl);

	const response = await webp;
	expect(response.status()).toBe(200);
	expect(response.headers()['content-type']).toBe('image/webp');

	const image = page.locator('.scan img');
	await expect(image).toHaveAttribute('alt', 'Křest – Josef Skopal');
	// The demo crop is 900 px wide: the 960 and 1440 copies would be the same, so it is listed once at 900w.
	await expect(image).toHaveAttribute('srcset', '/obrazek/nahled/480/litovel_krest_josef_skopal_1921.webp 480w, /obrazek/nahled/960/litovel_krest_josef_skopal_1921.webp 900w');
	await expect.poll(() => image.evaluate((img) => img.currentSrc)).toMatch(/\.webp$/);
	expect(await image.evaluate((img) => img.naturalWidth)).toBeGreaterThan(0);
});

test.describe('phone', () => {
	test.use({ viewport: { width: 390, height: 780 }, deviceScaleFactor: 1 });

	test('takes the smallest copy', async ({ page }) => {
		await page.goto(recordUrl);
		const image = page.locator('.scan img');
		await expect.poll(() => image.evaluate((img) => img.currentSrc)).toMatch(/\/obrazek\/nahled\/480\//);
	});
});
