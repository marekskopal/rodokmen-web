// Shared fixtures of the end-to-end tests: every test fails on a console error, an uncaught exception or a
// Content-Security-Policy violation, and map tiles are answered locally instead of asking OpenStreetMap.
import { test as base, expect } from '@playwright/test';

export { expect };

/** Password of the demo family (Makefile DEMO_ENV). */
export const password = 'demo';

/** Title of the demo family's website (tests/Support/DemoFamily.php). */
export const siteTitle = 'Rodokmen Skopalových';

// 1×1 transparent PNG.
const tile = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=', 'base64');

export const test = base.extend({
	/** Messages that fail the test; a test that expects one removes it from the list. */
	consoleErrors: [async ({ page }, use) => {
		const errors = [];
		page.on('console', (message) => {
			if (message.type() === 'error') errors.push(message.text());
		});
		page.on('pageerror', (error) => errors.push(error.message));
		await page.route('https://tile.openstreetmap.org/**', (route) => route.fulfill({ contentType: 'image/png', body: tile }));
		await use(errors);
		expect(errors, 'console errors, exceptions and CSP violations').toEqual([]);
	}, { auto: true }],
});

/** Logs in through the form (the page must not be logged in yet). */
export async function login(page, target = '/') {
	await page.goto(target);
	await page.getByPlaceholder('Heslo').fill(password);
	await page.getByRole('button', { name: 'Vstoupit' }).click();
}

/** Addresses of one person and one register entry of the demo family, from the search index. */
export async function samplePages(request) {
	const response = await request.get('/data/hledani.json');
	expect(response.ok()).toBe(true);
	const items = await response.json();
	const person = items.find((item) => item.t === 'osoba');
	const record = items.find((item) => item.t === 'zápis');
	expect(person, 'a person in the search index').toBeDefined();
	expect(record, 'a register entry in the search index').toBeDefined();
	return { person, record };
}
