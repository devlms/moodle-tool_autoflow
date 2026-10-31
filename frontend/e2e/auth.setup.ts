import { test as setup, expect } from '@playwright/test';

// Logs in once as the dev site's admin and saves the session for the other tests.
setup('log in as admin', async ({ page }) => {
  // Let the login page finish its own requests first; one landing after the form posts can reset the session.
  await page.goto('/login/index.php', { waitUntil: 'networkidle' });
  await page.getByLabel('Username').fill(process.env.MOODLE_ADMIN_USER ?? 'admin');
  await page.getByLabel('Password', { exact: true }).fill(process.env.MOODLE_ADMIN_PASS ?? 'test');
  await page.getByRole('button', { name: 'Log in' }).click();
  await expect(page.locator('body')).toHaveClass(/pagelayout-(mydashboard|frontpage|mycourses)/);
  await page.context().storageState({ path: 'test-results/admin.json' });
});
