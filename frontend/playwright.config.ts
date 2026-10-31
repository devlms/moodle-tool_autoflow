import { defineConfig, devices } from '@playwright/test';

// End-to-end tests against a running Moodle with the plugin installed: the
// moodle-docker dev site locally, the same setup in CI (.github/workflows/e2e.yml).
export default defineConfig({
  testDir: 'e2e',
  fullyParallel: false,
  retries: process.env.CI ? 1 : 0,
  reporter: [['list'], ['html', { outputFolder: 'playwright-report', open: 'never' }], ['json', { outputFile: 'test-results/results.json' }]],
  use: {
    baseURL: process.env.MOODLE_URL ?? 'http://localhost:8000',
    storageState: 'test-results/admin.json',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [
    { name: 'setup', testMatch: /auth\.setup\.ts/, use: { storageState: { cookies: [], origins: [] } } },
    { name: 'chromium', use: { ...devices['Desktop Chrome'] }, dependencies: ['setup'] },
  ],
});
