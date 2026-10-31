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
 * Tests for the workflow executor.
 *
 * @package    tool_autoflow
 * @category   test
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_autoflow\local\engine\executor
 * @covers     \tool_autoflow\local\engine\registry
 * @covers     \tool_autoflow\local\engine\run_result
 * @covers     \tool_autoflow\local\engine\nodes\trigger_node
 * @covers     \tool_autoflow\local\engine\nodes\log_node
 * @covers     \tool_autoflow\local\engine\nodes\set_variable_node
 */
final class executor_test extends \basic_testcase {
    /**
     * A registry with the engine's own nodes plus test doubles.
     *
     * @param node_type ...$extra
     * @return registry
     */
    private static function registry(node_type ...$extra): registry {
        return new registry(
            new trigger_node('trigger.manual'),
            new trigger_node('trigger.event'),
            new condition_node(),
            new log_node(),
            new set_variable_node(),
            ...$extra
        );
    }

    /**
     * Build a graph from compact node and edge lists.
     *
     * @param array $nodes id => [type, params]
     * @param array $edges [source, target, handle?]
     * @return graph
     */
    private static function graph(array $nodes, array $edges): graph {
        $n = [];
        foreach ($nodes as $id => [$type, $params]) {
            $n[] = ['id' => (string) $id, 'type' => $type, 'data' => ['params' => $params]];
        }
        $e = array_map(
            fn($x) => ['id' => "$x[0]-$x[1]", 'source' => $x[0], 'target' => $x[1], 'sourceHandle' => $x[2] ?? null],
            $edges
        );
        return graph::from_array(['nodes' => $n, 'edges' => $e]);
    }

    public function test_runs_a_chain_and_passes_trigger_data_along(): void {
        $graph = self::graph([
            't' => ['trigger.event', []],
            'log' => ['action.log', ['message' => 'User {{trigger.relateduserid}} in course {{trigger.courseid}}']],
        ], [['t', 'log']]);
        $result = (new executor(self::registry()))->run($graph, ['relateduserid' => 5, 'courseid' => 9]);

        $this->assertSame('success', $result->status);
        $this->assertNull($result->error);
        $this->assertSame(['t', 'log'], array_column($result->steps, 'node'));
        $this->assertSame(['message' => 'User 5 in course 9'], $result->context['nodes']['log']);
        $this->assertSame(['relateduserid' => 5, 'courseid' => 9], $result->context['nodes']['t']);
        $this->assertSame('ok', $result->steps[1]['status']);
        $this->assertSame('action.log', $result->steps[1]['type']);
    }

    public function test_condition_follows_only_the_chosen_branch(): void {
        $graph = self::graph([
            't' => ['trigger.event', []],
            'if' => ['logic.condition', ['left' => '{{trigger.grade}}', 'operator' => 'gte', 'right' => 50]],
            'pass' => ['action.log', ['message' => 'passed']],
            'fail' => ['action.log', ['message' => 'failed']],
        ], [['t', 'if'], ['if', 'pass', 'true'], ['if', 'fail', 'false']]);
        $executor = new executor(self::registry());

        $this->assertSame(['t', 'if', 'pass'], array_column($executor->run($graph, ['grade' => 72])->steps, 'node'));
        $this->assertSame(['t', 'if', 'fail'], array_column($executor->run($graph, ['grade' => 31])->steps, 'node'));
        $this->assertSame('true', $executor->run($graph, ['grade' => 72])->steps[1]['branch']);
    }

    public function test_variables_are_visible_to_later_nodes(): void {
        $graph = self::graph([
            't' => ['trigger.manual', []],
            'set' => ['logic.set', ['name' => 'greeting', 'value' => 'Hello {{trigger.name}}']],
            'log' => ['action.log', ['message' => '{{vars.greeting}}!']],
        ], [['t', 'set'], ['set', 'log']]);
        $result = (new executor(self::registry()))->run($graph, ['name' => 'Amina']);
        $this->assertSame('Hello Amina!', $result->context['nodes']['log']['message']);
        $this->assertSame(['greeting' => 'Hello Amina'], $result->context['vars']);
    }

    public function test_a_failing_node_stops_the_run(): void {
        $boom = new class implements node_type {
            /**
             * Type name.
             *
             * @return string
             */
            public function type(): string {
                return 'test.boom';
            }

            /**
             * No parameters.
             *
             * @return array
             */
            public function params(): array {
                return [];
            }

            /**
             * No branches.
             *
             * @return array
             */
            public function branches(): array {
                return [];
            }

            /**
             * Always fails.
             *
             * @param array $params
             * @param array $context
             * @return node_result
             */
            public function execute(array $params, array $context): node_result {
                throw new node_failed('No mail server');
            }
        };
        $graph = self::graph([
            't' => ['trigger.manual', []],
            'b' => ['test.boom', []],
            'after' => ['action.log', ['message' => 'never']],
        ], [['t', 'b'], ['b', 'after']]);
        $result = (new executor(self::registry($boom)))->run($graph, []);

        $this->assertSame('failed', $result->status);
        $this->assertSame('No mail server', $result->error);
        $this->assertSame(['t', 'b'], array_column($result->steps, 'node'));
        $this->assertSame('error', $result->steps[1]['status']);
        $this->assertSame('No mail server', $result->steps[1]['error']);
    }

    public function test_unknown_node_type_fails_the_run(): void {
        $graph = self::graph(['t' => ['trigger.manual', []], 'x' => ['vendor.missing', []]], [['t', 'x']]);
        $result = (new executor(self::registry()))->run($graph, []);
        $this->assertSame('failed', $result->status);
        $this->assertStringContainsString('vendor.missing', $result->error);
    }

    public function test_invalid_graph_does_not_start(): void {
        $graph = self::graph(['a' => ['action.log', ['message' => 'x']]], []);
        $result = (new executor(self::registry()))->run($graph, []);
        $this->assertSame('failed', $result->status);
        $this->assertSame([], $result->steps);
        $this->assertSame('The workflow is not valid: notrigger.', $result->error);
    }

    public function test_step_limit_stops_runaway_fan_out(): void {
        // A wide fan-out that rejoins: each join node runs once per arrival.
        $nodes = ['t' => ['trigger.manual', []], 'j0' => ['action.log', ['message' => 'x']]];
        $edges = [['t', 'j0']];
        for ($i = 0; $i < 12; $i++) {
            $nodes["l$i"] = ['action.log', ['message' => 'x']];
            $nodes["r$i"] = ['action.log', ['message' => 'x']];
            $nodes['j' . ($i + 1)] = ['action.log', ['message' => 'x']];
            array_push($edges, ["j$i", "l$i"], ["j$i", "r$i"], ["l$i", 'j' . ($i + 1)], ["r$i", 'j' . ($i + 1)]);
        }
        $result = (new executor(self::registry(), 50))->run(self::graph($nodes, $edges), []);
        $this->assertSame('failed', $result->status);
        $this->assertStringContainsString('50 steps', $result->error);
        $this->assertCount(50, $result->steps);
    }

    public function test_exactly_the_step_limit_is_allowed(): void {
        $graph = self::graph(['t' => ['trigger.manual', []], 'a' => ['action.log', ['message' => 'x']]], [['t', 'a']]);
        $this->assertSame('success', (new executor(self::registry(), 2))->run($graph, [])->status);
    }

    public function test_parameters_can_read_earlier_node_outputs(): void {
        $graph = self::graph([
            't' => ['trigger.manual', []],
            'if' => ['logic.condition', ['left' => 1, 'operator' => 'eq', 'right' => 1]],
            'log' => ['action.log', ['message' => 'Condition said {{nodes.if.result}}']],
        ], [['t', 'if'], ['if', 'log', 'true']]);
        $result = (new executor(self::registry()))->run($graph, []);
        $this->assertSame('Condition said true', $result->context['nodes']['log']['message']);
    }

    public function test_registry_lists_types_and_rejects_unknown(): void {
        $registry = self::registry();
        $this->assertSame(['trigger.manual', 'trigger.event', 'logic.condition', 'action.log', 'logic.set'], $registry->types());
        $this->assertInstanceOf(log_node::class, $registry->get('action.log'));
        $this->assertTrue($registry->has('logic.set'));
        $this->assertFalse($registry->has('nope'));
        $this->expectException(node_failed::class);
        $registry->get('nope');
    }

    public function test_set_variable_needs_a_name(): void {
        $this->expectException(node_failed::class);
        (new set_variable_node())->execute(['name' => '', 'value' => 1], []);
    }

    public function test_set_variable_keeps_the_value_type(): void {
        $result = (new set_variable_node())->execute(['name' => 'n', 'value' => 3], []);
        $this->assertSame(['n' => 3], $result->vars);
        $this->assertSame(['value' => 3], $result->output);
    }

    public function test_log_node_turns_values_into_text(): void {
        $this->assertSame(['message' => ''], (new log_node())->execute([], [])->output);
        $this->assertSame(['message' => '7'], (new log_node())->execute(['message' => 7], [])->output);
    }

    public function test_trigger_node_reports_its_type(): void {
        $this->assertSame('trigger.event', (new trigger_node('trigger.event'))->type());
    }

    public function test_default_step_limit_is_200(): void {
        $chain = function (int $length): graph {
            $nodes = ['n0' => ['trigger.manual', []]];
            $edges = [];
            for ($i = 1; $i < $length; $i++) {
                $nodes["n$i"] = ['action.log', ['message' => 'x']];
                $edges[] = ['n' . ($i - 1), "n$i"];
            }
            return self::graph($nodes, $edges);
        };
        $executor = new executor(self::registry());
        $this->assertSame('success', $executor->run($chain(200), [])->status);
        $this->assertSame('Stopped after 200 steps.', $executor->run($chain(201), [])->error);
    }

    public function test_set_variable_name_must_be_text(): void {
        $this->expectException(node_failed::class);
        (new set_variable_node())->execute(['name' => ['a'], 'value' => 1], []);
    }
}
