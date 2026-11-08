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

/**
 * Deletes run history older than the retention period (90 days by default).
 *
 * Runs store the triggering event and HTTP responses, so keeping them forever grows the table and
 * keeps personal data longer than needed. Set $CFG->forced_plugin_settings['tool_autoflow']['runretentiondays']
 * or the plugin setting runretentiondays to change the period; 0 keeps everything.
 *
 * @package    tool_autoflow
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup_runs extends \core\task\scheduled_task {
    /** @var int Days to keep runs when nothing is set. */
    public const DEFAULT_DAYS = 90;

    /**
     * Task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task:cleanupruns', 'tool_autoflow');
    }

    /**
     * Delete old runs.
     */
    public function execute() {
        global $DB;
        $days = get_config('tool_autoflow', 'runretentiondays');
        $days = $days === false ? self::DEFAULT_DAYS : (int) $days;
        if ($days <= 0) {
            return;
        }
        $before = time() - $days * DAYSECS;
        $count = $DB->count_records_select('tool_autoflow_run', 'timecreated < ?', [$before]);
        $DB->delete_records_select('tool_autoflow_run', 'timecreated < ?', [$before]);
        mtrace("Autoflow: deleted $count runs older than $days days.");
    }
}
