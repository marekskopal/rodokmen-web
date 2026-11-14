// Unit tests of the browser scripts in public/assets (tests/js). Every test loads the scripts into its own jsdom
// window (tests/js/dom.mjs), so the Node environment is enough here.
import { defineConfig } from 'vitest/config';

export default defineConfig({
	test: {
		include: ['tests/js/**/*.test.mjs'],
		environment: 'node',
	},
});
