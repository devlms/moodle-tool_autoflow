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

use tool_autoflow\local\runner;

/**
 * Runs one workflow. Queued by runner::queue() with the run-as user set, so
 * Moodle switches to that user before execute().
 *
 * @package    tool_autoflow
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class run_workflow extends \core\task\adhoc_task {
    /**
     * Name shown in the task logs.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task:runworkflow', 'tool_autoflow');
    }

    /**
     * Run it.
     */
    public function execute() {
        $data = json_decode($this->get_custom_data_as_string(), true);
        $run = runner::run((int) $data['workflowid'], $data['trigger'] ?? [], $data['triggertype'], (int) $data['depth']);
        if ($run) {
            mtrace("Workflow {$data['workflowid']}: {$run->status}" . ($run->error ? " ({$run->error})" : ''));
        }
    }
}
