// The generations page: one table per generation, readable as one table.
import { expect, test } from './support.mjs';

test('the columns of all the generations stand under each other', async ({ page }) => {
	await page.goto('/generace');
	const tables = page.locator('table.people');
	expect(await tables.count()).toBeGreaterThan(6);

	const columns = await tables.evaluateAll((all) => all.map(
		(table) => [...table.querySelectorAll('th')].map((th) => Math.round(th.getBoundingClientRect().left)).join(' '),
	));
	expect(new Set(columns).size, columns.join('\n')).toBe(1);
});

test('the far generations do not spell out the chain of "pra"', async ({ page }) => {
	await page.goto('/generace');
	const relations = await page.locator('table.people td:nth-child(3)').allTextContents();

	expect(relations).toContain('předek v 6. generaci');
	expect(relations.filter((relation) => relation.includes('('))).toEqual([]);
});
