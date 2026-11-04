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

namespace tool_autoflow\local;

use tool_autoflow\local\engine\nodes\condition_node;
use tool_autoflow\local\engine\nodes\log_node;
use tool_autoflow\local\engine\nodes\set_variable_node;
use tool_autoflow\local\engine\nodes\trigger_node;
use tool_autoflow\local\engine\registry;
use tool_autoflow\local\nodes\add_to_cohort_node;
use tool_autoflow\local\nodes\add_to_group_node;
use tool_autoflow\local\nodes\call_function_node;
use tool_autoflow\local\nodes\enrol_user_node;
use tool_autoflow\local\nodes\http_request_node;
use tool_autoflow\local\nodes\send_notification_node;

/**
 * Every node type this site can use: the engine's own and the Moodle ones.
 *
 * @package    tool_autoflow
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class node_catalogue {
    /**
     * The registry used to run workflows.
     *
     * @return registry
     */
    public static function registry(): registry {
        return new registry(
            new trigger_node('trigger.manual'),
            new trigger_node('trigger.schedule'),
            new trigger_node('trigger.event'),
            new condition_node(),
            new set_variable_node(),
            new log_node(),
            new http_request_node(),
            new send_notification_node(),
            new enrol_user_node(),
            new add_to_group_node(),
            new add_to_cohort_node(),
            new call_function_node(),
        );
    }

    /**
     * Palette entries for the editor: type, label, category, branches and parameters with labels.
     *
     * @return array[]
     */
    public static function describe(): array {
        $strings = get_string_manager();
        $types = [];
        foreach (self::registry()->types() as $type) {
            $node = self::registry()->get($type);
            $key = 'node:' . $type;
            $types[] = [
                'type' => $type,
                'label' => $strings->string_exists($key, 'tool_autoflow') ? get_string($key, 'tool_autoflow') : $type,
                'description' => $strings->string_exists("$key:help", 'tool_autoflow')
                    ? get_string("$key:help", 'tool_autoflow') : '',
                'category' => strtok($type, '.'),
                'branches' => $node->branches(),
                'params' => array_map(function ($param) use ($strings) {
                    $labels = [];
                    foreach ($param->options as $option) {
                        $labels[$option] = get_string("option:$option", 'tool_autoflow');
                    }
                    return $param->to_array() + [
                        'label' => $strings->string_exists("param:{$param->name}", 'tool_autoflow')
                            ? get_string("param:{$param->name}", 'tool_autoflow') : $param->name,
                        'optionlabels' => $labels,
                    ];
                }, $node->params()),
            ];
        }
        return $types;
    }

    /**
     * Every concrete event class on the site, for the event trigger picker.
     *
     * @return string[] Class names with a leading backslash, sorted.
     */
    public static function events(): array {
        global $CFG;
        // Loading a deprecated event class emits debugging; the core event list report silences it the same way.
        [$debug, $display, $developer] = [$CFG->debug, $CFG->debugdisplay, $CFG->debugdeveloper];
        [$CFG->debug, $CFG->debugdisplay, $CFG->debugdeveloper] = [0, false, false];
        $events = [];
        foreach (array_keys(\core_component::get_component_classes_in_namespace(null, 'event')) as $class) {
            if (is_a($class, \core\event\base::class, true) && !(new \ReflectionClass($class))->isAbstract()) {
                $events[] = '\\' . $class;
            }
        }
        [$CFG->debug, $CFG->debugdisplay, $CFG->debugdeveloper] = [$debug, $display, $developer];
        sort($events);
        return $events;
    }

    /**
     * Every web service function installed on the site, for the call function node.
     *
     * @return string[] Sorted names.
     */
    public static function functions(): array {
        global $DB;
        // Sorted here, not in SQL: database collations order underscores differently.
        $names = $DB->get_fieldset_select('external_functions', 'name', '1 = 1');
        sort($names, SORT_STRING);
        return $names;
    }
}
