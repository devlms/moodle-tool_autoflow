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

/**
 * Test data generator for tool_autoflow.
 *
 * @package    tool_autoflow
 * @category   test
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_autoflow_generator extends component_generator_base {
    /**
     * Create a workflow: a manual trigger followed by a log node with the given message.
     *
     * @param array $record name, message, enabled
     * @return int Workflow id.
     */
    public function create_workflow(array $record = []): int {
        $graph = json_encode([
            'nodes' => [
                ['id' => 't', 'type' => 'trigger.manual', 'data' => [], 'position' => ['x' => 0, 'y' => 0]],
                ['id' => 'log', 'type' => 'action.log', 'data' => ['params' => ['message' => $record['message'] ?? 'Hello']],
                    'position' => ['x' => 0, 'y' => 120]],
            ],
            'edges' => [['id' => 't-log', 'source' => 't', 'target' => 'log']],
        ]);
        return \tool_autoflow\local\workflow_store::save(
            0,
            $record['name'] ?? 'Workflow',
            $graph,
            (bool) ($record['enabled'] ?? true)
        )['id'];
    }
}
