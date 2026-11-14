import { describe, expect, it } from 'vitest';
import { click, load } from './dom.mjs';

const header = `
	<button class="nav-toggle" type="button" aria-expanded="false" aria-controls="mainnav" aria-label="Menu"></button>
	<nav id="mainnav" class="nav"><a href="/">Úvod</a></nav>`;

describe('mobile navigation', () => {
	it('opens and closes the menu and tells assistive technology', () => {
		const window = load(header);
		const toggle = window.document.querySelector('.nav-toggle');
		const nav = window.document.getElementById('mainnav');

		click(window, toggle);
		expect(nav.classList.contains('open')).toBe(true);
		expect(toggle.getAttribute('aria-expanded')).toBe('true');

		click(window, toggle);
		expect(nav.classList.contains('open')).toBe(false);
		expect(toggle.getAttribute('aria-expanded')).toBe('false');
	});
});
