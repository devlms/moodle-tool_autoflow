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
 * Runs a workflow graph from its trigger.
 *
 * Nodes run in the order they are reached, breadth first. A node reached by two
 * paths runs once per arrival, the same as n8n and Zapier paths. A step limit
 * stops graphs that fan out too far.
 *
 * @package    tool_autoflow
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class executor {
    /**
     * Create an executor.
     *
     * @param registry $registry Node types the run may use.
     * @param int $maxsteps Most node runs allowed in one workflow run.
     */
    public function __construct(
        /** @var registry Node types. */
        private readonly registry $registry,
        /** @var int Step limit. */
        private readonly int $maxsteps = 200,
    ) {
    }

    /**
     * Run the graph.
     *
     * @param graph $graph
     * @param array $trigger Data about what started the run, readable as {{trigger.*}}.
     * @return run_result
     */
    public function run(graph $graph, array $trigger): run_result {
        $context = ['trigger' => $trigger, 'nodes' => [], 'vars' => []];
        $errors = $graph->errors();
        if ($errors) {
            return new run_result(
                'failed',
                [],
                $context,
                'The workflow is not valid: ' . implode(', ', array_column($errors, 'code')) . '.'
            );
        }

        $steps = [];
        $queue = [$graph->trigger()['id']];
        while ($queue) {
            if (count($steps) === $this->maxsteps) {
                return new run_result('failed', $steps, $context, "Stopped after {$this->maxsteps} steps.");
            }
            $node = $graph->node(array_shift($queue));
            $step = ['node' => $node['id'], 'type' => $node['type']];
            try {
                $params = template::resolve($node['data']['params'] ?? [], $context);
                $result = $this->registry->get($node['type'])->execute($params, $context);
            } catch (node_failed $e) {
                $steps[] = $step + ['status' => 'error', 'branch' => null, 'error' => $e->getMessage()];
                return new run_result('failed', $steps, $context, $e->getMessage());
            }
            $context['nodes'][$node['id']] = $result->output;
            $context['vars'] = array_merge($context['vars'], $result->vars);
            $steps[] = $step + ['status' => 'ok', 'branch' => $result->branch];
            array_push($queue, ...$graph->next($node['id'], $result->branch));
        }
        return new run_result('success', $steps, $context);
    }
}
