import { describe, expect, it } from 'vitest';
import { click, load } from './dom.mjs';

const button = '<button type="button" data-theme-toggle>Světlý / tmavý režim</button>';
const both = { scripts: ['theme.js', 'app.js'] };

describe('theme', () => {
	it('switches from the system light scheme to dark and remembers it', () => {
		const window = load(button, both);
		click(window, window.document.querySelector('[data-theme-toggle]'));

		expect(window.document.documentElement.dataset.theme).toBe('dark');
		expect(window.localStorage.getItem('rk-theme')).toBe('dark');
	});

	it('switches from the system dark scheme to light', () => {
		const window = load(button, { ...both, dark: true });
		click(window, window.document.querySelector('[data-theme-toggle]'));

		expect(window.document.documentElement.dataset.theme).toBe('light');
	});

	it('toggles back and forth', () => {
		const window = load(button, both);
		const toggle = window.document.querySelector('[data-theme-toggle]');
		click(window, toggle);
		click(window, toggle);

		expect(window.document.documentElement.dataset.theme).toBe('light');
		expect(window.localStorage.getItem('rk-theme')).toBe('light');
	});

	it('applies the remembered choice before the page is painted', () => {
		const window = load('', { scripts: ['theme.js'], before: (w) => w.localStorage.setItem('rk-theme', 'dark') });

		expect(window.document.documentElement.dataset.theme).toBe('dark');
	});

	it('works when the browser blocks storage', () => {
		const blockStorage = (w) => Object.defineProperty(w, 'localStorage', {
			get() { throw new w.DOMException('blocked', 'SecurityError'); },
		});
		const window = load(button, { ...both, before: blockStorage });
		click(window, window.document.querySelector('[data-theme-toggle]'));

		expect(window.document.documentElement.dataset.theme).toBe('dark');
	});
});
