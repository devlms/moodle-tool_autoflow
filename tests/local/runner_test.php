<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace tool_autoflow\local;

use tool_autoflow\task\run_workflow;

/**
 * Tests for queueing and running workflows.
 *
 * @package    tool_autoflow
 * @category   test
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_autoflow\local\runner
 * @covers     \tool_autoflow\task\run_workflow
 */
final class runner_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Save a workflow: trigger, then a log node with the given message.
     *
     * @param string $message
     * @param bool $enabled
     * @return int
     */
    private static function workflow(string $message = 'Hello {{trigger.name}}', bool $enabled = true): int {
        $graph = json_encode([
            'nodes' => [
                ['id' => 't', 'type' => 'trigger.manual', 'data' => []],
                ['id' => 'log', 'type' => 'action.log', 'data' => ['params' => ['message' => $message]]],
            ],
            'edges' => [['source' => 't', 'target' => 'log']],
        ]);
        return workflow_store::save(0, 'Test', $graph, $enabled)['id'];
    }

    public function test_queue_creates_an_adhoc_task_running_as_the_admin(): void {
        $id = self::workflow();
        $this->assertTrue(runner::queue($id, ['name' => 'Amina'], 'manual'));
        $tasks = \core\task\manager::get_adhoc_tasks(run_workflow::class);
        $this->assertCount(1, $tasks);
        $task = reset($tasks);
        $this->assertEquals(get_admin()->id, $task->get_userid());
        $this->assertSame(
            ['workflowid' => $id, 'trigger' => ['name' => 'Amina'], 'triggertype' => 'manual', 'depth' => 0],
            json_decode($task->get_custom_data_as_string(), true)
        );
    }

    public function test_queue_runs_as_the_configured_user(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $id = self::workflow();
        $DB->set_field('tool_autoflow_workflow', 'runasuserid', $user->id, ['id' => $id]);
        runner::queue($id, [], 'manual');
        $tasks = \core\task\manager::get_adhoc_tasks(run_workflow::class);
        $this->assertEquals($user->id, reset($tasks)->get_userid());
    }

    public function test_running_the_task_records_a_successful_run(): void {
        global $DB;
        $student = $this->getDataGenerator()->create_user();
        $id = self::workflow();
        runner::queue($id, ['name' => 'Amina', 'relateduserid' => $student->id], 'event');
        $this->expectOutputRegex('/Workflow \d+: success/');
        $this->runAdhocTasks(run_workflow::class);

        $run = $DB->get_record('tool_autoflow_run', ['workflowid' => $id], '*', MUST_EXIST);
        $this->assertSame('success', $run->status);
        $this->assertSame('event', $run->triggertype);
        $this->assertEquals($student->id, $run->userid);
        $this->assertNull($run->error);
        $this->assertGreaterThanOrEqual($run->timecreated, $run->timefinished);
        $log = json_decode($run->log, true);
        $this->assertSame(['t', 'log'], array_column($log, 'node'));
        $this->assertSame('Hello Amina', $log[1]['output']['message']);
    }

    public function test_failed_run_records_the_error(): void {
        global $DB;
        $graph = json_encode([
            'nodes' => [
                ['id' => 't', 'type' => 'trigger.manual', 'data' => []],
                ['id' => 'set', 'type' => 'logic.set', 'data' => ['params' => ['name' => '']]],
            ],
            'edges' => [['source' => 't', 'target' => 'set']],
        ]);
        $id = workflow_store::save(0, 'Broken', $graph, true)['id'];
        $run = runner::run($id, [], 'manual', 0);
        $this->assertSame('failed', $run->status);
        $this->assertSame('Set variable needs a name.', $run->error);
        $this->assertEquals($run, $DB->get_record('tool_autoflow_run', ['id' => $run->id]));
    }

    public function test_userid_falls_back_to_the_trigger_userid(): void {
        $user = $this->getDataGenerator()->create_user();
        $run = runner::run(self::workflow(), ['userid' => $user->id], 'event', 0);
        $this->assertEquals($user->id, $run->userid);
        $this->assertNull(runner::run(self::workflow(), [], 'manual', 0)->userid);
    }

    public function test_queue_refuses_runs_nested_too_deep(): void {
        $id = self::workflow();
        $this->assertTrue(runner::queue($id, [], 'event', runner::MAX_DEPTH));
        $this->assertFalse(runner::queue($id, [], 'event', runner::MAX_DEPTH + 1));
        $this->assertCount(1, \core\task\manager::get_adhoc_tasks(run_workflow::class));
    }

    public function test_task_for_a_deleted_workflow_does_nothing(): void {
        global $DB;
        $id = self::workflow();
        runner::queue($id, [], 'manual');
        workflow_store::delete($id);
        $this->runAdhocTasks(run_workflow::class);
        $this->assertSame(0, $DB->count_records('tool_autoflow_run'));
    }

    public function test_disabled_workflow_does_not_run_from_events(): void {
        global $DB;
        $id = self::workflow('x', false);
        runner::queue($id, [], 'event');
        runner::queue($id, [], 'manual');
        $this->expectOutputRegex('/Workflow \d+: success/');
        $this->runAdhocTasks(run_workflow::class);
        $this->assertSame(['manual'], $DB->get_fieldset_select('tool_autoflow_run', 'triggertype', 'workflowid = ?', [$id]));
    }

    public function test_queued_run_uses_the_user_who_saved_the_workflow(): void {
        $manager = $this->getDataGenerator()->create_user();
        $this->setUser($manager);
        runner::queue(self::workflow(), [], 'manual');
        $tasks = \core\task\manager::get_adhoc_tasks(run_workflow::class);
        $this->assertEquals($manager->id, reset($tasks)->get_userid());
    }
}
