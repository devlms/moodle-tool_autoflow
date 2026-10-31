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

use tool_autoflow\local\engine\cron_expression;
use tool_autoflow\local\engine\graph;
use tool_autoflow\local\engine\invalid_graph;

/**
 * Saves and loads workflows.
 *
 * The trigger type and its setting (event or cron) are copied out of the graph
 * on save, so the event observer and the schedule task never parse graphs.
 *
 * @package    tool_autoflow
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class workflow_store {
    /** Cache key holding event class name to workflow ids. */
    private const EVENTMAP = 'map';

    /**
     * Save a workflow. An invalid graph is kept as a disabled draft.
     *
     * @param int $id 0 for a new workflow.
     * @param string $name
     * @param string $graphjson
     * @param bool $enabled What the user asked for. Ignored if the graph has errors.
     * @return array id, enabled and errors (each with code and node ids).
     */
    public static function save(int $id, string $name, string $graphjson, bool $enabled): array {
        global $DB, $USER;
        $name = trim($name);
        if ($name === '') {
            throw new \invalid_parameter_exception('A workflow needs a name.');
        }
        try {
            $graph = graph::from_json($graphjson);
        } catch (invalid_graph $e) {
            throw new \invalid_parameter_exception($e->getMessage());
        }
        [$triggertype, $triggerdata] = self::trigger_settings($graph);
        $errors = self::errors($graph, $triggertype, $triggerdata);
        $enabled = $enabled && !$errors;

        $record = (object) [
            'name' => $name,
            'graph' => $graphjson,
            'enabled' => (int) $enabled,
            'triggertype' => $triggertype,
            'triggerdata' => $triggerdata,
            'usermodified' => $USER->id,
            'timemodified' => time(),
        ];
        if ($id) {
            $record->id = $DB->get_field('tool_autoflow_workflow', 'id', ['id' => $id], MUST_EXIST);
            $DB->update_record('tool_autoflow_workflow', $record);
        } else {
            $record->timecreated = $record->timemodified;
            $id = (int) $DB->insert_record('tool_autoflow_workflow', $record);
        }
        self::forget_event_map();
        return ['id' => (int) $id, 'enabled' => $enabled, 'errors' => $errors];
    }

    /**
     * Load a workflow.
     *
     * @param int $id
     * @return \stdClass
     */
    public static function get(int $id): \stdClass {
        global $DB;
        return $DB->get_record('tool_autoflow_workflow', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Delete a workflow and its run history.
     *
     * @param int $id
     */
    public static function delete(int $id): void {
        global $DB;
        $DB->delete_records('tool_autoflow_run', ['workflowid' => $id]);
        $DB->delete_records('tool_autoflow_workflow', ['id' => $id]);
        self::forget_event_map();
    }

    /**
     * Ids of enabled workflows started by an event. Cached; this runs on every event.
     *
     * @param string $eventname Event class name with a leading backslash.
     * @return int[]
     */
    public static function workflows_for_event(string $eventname): array {
        global $DB;
        $cache = \cache::make('tool_autoflow', 'eventmap');
        $map = $cache->get(self::EVENTMAP);
        if ($map === false) {
            $map = [];
            $rows = $DB->get_records(
                'tool_autoflow_workflow',
                ['enabled' => 1, 'triggertype' => 'trigger.event'],
                'id',
                'id, triggerdata'
            );
            foreach ($rows as $row) {
                $map[$row->triggerdata][] = (int) $row->id;
            }
            $cache->set(self::EVENTMAP, $map);
        }
        return $map[$eventname] ?? [];
    }

    /**
     * Enabled workflows started by a schedule.
     *
     * @return \stdClass[] Keyed by id, with id, triggerdata and lastscheduled.
     */
    public static function scheduled(): array {
        global $DB;
        return $DB->get_records(
            'tool_autoflow_workflow',
            ['enabled' => 1, 'triggertype' => 'trigger.schedule'],
            'id',
            'id, triggerdata, lastscheduled'
        );
    }

    /**
     * Drop the cached event map after any change.
     */
    private static function forget_event_map(): void {
        \cache::make('tool_autoflow', 'eventmap')->delete(self::EVENTMAP);
    }

    /**
     * The trigger type and its setting, if the graph has exactly one trigger.
     *
     * @param graph $graph
     * @return string[] Type and setting, empty strings when there is no single trigger.
     */
    private static function trigger_settings(graph $graph): array {
        try {
            $trigger = $graph->trigger();
        } catch (invalid_graph $e) {
            return ['', ''];
        }
        $params = $trigger['data']['params'] ?? [];
        $setting = match ($trigger['type']) {
            'trigger.schedule' => trim((string) ($params['cron'] ?? '')),
            'trigger.event' => self::event_name((string) ($params['eventname'] ?? '')),
            default => '',
        };
        return [$trigger['type'], $setting];
    }

    /**
     * Everything that stops the workflow from running.
     *
     * @param graph $graph
     * @param string $triggertype
     * @param string $triggerdata
     * @return array[]
     */
    private static function errors(graph $graph, string $triggertype, string $triggerdata): array {
        $errors = $graph->errors();
        $registry = node_catalogue::registry();
        $unknown = [];
        foreach ($graph->nodes() as $node) {
            if (!$registry->has($node['type'])) {
                $unknown[] = $node['id'];
            }
        }
        if ($unknown) {
            $errors[] = ['code' => 'unknowntype', 'nodeids' => $unknown];
        }
        if ($triggertype === 'trigger.schedule' && !cron_expression::is_valid($triggerdata)) {
            $errors[] = ['code' => 'badcron', 'nodeids' => [$graph->trigger()['id']]];
        }
        if ($triggertype === 'trigger.event' && !self::is_event($triggerdata)) {
            $errors[] = ['code' => 'badevent', 'nodeids' => [$graph->trigger()['id']]];
        }
        return $errors;
    }

    /**
     * An event class name in the form Moodle uses for $event->eventname.
     *
     * @param string $name
     * @return string
     */
    private static function event_name(string $name): string {
        $name = trim($name);
        return $name === '' ? '' : '\\' . ltrim($name, '\\');
    }

    /**
     * Whether a class is a concrete Moodle event.
     *
     * @param string $classname
     * @return bool
     */
    private static function is_event(string $classname): bool {
        if ($classname === '' || !class_exists($classname)) {
            return false;
        }
        $class = new \ReflectionClass($classname);
        return $class->isSubclassOf(\core\event\base::class) && !$class->isAbstract();
    }
}
