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
use core_external\external_single_structure;
use core_external\external_value;
use tool_autoflow\local\node_catalogue;
use tool_autoflow\local\workflow_store;

/**
 * Everything the editor needs to open a workflow: the workflow, the node palette,
 * the event and function lists and the interface strings.
 *
 * @package    tool_autoflow
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_editor extends external_api {
    /** Strings the editor shows, by key. */
    private const STRINGS = ['save', 'saved', 'runnow', 'runqueued', 'enabled', 'workflowname', 'palette', 'nodesettings',
        'selectnode', 'deletenode', 'problems', 'saving', 'savefailed', 'branch:true', 'branch:false', 'choose', 'workflowruns',
        'cat:trigger', 'cat:logic', 'cat:action', 'cat:moodle', 'error:notrigger', 'error:manytriggers', 'error:duplicateid',
        'error:danglingedge', 'error:cycle', 'error:unreachable', 'invalidjson'];

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'workflowid' => new external_value(PARAM_INT, 'Workflow id, 0 for a new workflow'),
        ]);
    }

    /**
     * Load the editor data.
     *
     * @param int $workflowid
     * @return array
     */
    public static function execute(int $workflowid): array {
        ['workflowid' => $workflowid] = self::validate_parameters(self::execute_parameters(), ['workflowid' => $workflowid]);
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('tool/autoflow:manage', $context);

        $workflow = $workflowid ? workflow_store::get($workflowid)
            : (object) ['id' => 0, 'name' => '', 'enabled' => 0, 'graph' => json_encode(['nodes' => [], 'edges' => []])];
        $strings = [];
        foreach (self::STRINGS as $key) {
            $strings[$key] = get_string($key, 'tool_autoflow');
        }
        return [
            'id' => (int) $workflow->id,
            'name' => $workflow->name,
            'enabled' => (bool) $workflow->enabled,
            'graph' => $workflow->graph,
            'nodetypes' => json_encode(node_catalogue::describe()),
            'events' => json_encode(node_catalogue::events()),
            'functions' => json_encode(node_catalogue::functions()),
            'strings' => json_encode($strings),
        ];
    }

    /**
     * Return structure. Graph, palette, events and strings travel as JSON text.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Workflow id, 0 when new'),
            'name' => new external_value(PARAM_TEXT, 'Name'),
            'enabled' => new external_value(PARAM_BOOL, 'Enabled'),
            'graph' => new external_value(PARAM_RAW, 'Graph JSON'),
            'nodetypes' => new external_value(PARAM_RAW, 'Palette JSON'),
            'events' => new external_value(PARAM_RAW, 'Event class names JSON'),
            'functions' => new external_value(PARAM_RAW, 'Web service function names JSON'),
            'strings' => new external_value(PARAM_RAW, 'Interface strings JSON'),
        ]);
    }
}
