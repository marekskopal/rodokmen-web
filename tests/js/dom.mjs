// Loads the plain browser scripts of public/assets into a fresh jsdom window for every test: the scripts are
// IIFEs without a bundler or modules, and a new window per test keeps their listeners on document apart.
import { readFileSync } from 'node:fs';
import { JSDOM } from 'jsdom';

const assets = new URL('../../public/assets/', import.meta.url);
const sources = new Map();

const source = (name) => {
	if (!sources.has(name)) sources.set(name, readFileSync(new URL(name, assets), 'utf8'));
	return sources.get(name);
};

/**
 * @param {string} body HTML of <body>
 * @param {object} [options]
 * @param {string[]} [options.scripts] files in public/assets, run in this order (default app.js)
 * @param {string} [options.url] page address (location, history)
 * @param {boolean} [options.dark] whether the system prefers the dark colour scheme
 * @param {(window: Window) => void} [options.before] runs before the scripts (e.g. to fill localStorage)
 * @returns {Window}
 */
export function load(body, { scripts = ['app.js'], url = 'https://rodokmen.test/', dark = false, before } = {}) {
	const { window } = new JSDOM(`<!doctype html><html lang="cs"><head></head><body>${body}</body></html>`, {
		url,
		runScripts: 'outside-only',
		pretendToBeVisual: true,
	});
	window.matchMedia = (query) => ({
		matches: dark && query.includes('dark'),
		media: query,
		addEventListener() {},
		removeEventListener() {},
	});
	if (before) before(window);
	for (const name of scripts) window.eval(source(name));
	return window;
}

/** Clicks an element like a user (a bubbling primary-button click). */
export function click(window, element) {
	element.dispatchEvent(new window.MouseEvent('click', { bubbles: true, cancelable: true, button: 0 }));
}

/** Types into an input: sets the value and fires `input`. */
export function type(window, input, value) {
	input.value = value;
	input.dispatchEvent(new window.Event('input', { bubbles: true }));
}
