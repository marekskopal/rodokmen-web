// End-to-end tests (tests/e2e) in Chromium against the demo family served by `make e2e-server`.
import { defineConfig, devices } from '@playwright/test';

const port = 8090;
const ci = !!process.env.CI;

export default defineConfig({
	testDir: 'tests/e2e',
	testMatch: '**/*.spec.mjs',
	outputDir: 'var/e2e-results',
	fullyParallel: true,
	forbidOnly: ci,
	retries: ci ? 1 : 0,
	reporter: ci ? [['list'], ['html', { outputFolder: 'var/e2e-report', open: 'never' }]] : 'list',
	use: {
		baseURL: `http://localhost:${port}`,
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
	},
	projects: [
		// Logs in once and saves the login cookie for the other tests (tests/e2e/auth.setup.mjs).
		{ name: 'setup', testMatch: 'auth.setup.mjs' },
		{
			name: 'chromium',
			use: { ...devices['Desktop Chrome'], storageState: 'var/e2e-auth.json' },
			dependencies: ['setup'],
		},
	],
	webServer: {
		command: 'make e2e-server',
		url: `http://localhost:${port}/health`,
		reuseExistingServer: !ci,
		timeout: 60_000,
		// The PHP built-in server logs every request to stderr; run `make e2e-server` by hand to see it.
		stderr: 'ignore',
	},
});
