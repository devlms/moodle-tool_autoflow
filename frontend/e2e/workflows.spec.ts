import { test, expect } from '@playwright/test';

test('the workflow list shows the empty state on a new site', async ({ page }) => {
  await page.goto('/admin/tool/autoflow/index.php');
  await expect(page.getByRole('heading', { name: 'Workflows' })).toBeVisible();
  await expect(page.locator('[data-region="workflow-list"]')).toContainText('No workflows yet');
});

test('the plugin is listed under Admin tools', async ({ page }) => {
  await page.goto('/admin/category.php?category=tools');
  await page.locator('#region-main').getByRole('link', { name: 'Autoflow', exact: true }).click();
  await expect(page).toHaveURL(/admin\/tool\/autoflow\/index\.php/);
});
