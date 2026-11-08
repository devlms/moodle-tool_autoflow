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

namespace tool_autoflow\output;

use core\output\renderable;
use core\output\renderer_base;
use core\output\templatable;
use tool_autoflow\local\display;

/**
 * Every workflow on the site with its trigger, who it runs as and how its last run went.
 *
 * @package    tool_autoflow
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class workflow_list implements renderable, templatable {
    /**
     * Create the list.
     *
     * @param int $now
     */
    public function __construct(
        /** @var int Current time. */
        private readonly int $now,
    ) {
    }

    /**
     * Data for tool_autoflow/workflow_list.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $DB;
        $fields = 'id, name, enabled, triggertype, runasuserid, timemodified';
        $workflows = $DB->get_records('tool_autoflow_workflow', null, 'name ASC', $fields);
        // Not user_get_users_by_id(): it is deprecated in 5.3 and its replacement does not exist in 4.5.
        $users = $DB->get_records_list('user', 'id', array_filter(array_column($workflows, 'runasuserid')));
        // The newest run of each workflow.
        $lastruns = $DB->get_records_sql(
            'SELECT r.workflowid, r.status, r.timecreated
               FROM {tool_autoflow_run} r
               JOIN (SELECT workflowid, MAX(id) AS id FROM {tool_autoflow_run} GROUP BY workflowid) last ON last.id = r.id'
        );
        $index = new \moodle_url('/admin/tool/autoflow/index.php');

        $rows = [];
        foreach ($workflows as $w) {
            $last = $lastruns[$w->id] ?? null;
            $rows[] = [
                'id' => (int) $w->id,
                // Unescaped here: the template escapes once.
                'name' => format_string($w->name, true, ['escape' => false]),
                'enabled' => (bool) $w->enabled,
                'statuslabel' => get_string($w->enabled ? 'enabled' : 'disabled', 'admin'),
                'statusbadge' => 'badge ' . ($w->enabled ? 'bg-success text-white' : 'bg-secondary text-dark'),
                'trigger' => display::string_or('node:' . $w->triggertype, ''),
                'runas' => isset($users[$w->runasuserid]) ? fullname($users[$w->runasuserid]) : '',
                'hasrun' => $last !== null,
                'lastrun' => $last ? display::ago((int) $last->timecreated, $this->now) : get_string('never', 'tool_autoflow'),
                'lastrunfull' => $last ? userdate((int) $last->timecreated) : '',
                'laststatus' => $last ? display::run_status($last->status) : '',
                'lastbadge' => $last ? display::run_badge($last->status) : '',
                'editurl' => (new \moodle_url('/admin/tool/autoflow/edit.php', ['id' => $w->id]))->out(false),
                'runsurl' => (new \moodle_url('/admin/tool/autoflow/runs.php', ['id' => $w->id]))->out(false),
                'runurl' => (new \moodle_url($index, ['action' => 'run', 'id' => $w->id, 'sesskey' => sesskey()]))->out(false),
                'deleteurl' => (new \moodle_url($index, ['action' => 'delete', 'id' => $w->id]))->out(false),
            ];
        }
        return [
            'newurl' => (new \moodle_url('/admin/tool/autoflow/edit.php'))->out(false),
            'hasworkflows' => !empty($rows),
            'workflows' => $rows,
        ];
    }
}
