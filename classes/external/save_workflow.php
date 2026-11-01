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
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use tool_autoflow\local\workflow_store;

/**
 * Save a workflow from the editor. A graph with problems is kept as a disabled draft
 * and the problems come back for the editor to show.
 *
 * @package    tool_autoflow
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_workflow extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Workflow id, 0 to create one'),
            'name' => new external_value(PARAM_TEXT, 'Name'),
            'graph' => new external_value(PARAM_RAW, 'Graph JSON from the editor'),
            'enabled' => new external_value(PARAM_BOOL, 'Whether to enable it'),
            'runasuserid' => new external_value(PARAM_INT, 'User the workflow runs as, 0 for the person saving', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Save.
     *
     * @param int $id
     * @param string $name
     * @param string $graph
     * @param bool $enabled
     * @param int $runasuserid User the workflow runs as, 0 for the person saving.
     * @return array
     */
    public static function execute(int $id, string $name, string $graph, bool $enabled, int $runasuserid = 0): array {
        $params = self::validate_parameters(
            self::execute_parameters(),
            ['id' => $id, 'name' => $name, 'graph' => $graph, 'enabled' => $enabled, 'runasuserid' => $runasuserid]
        );
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('tool/autoflow:manage', $context);

        $result = workflow_store::save(
            $params['id'],
            $params['name'],
            $params['graph'],
            $params['enabled'],
            $params['runasuserid']
        );
        $result['runasname'] = fullname(\core_user::get_user($result['runasuserid']));
        $result['errors'] = array_map(fn($e) => [
            'code' => $e['code'],
            'message' => get_string('error:' . $e['code'], 'tool_autoflow'),
            'nodeids' => array_map('strval', $e['nodeids'] ?? []),
        ], $result['errors']);
        return $result;
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Workflow id'),
            'enabled' => new external_value(PARAM_BOOL, 'Whether it is enabled now'),
            'runasuserid' => new external_value(PARAM_INT, 'User the workflow runs as'),
            'runasname' => new external_value(PARAM_TEXT, 'Full name of that user'),
            'errors' => new external_multiple_structure(new external_single_structure([
                'code' => new external_value(PARAM_ALPHA, 'Problem code'),
                'message' => new external_value(PARAM_TEXT, 'Problem in words'),
                'nodeids' => new external_multiple_structure(new external_value(PARAM_RAW, 'Node id')),
            ])),
        ]);
    }
}
