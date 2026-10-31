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

declare(strict_types=1);

namespace tool_autoflow\local\engine;

use tool_autoflow\local\engine\nodes\condition_node;
use tool_autoflow\local\engine\nodes\log_node;
use tool_autoflow\local\engine\nodes\set_variable_node;
use tool_autoflow\local\engine\nodes\trigger_node;

/**
 * Tests for what nodes say about themselves, which the editor palette shows.
 *
 * @package    tool_autoflow
 * @category   test
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_autoflow\local\engine\nodes\condition_node
 * @covers     \tool_autoflow\local\engine\nodes\log_node
 * @covers     \tool_autoflow\local\engine\nodes\set_variable_node
 * @covers     \tool_autoflow\local\engine\nodes\trigger_node
 * @covers     \tool_autoflow\local\engine\param
 */
final class node_metadata_test extends \basic_testcase {
    public function test_condition_parameters_and_branches(): void {
        $node = new condition_node();
        $this->assertSame(['true', 'false'], $node->branches());
        $params = $node->params();
        $this->assertSame(['left', 'operator', 'right'], array_map(fn(param $p) => $p->name, $params));
        $this->assertSame('select', $params[1]->kind);
        $this->assertTrue($params[1]->required);
        $this->assertSame(['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'contains', 'empty', 'notempty'], $params[1]->options);
        $this->assertEquals([new param('left', 'text', true), $params[1], new param('right', 'text', false)], $params);
    }

    public function test_log_parameters(): void {
        $node = new log_node();
        $this->assertSame([], $node->branches());
        $this->assertEquals([new param('message', 'textarea', true)], $node->params());
    }

    public function test_set_variable_parameters(): void {
        $this->assertEquals(
            [new param('name', 'text', true), new param('value', 'text', false)],
            (new set_variable_node())->params()
        );
        $this->assertSame([], (new set_variable_node())->branches());
    }

    public function test_trigger_parameters_depend_on_the_type(): void {
        $this->assertSame([], (new trigger_node('trigger.manual'))->params());
        $this->assertEquals([new param('cron', 'text', true)], (new trigger_node('trigger.schedule'))->params());
        $this->assertEquals([new param('eventname', 'event', true)], (new trigger_node('trigger.event'))->params());
        $this->assertSame([], (new trigger_node('trigger.event'))->branches());
    }

    public function test_param_exports_as_array(): void {
        $this->assertSame(
            ['name' => 'operator', 'kind' => 'select', 'required' => true, 'options' => ['a', 'b']],
            (new param('operator', 'select', true, ['a', 'b']))->to_array()
        );
        $this->assertSame([], (new param('x', 'text', false))->options);
    }
}
