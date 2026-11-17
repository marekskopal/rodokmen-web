import { describe, expect, it, vi } from 'vitest';
import { click, load, type } from './dom.mjs';

const form = (format, summary) => `
	<form method="get" data-live-form>
		<select name="format">
			<option value="a1" ${format === 'a1' ? 'selected' : ''}>A1</option>
			<option value="a3" ${format === 'a3' ? 'selected' : ''}>A3</option>
		</select>
		<select name="generace" data-live="depth"><option value="" selected>doporučeno (${format === 'a1' ? 7 : 5})</option></select>
		<input type="text" name="nadpis" value="">
		<p data-live="summary">${summary}</p>
		<button type="submit" data-live-submit>Zobrazit</button>
		<a data-live="svg" href="/plakat.svg?format=${format}" download>Stáhnout SVG</a>
		<button type="button" data-print>Tisk / PDF</button>
	</form>
	<div data-live="preview" data-live-busy><div class="sheet poster-${format}"></div></div>`;

/** A page whose fetch answers with the page for the asked format; `answer` resolves the latest request. */
function page() {
	const requests = [];
	const window = load(form('a1', '841 × 594 mm'), {
		url: 'https://rodokmen.test/predci/marek-skopal/plakat',
		before: (w) => {
			w.print = vi.fn();
			w.fetch = vi.fn((url) => new Promise((resolve) => {
				requests.push({ url: String(url), resolve });
			}));
		},
	});
	const answer = async () => {
		const request = requests.at(-1);
		const format = new URL(request.url, 'https://rodokmen.test/').searchParams.get('format') ?? 'a1';
		request.resolve({ ok: true, redirected: false, text: async () => `<body>${form(format, format === 'a3' ? '420 × 297 mm' : '841 × 594 mm')}</body>` });
		await vi.waitFor(() => expect(window.document.querySelector('[aria-busy]')).toBeNull());
	};
	return { window, requests, answer, $: (sel) => window.document.querySelector(sel) };
}

describe('forms that apply a choice at once', () => {
	it('hides the submit button', () => {
		const { $ } = page();
		expect($('[data-live-submit]').hidden).toBe(true);
	});

	it('swaps the live parts and the address after a choice', async () => {
		const { window, requests, answer, $ } = page();
		const format = $('select[name="format"]');
		format.value = 'a3';
		format.dispatchEvent(new window.Event('change', { bubbles: true }));

		expect(requests.map((r) => r.url)).toEqual(['/predci/marek-skopal/plakat?format=a3']);
		expect($('[data-live="preview"]').getAttribute('aria-busy')).toBe('true');
		await answer();

		expect($('[data-live="summary"]').textContent).toBe('420 × 297 mm');
		expect($('[data-live="svg"]').getAttribute('href')).toBe('/plakat.svg?format=a3');
		expect($('.sheet').className).toContain('poster-a3');
		expect($('[data-live="depth"]').textContent).toContain('doporučeno (5)');
		expect(window.location.search).toBe('?format=a3');
	});

	it('waits with the title until the typing stops', async () => {
		vi.useFakeTimers();
		try {
			const { window, requests, $ } = page();
			type(window, $('input[name="nadpis"]'), 'Rod');
			type(window, $('input[name="nadpis"]'), 'Rod Skopalů');
			expect(requests).toHaveLength(0);
			vi.advanceTimersByTime(700);
			expect(requests.map((r) => r.url)).toEqual(['/predci/marek-skopal/plakat?format=a1&nadpis=Rod+Skopal%C5%AF']);
		} finally {
			vi.useRealTimers();
		}
	});

	it('prints only after the choice has loaded', async () => {
		const { window, answer, $ } = page();
		const format = $('select[name="format"]');
		format.value = 'a3';
		format.dispatchEvent(new window.Event('change', { bubbles: true }));
		click(window, $('[data-print]'));
		expect(window.print).not.toHaveBeenCalled();

		await answer();
		await vi.waitFor(() => expect(window.print).toHaveBeenCalledOnce());
		expect($('.sheet').className).toContain('poster-a3');
	});
});
