import { describe, expect, it, vi } from 'vitest';
import { click, load } from './dom.mjs';

const page = `
	<button type="button" data-print>Tisk / PDF</button>
	<details id="closed"><summary>Přepis a překlad</summary>Joannes</details>
	<details id="open" open><summary>Přepis a překlad</summary>Maria</details>`;

describe('printing', () => {
	it('prints from the button', () => {
		const window = load(page, { before: (w) => { w.print = vi.fn(); } });
		click(window, window.document.querySelector('[data-print]'));

		expect(window.print).toHaveBeenCalledOnce();
	});

	it('opens the transcriptions for printing and closes them afterwards', () => {
		const window = load(page);
		const closed = window.document.getElementById('closed');
		const open = window.document.getElementById('open');

		window.dispatchEvent(new window.Event('beforeprint'));
		expect(closed.open).toBe(true);
		expect(open.open).toBe(true);

		window.dispatchEvent(new window.Event('afterprint'));
		expect(closed.open).toBe(false);
		expect(open.open).toBe(true); // what the visitor opened stays open
		expect(closed.hasAttribute('data-print-opened')).toBe(false);
	});
});
