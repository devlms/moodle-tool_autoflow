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

namespace tool_autoflow\external;

use core_external\external_api;
use tool_autoflow\local\workflow_store;
use tool_autoflow\local\workflow_store_test;
use tool_autoflow\task\run_workflow as run_task;

/**
 * Tests for the web service functions the editor calls.
 *
 * @package    tool_autoflow
 * @category   test
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_autoflow\external\get_editor
 * @covers     \tool_autoflow\external\save_workflow
 * @covers     \tool_autoflow\external\run_workflow
 * @covers     \tool_autoflow\local\node_catalogue
 */
final class external_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    public function test_get_editor_for_a_new_workflow(): void {
        $result = external_api::clean_returnvalue(get_editor::execute_returns(), get_editor::execute(0));
        $this->assertSame(0, $result['id']);
        $this->assertSame('', $result['name']);
        $this->assertFalse($result['enabled']);
        $this->assertSame(['nodes' => [], 'edges' => []], json_decode($result['graph'], true));

        $types = array_column(json_decode($result['nodetypes'], true), null, 'type');
        $this->assertSame(
            ['trigger.manual', 'trigger.schedule', 'trigger.event', 'logic.condition', 'logic.set', 'action.log',
            'action.http', 'moodle.send_notification', 'moodle.enrol_user', 'moodle.add_to_group', 'moodle.call_function'],
            array_keys($types)
        );
        foreach ($types as $type => $info) {
            $this->assertNotSame($type, $info['label'], "$type has no label");
            foreach ($info['params'] as $param) {
                $this->assertNotSame($param['name'], $param['label'], "{$param['name']} of $type has no label");
            }
        }
        $this->assertSame('GET', $types['action.http']['params'][0]['optionlabels']['get']);
        $condition = $types['logic.condition'];
        $this->assertSame('Condition', $condition['label']);
        $this->assertSame('logic', $condition['category']);
        $this->assertSame(['true', 'false'], $condition['branches']);
        $this->assertSame('Operator', $condition['params'][1]['label']);
        $this->assertSame('select', $condition['params'][1]['kind']);
        $this->assertSame('is equal to', $condition['params'][1]['optionlabels']['eq']);
        $this->assertSame('trigger', $types['trigger.event']['category']);
        $this->assertSame('moodle', $types['moodle.send_notification']['category']);

        $this->assertContains('\\core\\event\\user_created', json_decode($result['events'], true));
        $functions = json_decode($result['functions'], true);
        $this->assertContains('core_webservice_get_site_info', $functions);
        $this->assertSame($functions, array_values(array_unique($functions)));
        $sorted = $functions;
        sort($sorted);
        $this->assertSame($sorted, $functions);
        $this->assertSame('Save', json_decode($result['strings'], true)['save']);
    }

    public function test_get_editor_returns_a_saved_workflow(): void {
        $id = workflow_store::save(0, 'Welcome', workflow_store_test::graph(), true)['id'];
        $result = external_api::clean_returnvalue(get_editor::execute_returns(), get_editor::execute($id));
        $this->assertSame($id, $result['id']);
        $this->assertSame('Welcome', $result['name']);
        $this->assertTrue($result['enabled']);
        $this->assertJsonStringEqualsJsonString(workflow_store_test::graph(), $result['graph']);
    }

    public function test_save_returns_errors_for_a_draft(): void {
        $draft = json_encode(['nodes' => [['id' => 'a', 'type' => 'action.log', 'data' => ['params' => ['message' => 'x']]]],
            'edges' => []]);
        $result = external_api::clean_returnvalue(
            save_workflow::execute_returns(),
            save_workflow::execute(0, 'Draft', $draft, true)
        );
        $this->assertGreaterThan(0, $result['id']);
        $this->assertFalse($result['enabled']);
        $this->assertSame(
            [['code' => 'notrigger', 'message' => 'Add a trigger to start the workflow.', 'nodeids' => []]],
            $result['errors']
        );
    }

    public function test_save_and_enable_a_valid_workflow(): void {
        $result = external_api::clean_returnvalue(
            save_workflow::execute_returns(),
            save_workflow::execute(0, 'Ok', workflow_store_test::graph(), true)
        );
        $this->assertTrue($result['enabled']);
        $this->assertSame([], $result['errors']);
        $this->assertSame('Ok', workflow_store::get($result['id'])->name);
    }

    public function test_run_workflow_queues_a_manual_run(): void {
        $id = workflow_store::save(0, 'Ok', workflow_store_test::graph(), false)['id'];
        $result = external_api::clean_returnvalue(run_workflow::execute_returns(), run_workflow::execute($id));
        $this->assertTrue($result['queued']);
        $tasks = \core\task\manager::get_adhoc_tasks(run_task::class);
        $this->assertSame('manual', json_decode(reset($tasks)->get_custom_data_as_string(), true)['triggertype']);
    }

    public function test_every_function_needs_the_capability(): void {
        $this->setUser($this->getDataGenerator()->create_user());
        $id = workflow_store::save(0, 'Ok', workflow_store_test::graph(), false)['id'] ?? 0;
        foreach (
            [fn() => get_editor::execute(0), fn() => save_workflow::execute(0, 'x', '{}', false),
                fn() => run_workflow::execute($id)] as $call
        ) {
            try {
                $call();
                $this->fail('Allowed without tool/autoflow:manage');
            } catch (\required_capability_exception $e) {
                $this->assertSame('nopermissions', $e->errorcode);
            }
        }
    }

    public function test_every_error_code_has_a_message(): void {
        foreach (
            ['notrigger', 'manytriggers', 'duplicateid', 'danglingedge', 'cycle', 'unreachable', 'unknowntype', 'badcron',
                'badevent', 'missingparam'] as $code
        ) {
            $this->assertTrue(get_string_manager()->string_exists("error:$code", 'tool_autoflow'), $code);
        }
    }
}
