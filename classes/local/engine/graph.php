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
 * A workflow graph: nodes and the edges between them, as saved by the editor.
 *
 * Validation follows the same rules as frontend/src/graph.ts. The editor checks
 * them for quick feedback; this class checks them again because the server is the
 * one that runs the workflow.
 *
 * @package    tool_autoflow
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class graph {
    /** @var array[] Nodes in editor order: id, type, data. */
    private array $nodes;

    /** @var array[] Edges: source, target, handle. */
    private array $edges;

    /**
     * Use from_array() or from_json().
     *
     * @param array[] $nodes
     * @param array[] $edges
     */
    private function __construct(array $nodes, array $edges) {
        $this->nodes = $nodes;
        $this->edges = $edges;
    }

    /**
     * Read a graph from the editor's JSON.
     *
     * @param string $json
     * @return self
     */
    public static function from_json(string $json): self {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new invalid_graph('The graph is not valid JSON.');
        }
        return self::from_array($data);
    }

    /**
     * Read a graph from an array with nodes and edges.
     *
     * @param array $data
     * @return self
     */
    public static function from_array(array $data): self {
        if (!is_array($data['nodes'] ?? null) || !is_array($data['edges'] ?? null)) {
            throw new invalid_graph('The graph needs a list of nodes and a list of edges.');
        }
        $nodes = [];
        foreach ($data['nodes'] as $n) {
            if (!is_string($n['id'] ?? null) || !is_string($n['type'] ?? null)) {
                throw new invalid_graph('Every node in the graph needs a text id and type.');
            }
            $nodes[] = ['id' => $n['id'], 'type' => $n['type'], 'data' => is_array($n['data'] ?? null) ? $n['data'] : []];
        }
        $edges = [];
        foreach ($data['edges'] as $e) {
            if (!is_string($e['source'] ?? null) || !is_string($e['target'] ?? null)) {
                throw new invalid_graph('Every edge in the graph needs a source and a target.');
            }
            $edges[] = ['source' => $e['source'], 'target' => $e['target'], 'handle' => $e['sourceHandle'] ?? null];
        }
        return new self($nodes, $edges);
    }

    /**
     * Every problem that stops the graph from running. Empty means valid.
     *
     * @return array[] Each with a code and, for most codes, the node ids involved.
     */
    public function errors(): array {
        $ids = [];
        $duplicates = [];
        foreach ($this->nodes as $n) {
            if (isset($ids[$n['id']])) {
                $duplicates[$n['id']] = true;
            }
            $ids[$n['id']] = true;
        }

        $triggers = array_values(array_map(
            fn($n) => $n['id'],
            array_filter($this->nodes, fn($n) => self::is_trigger($n['type']))
        ));
        if (!$triggers) {
            return [['code' => 'notrigger']];
        }
        $errors = [];
        if (count($triggers) > 1) {
            $errors[] = ['code' => 'manytriggers', 'nodeids' => $triggers];
        }
        if ($duplicates) {
            $errors[] = ['code' => 'duplicateid', 'nodeids' => self::keys($duplicates)];
        }
        $dangling = [];
        foreach ($this->edges as $e) {
            foreach ([$e['target'], $e['source']] as $end) {
                if (!isset($ids[$end])) {
                    $dangling[$end] = true;
                }
            }
        }
        if ($dangling) {
            $errors[] = ['code' => 'danglingedge', 'nodeids' => self::keys($dangling)];
        }
        if ($errors) {
            return $errors;
        }

        $next = array_fill_keys(array_keys($ids), []);
        foreach ($this->edges as $e) {
            $next[$e['source']][] = $e['target'];
        }
        if (self::has_cycle($next)) {
            return [['code' => 'cycle']];
        }

        $reached = [$triggers[0] => true];
        $queue = [$triggers[0]];
        while ($queue) {
            foreach ($next[array_shift($queue)] as $target) {
                if (!isset($reached[$target])) {
                    $reached[$target] = true;
                    $queue[] = $target;
                }
            }
        }
        $unreachable = array_values(array_filter(self::keys($ids), fn($id) => !isset($reached[$id])));
        return $unreachable ? [['code' => 'unreachable', 'nodeids' => $unreachable]] : [];
    }

    /**
     * Whether the graph can run.
     *
     * @return bool
     */
    public function is_valid(): bool {
        return $this->errors() === [];
    }

    /**
     * The trigger node. Call on a valid graph.
     *
     * @return array
     */
    public function trigger(): array {
        foreach ($this->nodes as $n) {
            if (self::is_trigger($n['type'])) {
                return $n;
            }
        }
        throw new invalid_graph('The graph has no trigger.');
    }

    /**
     * One node by id.
     *
     * @param string $id
     * @return array
     */
    public function node(string $id): array {
        foreach ($this->nodes as $n) {
            if ($n['id'] === $id) {
                return $n;
            }
        }
        throw new invalid_graph("The graph has no node $id.");
    }

    /**
     * Ids of the nodes to run after a node. Edges without a handle always follow;
     * edges with a handle follow only when the node chose that branch.
     *
     * @param string $id
     * @param string|null $branch
     * @return string[]
     */
    public function next(string $id, ?string $branch): array {
        $targets = [];
        foreach ($this->edges as $e) {
            if ($e['source'] === $id && ($e['handle'] === null || $e['handle'] === $branch)) {
                $targets[] = $e['target'];
            }
        }
        return $targets;
    }

    /**
     * Whether a node type is a trigger.
     *
     * @param string $type
     * @return bool
     */
    public static function is_trigger(string $type): bool {
        return str_starts_with($type, 'trigger.');
    }

    /**
     * Array keys as strings. PHP turns numeric string keys such as "1" into integers.
     *
     * @param array $array
     * @return string[]
     */
    private static function keys(array $array): array {
        return array_map('strval', array_keys($array));
    }

    /**
     * Depth-first search: reaching a node that is still open means a cycle.
     *
     * @param array $next Adjacency list, node id to target ids.
     * @return bool
     */
    private static function has_cycle(array $next): bool {
        $state = [];
        $visit = function (string $id) use (&$visit, &$state, $next): bool {
            $state[$id] = 'open';
            foreach ($next[$id] as $target) {
                $s = $state[$target] ?? null;
                if ($s === 'open' || ($s === null && $visit($target))) {
                    return true;
                }
            }
            $state[$id] = 'done';
            return false;
        };
        foreach (array_keys($next) as $id) {
            if (!isset($state[$id]) && $visit((string) $id)) {
                return true;
            }
        }
        return false;
    }
}
