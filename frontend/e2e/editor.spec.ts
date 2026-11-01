import { execSync } from 'node:child_process';
import { test, expect, type Page } from '@playwright/test';

// Runs Moodle's ad hoc tasks, so queued workflow runs happen. Set MOODLE_CRON_CMD to the
// command for your setup, for example a moodle-docker exec (see the CI workflow).
function runAdhocTasks() {
  const command = process.env.MOODLE_CRON_CMD;
  if (!command) {
    throw new Error('Set MOODLE_CRON_CMD to a command that runs admin/cli/adhoc_task.php --execute');
  }
  execSync(command, { stdio: 'pipe', timeout: 120_000 });
}

// The editor only: Moodle's own message drawer also has "Message" fields and a status region.
const editor = (page: Page) => page.locator('[data-tool-autoflow-editor]');

async function addNode(page: Page, type: string) {
  await page.getByTestId(`palette-${type}`).click();
}

async function connect(page: Page, from: string, to: string, handle = '') {
  const source = page.locator(`.react-flow__node[data-id="${from}"] .react-flow__handle-bottom${handle ? `[data-handleid="${handle}"]` : ''}`);
  const target = page.locator(`.react-flow__node[data-id="${to}"] .react-flow__handle-top`);
  await source.dragTo(target);
}

test('build a workflow in the editor, run it and read the run log', async ({ page }) => {
  const name = `Greeter ${Date.now()}`;
  await page.goto('/admin/tool/autoflow/index.php');
  await page.getByTestId('new-workflow').click();

  await editor(page).getByLabel('Workflow name', { exact: true }).fill(name);
  await addNode(page, 'trigger.manual');
  await addNode(page, 'logic.condition');
  await editor(page).getByLabel('Value', { exact: true }).fill('{{trigger.userid}}');
  await editor(page).getByLabel('Operator', { exact: true }).selectOption('gt');
  await editor(page).getByLabel('Compare with', { exact: true }).fill('0');
  await addNode(page, 'action.log');
  await editor(page).getByLabel('Message', { exact: true }).fill('Hello user {{trigger.userid}}');
  await connect(page, 'trigger_manual_1', 'logic_condition_1');
  await connect(page, 'logic_condition_1', 'action_log_1', 'true');
  await expect(page.locator('.react-flow__edge')).toHaveCount(2);
  await expect(editor(page).getByRole('region', { name: 'Problems' })).toHaveCount(0);

  await editor(page).getByLabel('Enabled', { exact: true }).check();
  await page.getByRole('button', { name: 'Save' }).click();
  await expect(editor(page).getByRole('status')).toHaveText('Saved');
  await expect(page).toHaveURL(/edit\.php\?id=\d+/);

  await page.getByRole('button', { name: 'Run now' }).click();
  await expect(editor(page).getByRole('status')).toContainText('Run queued');
  runAdhocTasks();

  await page.goto('/admin/tool/autoflow/index.php');
  const row = page.locator('tr', { hasText: name });
  await expect(row).toContainText('Started by hand');
  await row.getByTestId('run-history').click();
  const run = page.getByTestId('run').first();
  await expect(run).toHaveAttribute('data-status', 'success');
  await run.locator('summary').click();
  await expect(run).toContainText('logic_condition_1');
  await expect(run).toContainText(/Hello user \d+/);

  // Reloading the editor brings the saved graph back.
  await page.goto('/admin/tool/autoflow/index.php');
  await page.getByRole('link', { name }).click();
  await expect(page.getByTestId('node-action_log_1')).toBeVisible();
  await expect(page.locator('.react-flow__edge')).toHaveCount(2);
});

test('a workflow without a trigger or required settings is saved as a disabled draft with the problems shown', async ({ page }) => {
  await page.goto('/admin/tool/autoflow/edit.php');
  await editor(page).getByLabel('Workflow name', { exact: true }).fill(`Draft ${Date.now()}`);
  await addNode(page, 'action.log');
  await editor(page).getByLabel('Enabled', { exact: true }).check();
  await page.getByRole('button', { name: 'Save' }).click();
  await expect(editor(page).getByRole('status')).toHaveText('Saved');
  const problems = editor(page).getByRole('region', { name: 'Problems' });
  await expect(problems).toContainText('Add a trigger to start the workflow.');
  // The log node has no message yet: the save says so and marks the node.
  await expect(problems).toContainText('Fill in the required settings of the marked nodes.');
  await expect(page.locator('.react-flow__node[data-id="action_log_1"] .tool_autoflow-node')).toHaveClass(/has-problem/);
  await expect(editor(page).getByLabel('Enabled', { exact: true })).not.toBeChecked();
});

test('a workflow can call any Moodle web service function', async ({ page }) => {
  const name = `Site info ${Date.now()}`;
  await page.goto('/admin/tool/autoflow/edit.php');
  await editor(page).getByLabel('Workflow name', { exact: true }).fill(name);
  await addNode(page, 'trigger.manual');
  await addNode(page, 'moodle.call_function');
  await editor(page).getByLabel('Function', { exact: true }).fill('core_webservice_get_site_info');
  await addNode(page, 'action.log');
  await editor(page).getByLabel('Message', { exact: true }).fill('This site is {{nodes.moodle_call_function_1.result.sitename}}');
  await connect(page, 'trigger_manual_1', 'moodle_call_function_1');
  await connect(page, 'moodle_call_function_1', 'action_log_1');
  await editor(page).getByRole('button', { name: 'Save' }).click();
  await expect(editor(page).getByRole('status')).toHaveText('Saved');
  await editor(page).getByRole('button', { name: 'Run now' }).click();
  await expect(editor(page).getByRole('status')).toContainText('Run queued');
  runAdhocTasks();

  await page.goto('/admin/tool/autoflow/index.php');
  await page.locator('tr', { hasText: name }).getByTestId('run-history').click();
  const run = page.getByTestId('run').first();
  await expect(run).toHaveAttribute('data-status', 'success');
  await run.locator('summary').click();
  await expect(run).toContainText(/This site is \S+/);
});
