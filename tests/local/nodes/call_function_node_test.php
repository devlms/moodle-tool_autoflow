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

namespace tool_autoflow\local\nodes;

use tool_autoflow\local\engine\executor;
use tool_autoflow\local\engine\graph;
use tool_autoflow\local\engine\node_failed;
use tool_autoflow\local\engine\param;
use tool_autoflow\local\node_catalogue;

/**
 * Tests for the node that calls any Moodle web service function.
 *
 * @package    tool_autoflow
 * @category   test
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_autoflow\local\nodes\call_function_node
 */
final class call_function_node_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_calls_the_function_and_returns_its_result(): void {
        $this->setAdminUser();
        $user = $this->getDataGenerator()->create_user(['firstname' => 'Amina']);
        $result = (new call_function_node())->execute([
            'function' => 'core_user_get_users_by_field',
            'args' => ['field' => 'id', 'values' => [$user->id]],
        ], []);
        $this->assertSame('Amina', $result->output['result'][0]['firstname']);
    }

    public function test_runs_with_the_current_users_permissions(): void {
        $this->setUser($this->getDataGenerator()->create_user());
        $this->expectException(node_failed::class);
        // A plain user cannot create courses.
        (new call_function_node())->execute([
            'function' => 'core_course_create_courses',
            'args' => ['courses' => [['fullname' => 'X', 'shortname' => 'X', 'categoryid' => 1]]],
        ], []);
    }

    public function test_invalid_arguments_fail_with_moodles_message(): void {
        $this->setAdminUser();
        try {
            (new call_function_node())->execute(['function' => 'core_user_get_users_by_field', 'args' => ['field' => 'id']], []);
            $this->fail('Accepted missing values');
        } catch (node_failed $e) {
            $this->assertStringContainsString('core_user_get_users_by_field', $e->getMessage());
        }
    }

    public function test_unknown_function_fails(): void {
        $this->setAdminUser();
        $this->expectException(node_failed::class);
        $this->expectExceptionMessage('no_such_function');
        (new call_function_node())->execute(['function' => 'no_such_function', 'args' => []], []);
    }

    public function test_arguments_must_be_an_object(): void {
        $this->setAdminUser();
        $this->expectException(node_failed::class);
        $this->expectExceptionMessage('JSON object');
        (new call_function_node())->execute(['function' => 'core_webservice_get_site_info', 'args' => '{"a": 1}'], []);
    }

    public function test_missing_arguments_mean_none(): void {
        $this->setAdminUser();
        $result = (new call_function_node())->execute(['function' => 'core_webservice_get_site_info'], []);
        $this->assertSame('Admin', $result->output['result']['firstname']);
    }

    public function test_placeholders_inside_arguments_cannot_add_keys(): void {
        $this->setAdminUser();
        $user = $this->getDataGenerator()->create_user();
        $graph = graph::from_array([
            'nodes' => [
                ['id' => 't', 'type' => 'trigger.manual', 'data' => []],
                ['id' => 'call', 'type' => 'moodle.call_function', 'data' => ['params' => [
                    'function' => 'core_user_get_users_by_field',
                    'args' => ['field' => 'id', 'values' => ['{{trigger.userid}}']],
                ]]],
            ],
            'edges' => [['source' => 't', 'target' => 'call']],
        ]);
        $executor = new executor(node_catalogue::registry());
        $ok = $executor->run($graph, ['userid' => (string) $user->id]);
        $this->assertSame('success', $ok->status);
        $this->assertEquals($user->id, $ok->context['nodes']['call']['result'][0]['id']);

        // A value full of JSON stays one value: the field is still id, and Moodle rejects the whole string as an id.
        $evil = $executor->run($graph, ['userid' => '1"], "field": "email']);
        $this->assertSame('failed', $evil->status);
        $this->assertStringContainsString("The field 'id' value is invalid: 1\"], \"field\": \"email", $evil->error);
    }

    public function test_metadata(): void {
        $node = new call_function_node();
        $this->assertSame('moodle.call_function', $node->type());
        $this->assertSame([], $node->branches());
        $this->assertEquals([new param('function', 'function', true), new param('args', 'json', false)], $node->params());
    }
}
