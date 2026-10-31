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

/**
 * Tests for saving and loading workflows.
 *
 * @package    tool_autoflow
 * @category   test
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_autoflow\local\workflow_store
 */
final class workflow_store_test extends \advanced_testcase {
    /**
     * A graph JSON string with one trigger and one log node.
     *
     * @param string $type Trigger type.
     * @param array $params Trigger params.
     * @return string
     */
    public static function graph(string $type = 'trigger.manual', array $params = []): string {
        return json_encode([
            'nodes' => [
                ['id' => 't', 'type' => $type, 'data' => ['params' => $params], 'position' => ['x' => 0, 'y' => 0]],
                ['id' => 'log', 'type' => 'action.log', 'data' => ['params' => ['message' => 'hi']],
                    'position' => ['x' => 0, 'y' => 100]],
            ],
            'edges' => [['id' => 't-log', 'source' => 't', 'target' => 'log']],
        ]);
    }

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    public function test_save_new_workflow_and_load_it(): void {
        $result = workflow_store::save(0, 'Welcome', self::graph(), true);
        $this->assertSame([], $result['errors']);
        $this->assertTrue($result['enabled']);

        $workflow = workflow_store::get($result['id']);
        $this->assertSame('Welcome', $workflow->name);
        $this->assertEquals(1, $workflow->enabled);
        $this->assertSame('trigger.manual', $workflow->triggertype);
        $this->assertSame('', $workflow->triggerdata);
        $this->assertEquals(get_admin()->id, $workflow->usermodified);
        $this->assertJsonStringEqualsJsonString(self::graph(), $workflow->graph);
    }

    public function test_update_keeps_the_id(): void {
        $id = workflow_store::save(0, 'One', self::graph(), false)['id'];
        $again = workflow_store::save($id, 'Two', self::graph(), false);
        $this->assertSame($id, $again['id']);
        $this->assertSame('Two', workflow_store::get($id)->name);
    }

    public function test_invalid_graph_is_saved_as_a_disabled_draft(): void {
        $draft = json_encode(['nodes' => [['id' => 'a', 'type' => 'action.log', 'data' => []]], 'edges' => []]);
        $result = workflow_store::save(0, 'Half built', $draft, true);
        $this->assertFalse($result['enabled']);
        $this->assertSame(['notrigger'], array_column($result['errors'], 'code'));
        $this->assertEquals(0, workflow_store::get($result['id'])->enabled);
    }

    public function test_unknown_node_types_are_errors(): void {
        $graph = json_encode(['nodes' => [['id' => 't', 'type' => 'trigger.manual'], ['id' => 'x', 'type' => 'vendor.gone']],
            'edges' => [['source' => 't', 'target' => 'x']]]);
        $result = workflow_store::save(0, 'Broken', $graph, true);
        $this->assertFalse($result['enabled']);
        $this->assertSame([['code' => 'unknowntype', 'nodeids' => ['x']]], $result['errors']);
    }

    public function test_schedule_trigger_copies_the_cron_and_checks_it(): void {
        $ok = workflow_store::save(0, 'Nightly', self::graph('trigger.schedule', ['cron' => '0 2 * * *']), true);
        $this->assertTrue($ok['enabled']);
        $this->assertSame('0 2 * * *', workflow_store::get($ok['id'])->triggerdata);

        $bad = workflow_store::save(0, 'Bad', self::graph('trigger.schedule', ['cron' => 'whenever']), true);
        $this->assertFalse($bad['enabled']);
        $this->assertSame([['code' => 'badcron', 'nodeids' => ['t']]], $bad['errors']);
    }

    public function test_event_trigger_needs_a_real_event_class(): void {
        $ok = workflow_store::save(
            0,
            'On enrol',
            self::graph('trigger.event', ['eventname' => '\\core\\event\\user_created']),
            true
        );
        $this->assertTrue($ok['enabled']);
        $this->assertSame('\\core\\event\\user_created', workflow_store::get($ok['id'])->triggerdata);

        foreach (['\\core\\event\\nope', '\\core\\user', ''] as $name) {
            $bad = workflow_store::save(0, 'Bad', self::graph('trigger.event', ['eventname' => $name]), true);
            $this->assertSame([['code' => 'badevent', 'nodeids' => ['t']]], $bad['errors'], $name);
        }
    }

    public function test_event_names_are_stored_with_a_leading_backslash(): void {
        $id = workflow_store::save(0, 'E', self::graph('trigger.event', ['eventname' => 'core\\event\\user_created']), true)['id'];
        $this->assertSame('\\core\\event\\user_created', workflow_store::get($id)->triggerdata);
    }

    public function test_name_is_required(): void {
        $this->expectException(\invalid_parameter_exception::class);
        workflow_store::save(0, '  ', self::graph(), false);
    }

    public function test_unreadable_graph_is_refused(): void {
        $this->expectException(\invalid_parameter_exception::class);
        workflow_store::save(0, 'X', 'not json', false);
    }

    public function test_event_map_lists_enabled_event_workflows(): void {
        $a = workflow_store::save(0, 'A', self::graph('trigger.event', ['eventname' => '\\core\\event\\user_created']), true)['id'];
        $b = workflow_store::save(0, 'B', self::graph('trigger.event', ['eventname' => '\\core\\event\\user_created']), true)['id'];
        workflow_store::save(0, 'Off', self::graph('trigger.event', ['eventname' => '\\core\\event\\user_created']), false);
        workflow_store::save(0, 'Manual', self::graph(), true);

        $this->assertEqualsCanonicalizing([$a, $b], workflow_store::workflows_for_event('\\core\\event\\user_created'));
        $this->assertSame([], workflow_store::workflows_for_event('\\core\\event\\user_deleted'));
    }

    public function test_saving_refreshes_the_event_map(): void {
        $this->assertSame([], workflow_store::workflows_for_event('\\core\\event\\user_created'));
        $graph = self::graph('trigger.event', ['eventname' => '\\core\\event\\user_created']);
        $id = workflow_store::save(0, 'A', $graph, true)['id'];
        $this->assertSame([$id], workflow_store::workflows_for_event('\\core\\event\\user_created'));
        workflow_store::save($id, 'A', self::graph('trigger.event', ['eventname' => '\\core\\event\\user_created']), false);
        $this->assertSame([], workflow_store::workflows_for_event('\\core\\event\\user_created'));
    }

    public function test_delete_removes_the_workflow_and_its_runs(): void {
        global $DB;
        $graph = self::graph('trigger.event', ['eventname' => '\\core\\event\\user_created']);
        $id = workflow_store::save(0, 'A', $graph, true)['id'];
        $DB->insert_record('tool_autoflow_run', ['workflowid' => $id, 'status' => 'success', 'triggertype' => 'manual',
            'timecreated' => time()]);
        workflow_store::delete($id);
        $this->assertFalse($DB->record_exists('tool_autoflow_workflow', ['id' => $id]));
        $this->assertFalse($DB->record_exists('tool_autoflow_run', ['workflowid' => $id]));
        $this->assertSame([], workflow_store::workflows_for_event('\\core\\event\\user_created'));
    }

    public function test_schedule_workflows_lists_enabled_ones(): void {
        $id = workflow_store::save(0, 'S', self::graph('trigger.schedule', ['cron' => '* * * * *']), true)['id'];
        workflow_store::save(0, 'Off', self::graph('trigger.schedule', ['cron' => '* * * * *']), false);
        $this->assertSame([$id], array_map('intval', array_keys(workflow_store::scheduled())));
    }
}
