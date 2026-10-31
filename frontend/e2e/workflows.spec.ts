import { test, expect } from '@playwright/test';

test('the workflow list has a heading and a way to start a new workflow', async ({ page }) => {
  await page.goto('/admin/tool/autoflow/index.php');
  await expect(page.getByRole('heading', { name: 'Workflows' })).toBeVisible();
  await page.getByTestId('new-workflow').click();
  await expect(page).toHaveURL(/admin\/tool\/autoflow\/edit\.php/);
  await expect(page.getByRole('heading', { name: 'New workflow' })).toBeVisible();
});

test('the plugin is listed under Admin tools', async ({ page }) => {
  await page.goto('/admin/category.php?category=tools');
  await page.locator('#region-main').getByRole('link', { name: 'Autoflow', exact: true }).click();
  await expect(page).toHaveURL(/admin\/tool\/autoflow\/index\.php/);
});
