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

namespace tool_autoflow\task;

use tool_autoflow\local\engine\cron_expression;
use tool_autoflow\local\runner;
use tool_autoflow\local\workflow_store;

/**
 * Every minute: queue the scheduled workflows that are due, in the site timezone.
 *
 * @package    tool_autoflow
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class check_schedules extends \core\task\scheduled_task {
    /**
     * Name shown in the task list.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task:checkschedules', 'tool_autoflow');
    }

    /**
     * Run the check now.
     */
    public function execute() {
        $this->check(time());
    }

    /**
     * Queue every scheduled workflow with a matching minute since its last check.
     *
     * @param int $now
     */
    public function check(int $now): void {
        global $DB;
        $timezone = \core_date::get_server_timezone_object();
        foreach (workflow_store::scheduled() as $workflow) {
            // A workflow never checked before only looks at the last minute.
            $from = (int) $workflow->lastscheduled ?: $now - 60;
            if (
                cron_expression::is_valid($workflow->triggerdata)
                    && cron_expression::parse($workflow->triggerdata)->due_between($from, $now, $timezone)
            ) {
                runner::queue((int) $workflow->id, ['time' => $now], 'schedule');
            }
            $DB->set_field('tool_autoflow_workflow', 'lastscheduled', $now, ['id' => $workflow->id]);
        }
    }
}
