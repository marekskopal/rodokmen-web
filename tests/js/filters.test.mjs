import { describe, expect, it } from 'vitest';
import { click, load, type } from './dom.mjs';

const row = (name, line, st) => `<tr data-text="${name}" data-line="${line}" data-st="${st}"><td>${name}</td></tr>`;

const generations = `
	<div class="filters" data-filter-root>
		<input type="search" data-filter-text>
		<button type="button" class="chip on" data-filter-line="">Všechny rody</button>
		<button type="button" class="chip" data-filter-line="skopal">Skopalovi</button>
		<button type="button" class="chip" data-filter-line="horak">Horákovi</button>
		<label><input type="checkbox" data-filter-doc> jen doložené</label>
	</div>
	<section data-gen id="g1"><span data-gen-count>2</span><table>
		${row('Antonín Skopal kovář Uničov', 'skopal', 'doc')}
		${row('Jan Horák sládek Litovel', 'horak', 'prob')}
	</table></section>
	<section data-gen id="g2"><span data-gen-count>1</span><table>
		${row('Matouš Skopal kovář', 'skopal', 'prob')}
	</table></section>
	<p data-filter-empty hidden>Nic neodpovídá filtru.</p>`;

const records = `
	<div class="filters" data-filter-root>
		<input type="search" data-filter-text>
		<button type="button" class="chip on" data-filter-kind="">Vše</button>
		<button type="button" class="chip" data-filter-kind="N">Narození</button>
		<button type="button" class="chip" data-filter-kind="Z">Úmrtí</button>
	</div>
	<a class="record-tile" data-kind="N" data-text="Křest Josef Skopal 1921">Křest</a>
	<a class="record-tile" data-kind="Z" data-text="Pohřeb Antonín Skopal 1951">Pohřeb</a>
	<p data-filter-empty hidden>Nic neodpovídá filtru.</p>`;

const visibleNames = (window) => Array.from(window.document.querySelectorAll('tr[data-text]'))
	.filter((tr) => !tr.hidden).map((tr) => tr.dataset.text.split(' ').slice(0, 2).join(' '));

describe('generations filter', () => {
	it('matches the text without diacritics and case', () => {
		const window = load(generations);
		type(window, window.document.querySelector('[data-filter-text]'), 'SLADEK');

		expect(visibleNames(window)).toEqual(['Jan Horák']);
	});

	it('hides generations without a match and updates their counts', () => {
		const window = load(generations);
		type(window, window.document.querySelector('[data-filter-text]'), 'horák');

		expect(window.document.getElementById('g1').hidden).toBe(false);
		expect(window.document.getElementById('g2').hidden).toBe(true);
		expect(window.document.querySelector('#g1 [data-gen-count]').textContent).toBe('1');
	});

	it('filters by line and keeps the line in the address', () => {
		const window = load(generations, { url: 'https://rodokmen.test/generace' });
		click(window, window.document.querySelector('[data-filter-line="skopal"]'));

		expect(visibleNames(window)).toEqual(['Antonín Skopal', 'Matouš Skopal']);
		expect(window.location.search).toBe('?rod=skopal');
		expect(window.document.querySelector('[data-filter-line="skopal"]').classList.contains('on')).toBe(true);

		click(window, window.document.querySelector('[data-filter-line=""]'));
		expect(visibleNames(window)).toHaveLength(3);
		expect(window.location.search).toBe('');
	});

	it('starts with the line chip marked in the page', () => {
		const html = generations.replace('class="chip on" data-filter-line=""', 'class="chip" data-filter-line=""')
			.replace('class="chip" data-filter-line="horak"', 'class="chip on" data-filter-line="horak"');
		const window = load(html);

		expect(visibleNames(window)).toEqual(['Jan Horák']);
	});

	it('shows only people documented by a register entry', () => {
		const window = load(generations);
		const doc = window.document.querySelector('[data-filter-doc]');
		doc.checked = true;
		doc.dispatchEvent(new window.Event('change'));

		expect(visibleNames(window)).toEqual(['Antonín Skopal']);
	});

	it('shows the empty note when nothing matches', () => {
		const window = load(generations);
		const empty = window.document.querySelector('[data-filter-empty]');
		type(window, window.document.querySelector('[data-filter-text]'), 'nikdo takový');

		expect(empty.hidden).toBe(false);

		type(window, window.document.querySelector('[data-filter-text]'), '');
		expect(empty.hidden).toBe(true);
	});
});

describe('records filter', () => {
	it('filters by kind and text together', () => {
		const window = load(records);
		const tiles = () => Array.from(window.document.querySelectorAll('a.record-tile')).filter((a) => !a.hidden).map((a) => a.textContent);

		click(window, window.document.querySelector('[data-filter-kind="Z"]'));
		expect(tiles()).toEqual(['Pohřeb']);

		click(window, window.document.querySelector('[data-filter-kind=""]'));
		type(window, window.document.querySelector('[data-filter-text]'), '1921');
		expect(tiles()).toEqual(['Křest']);
	});
});
