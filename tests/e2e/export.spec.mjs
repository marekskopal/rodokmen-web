// GEDCOM download for other genealogy software.
import { expect, test } from './support.mjs';

test('the footer downloads the tree as GEDCOM', async ({ page }) => {
	await page.goto('/');
	const download = page.waitForEvent('download');
	await page.getByRole('link', { name: 'Stáhnout GEDCOM' }).click();
	const file = await download;

	expect(file.suggestedFilename()).toBe('rodokmen.ged');
	const chunks = [];
	for await (const chunk of await file.createReadStream()) chunks.push(chunk);
	const text = Buffer.concat(chunks).toString('utf8');
	expect(text.startsWith('\uFEFF0 HEAD\r\n')).toBe(true);
	expect(text).toContain('1 NAME Marek /Skopal/');
	expect(text).toContain('2 VERS 5.5.1');
	expect(text.endsWith('0 TRLR\r\n')).toBe(true);
});
