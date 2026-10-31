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

/**
 * Tests for graph parsing and validation. The rules match frontend/src/graph.ts.
 *
 * @package    tool_autoflow
 * @category   test
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_autoflow\local\engine\graph
 */
final class graph_test extends \basic_testcase {
    /**
     * A node array.
     *
     * @param string $id
     * @param string $type
     * @param array $data
     * @return array
     */
    private static function node(string $id, string $type = 'action.log', array $data = []): array {
        return ['id' => $id, 'type' => $type, 'data' => $data];
    }

    /**
     * An edge array.
     *
     * @param string $source
     * @param string $target
     * @param string|null $handle
     * @return array
     */
    private static function edge(string $source, string $target, ?string $handle = null): array {
        return ['id' => "$source-$target", 'source' => $source, 'target' => $target, 'sourceHandle' => $handle];
    }

    /**
     * Error codes for a graph.
     *
     * @param array $nodes
     * @param array $edges
     * @return string[]
     */
    private static function codes(array $nodes, array $edges): array {
        return array_column(graph::from_array(['nodes' => $nodes, 'edges' => $edges])->errors(), 'code');
    }

    public function test_valid_chain_has_no_errors(): void {
        $graph = graph::from_array([
            'nodes' => [self::node('t', 'trigger.manual'), self::node('a'), self::node('b')],
            'edges' => [self::edge('t', 'a'), self::edge('a', 'b')],
        ]);
        $this->assertSame([], $graph->errors());
        $this->assertTrue($graph->is_valid());
        $this->assertSame('t', $graph->trigger()['id']);
    }

    public function test_requires_a_trigger(): void {
        $this->assertSame(['notrigger'], self::codes([self::node('a')], []));
        $this->assertSame(['notrigger'], self::codes([], []));
    }

    public function test_allows_one_trigger_only(): void {
        $graph = graph::from_array(['nodes' => [self::node('t1', 'trigger.manual'), self::node('t2', 'trigger.event')],
            'edges' => []]);
        $this->assertSame([['code' => 'manytriggers', 'nodeids' => ['t1', 't2']]], $graph->errors());
        $this->assertFalse($graph->is_valid());
    }

    public function test_rejects_duplicate_ids(): void {
        $graph = graph::from_array(['nodes' => [self::node('t', 'trigger.manual'), self::node('a'), self::node('a')],
            'edges' => [self::edge('t', 'a')]]);
        $this->assertContains(['code' => 'duplicateid', 'nodeids' => ['a']], $graph->errors());
    }

    public function test_rejects_dangling_edges(): void {
        $graph = graph::from_array(['nodes' => [self::node('t', 'trigger.manual')],
            'edges' => [self::edge('t', 'ghost'), self::edge('ghost2', 't')]]);
        $this->assertContains(['code' => 'danglingedge', 'nodeids' => ['ghost', 'ghost2']], $graph->errors());
    }

    public function test_rejects_cycles_including_self_loops(): void {
        $this->assertSame(['cycle'], self::codes(
            [self::node('t', 'trigger.manual'), self::node('a'), self::node('b')],
            [self::edge('t', 'a'), self::edge('a', 'b'), self::edge('b', 'a')]
        ));
        $this->assertSame(['cycle'], self::codes(
            [self::node('t', 'trigger.manual'), self::node('a')],
            [self::edge('t', 'a'), self::edge('a', 'a')]
        ));
    }

    public function test_reports_unreachable_nodes(): void {
        $graph = graph::from_array([
            'nodes' => [self::node('t', 'trigger.manual'), self::node('a'), self::node('lonely'), self::node('island')],
            'edges' => [self::edge('t', 'a'), self::edge('lonely', 'island')],
        ]);
        $this->assertSame([['code' => 'unreachable', 'nodeids' => ['lonely', 'island']]], $graph->errors());
    }

    public function test_long_chains_of_rejoining_branches_are_fast(): void {
        $nodes = [self::node('t', 'trigger.manual'), self::node('j0')];
        $edges = [self::edge('t', 'j0')];
        for ($i = 0; $i < 30; $i++) {
            array_push($nodes, self::node("l$i"), self::node("r$i"), self::node('j' . ($i + 1)));
            array_push(
                $edges,
                self::edge("j$i", "l$i"),
                self::edge("j$i", "r$i"),
                self::edge("l$i", 'j' . ($i + 1)),
                self::edge("r$i", 'j' . ($i + 1))
            );
        }
        foreach ([$nodes, array_reverse($nodes)] as $order) {
            $started = microtime(true);
            $this->assertSame([], graph::from_array(['nodes' => $order, 'edges' => $edges])->errors());
            $this->assertLessThan(0.5, microtime(true) - $started);
        }
    }

    public function test_outgoing_edges_follow_handles(): void {
        $graph = graph::from_array([
            'nodes' => [self::node('t', 'trigger.manual'), self::node('if', 'logic.condition'), self::node('y'),
                self::node('n'), self::node('always')],
            'edges' => [self::edge('t', 'if'), self::edge('if', 'y', 'true'), self::edge('if', 'n', 'false'),
                self::edge('if', 'always')],
        ]);
        $this->assertSame(['y', 'always'], $graph->next('if', 'true'));
        $this->assertSame(['n', 'always'], $graph->next('if', 'false'));
        $this->assertSame(['always'], $graph->next('if', null));
        $this->assertSame([], $graph->next('y', null));
        $this->assertSame('logic.condition', $graph->node('if')['type']);
    }

    public function test_from_json_reads_editor_output(): void {
        $json = json_encode(['nodes' => [self::node('t', 'trigger.manual', ['label' => 'Go'])], 'edges' => []]);
        $graph = graph::from_json($json);
        $this->assertTrue($graph->is_valid());
        $this->assertSame(['label' => 'Go'], $graph->node('t')['data']);
    }

    public function test_from_json_rejects_malformed_input(): void {
        foreach (
            ['not json', '[]', '{"nodes": "x", "edges": []}', '{"nodes": [{"id": 1}], "edges": []}',
                '{"nodes": [{"id": "a"}], "edges": []}', '{"nodes": [{"type": "trigger.manual"}], "edges": []}',
                '{"nodes": [{"id": "a", "type": "x"}], "edges": [{"source": "a"}]}'] as $bad
        ) {
            try {
                graph::from_json($bad);
                $this->fail("Accepted: $bad");
            } catch (invalid_graph $e) {
                $this->assertStringContainsString('graph', $e->getMessage());
            }
        }
    }

    public function test_missing_data_defaults_to_empty(): void {
        $graph = graph::from_json('{"nodes": [{"id": "t", "type": "trigger.manual"}], "edges": []}');
        $this->assertSame([], $graph->node('t')['data']);
    }

    public function test_unknown_node_lookup_fails(): void {
        $this->expectException(invalid_graph::class);
        graph::from_array(['nodes' => [self::node('t', 'trigger.manual')], 'edges' => []])->node('nope');
    }

    public function test_numeric_ids_stay_strings(): void {
        $graph = graph::from_array([
            'nodes' => [self::node('1', 'trigger.manual'), self::node('2'), self::node('2'), self::node('3')],
            'edges' => [self::edge('1', '2')],
        ]);
        $this->assertSame([['code' => 'duplicateid', 'nodeids' => ['2']]], $graph->errors());

        $graph = graph::from_array([
            'nodes' => [self::node('1', 'trigger.manual'), self::node('2'), self::node('3')],
            'edges' => [self::edge('1', '2'), self::edge('9', '2')],
        ]);
        $this->assertSame([['code' => 'danglingedge', 'nodeids' => ['9']]], $graph->errors());

        $graph = graph::from_array([
            'nodes' => [self::node('1', 'trigger.manual'), self::node('2'), self::node('3')],
            'edges' => [self::edge('1', '2')],
        ]);
        $this->assertSame([['code' => 'unreachable', 'nodeids' => ['3']]], $graph->errors());
    }

    public function test_reports_every_structural_error_at_once(): void {
        $graph = graph::from_array([
            'nodes' => [self::node('t1', 'trigger.manual'), self::node('t2', 'trigger.event'), self::node('a'),
                self::node('a')],
            'edges' => [self::edge('t1', 'ghost')],
        ]);
        $this->assertSame(['manytriggers', 'duplicateid', 'danglingedge'], array_column($graph->errors(), 'code'));
    }

    public function test_trigger_returns_the_whole_node(): void {
        $graph = graph::from_array(['nodes' => [self::node('a'), self::node('t', 'trigger.manual', ['x' => 1])],
            'edges' => [self::edge('t', 'a')]]);
        $this->assertSame(['id' => 't', 'type' => 'trigger.manual', 'data' => ['x' => 1]], $graph->trigger());
    }

    public function test_trigger_on_a_graph_without_one_fails(): void {
        $this->expectException(invalid_graph::class);
        graph::from_array(['nodes' => [self::node('a')], 'edges' => []])->trigger();
    }

    public function test_numeric_ids_in_a_cycle(): void {
        $graph = graph::from_array(['nodes' => [self::node('1', 'trigger.manual'), self::node('2'), self::node('3')],
            'edges' => [self::edge('1', '2'), self::edge('2', '3'), self::edge('3', '2')]]);
        $this->assertSame([['code' => 'cycle']], $graph->errors());
    }

    public function test_is_trigger_checks_the_type_prefix(): void {
        $this->assertTrue(graph::is_trigger('trigger.event'));
        $this->assertFalse(graph::is_trigger('action.trigger.x'));
        $this->assertFalse(graph::is_trigger('triggerx'));
    }
}
