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

use tool_autoflow\local\engine\executor;
use tool_autoflow\local\engine\graph;
use tool_autoflow\local\engine\invalid_graph;
use tool_autoflow\task\run_workflow;

/**
 * Queues workflow runs as ad hoc tasks and runs them.
 *
 * Every run carries a depth. A run started by an event that another run fired is
 * one level deeper. Past MAX_DEPTH nothing is queued, so a workflow that triggers
 * itself stops instead of looping, while ordinary chains of workflows still work.
 *
 * @package    tool_autoflow
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class runner {
    /** Deepest allowed chain of runs started by other runs. */
    public const MAX_DEPTH = 3;

    /** @var int|null Depth of the run executing in this process, or null. */
    private static ?int $depth = null;

    /**
     * Queue a run.
     *
     * @param int $workflowid
     * @param array $trigger Data about what started it.
     * @param string $triggertype manual, schedule or event.
     * @param int $depth 0 unless another run caused this one.
     * @return bool False when the chain is already too deep.
     */
    public static function queue(int $workflowid, array $trigger, string $triggertype, int $depth = 0): bool {
        global $DB;
        if ($depth > self::MAX_DEPTH) {
            return false;
        }
        $runas = $DB->get_field('tool_autoflow_workflow', 'runasuserid', ['id' => $workflowid]);
        $task = new run_workflow();
        $task->set_custom_data(['workflowid' => $workflowid, 'trigger' => $trigger, 'triggertype' => $triggertype,
            'depth' => $depth]);
        $task->set_userid($runas ?: get_admin()->id);
        \core\task\manager::queue_adhoc_task($task);
        return true;
    }

    /**
     * Depth of the run executing now, or null when no run is executing.
     *
     * @return int|null
     */
    public static function current_depth(): ?int {
        return self::$depth;
    }

    /**
     * Run a workflow now and record the result.
     *
     * @param int $workflowid
     * @param array $trigger
     * @param string $triggertype
     * @param int $depth
     * @return \stdClass|null The run record, or null when the workflow is gone or disabled.
     */
    public static function run(int $workflowid, array $trigger, string $triggertype, int $depth): ?\stdClass {
        global $DB;
        $workflow = $DB->get_record('tool_autoflow_workflow', ['id' => $workflowid]);
        if (!$workflow || (!$workflow->enabled && $triggertype !== 'manual')) {
            return null;
        }
        $run = (object) [
            'workflowid' => $workflowid,
            'status' => 'running',
            'triggertype' => $triggertype,
            'userid' => self::trigger_user($trigger),
            'timecreated' => time(),
        ];
        $run->id = $DB->insert_record('tool_autoflow_run', $run);

        $previous = self::$depth;
        self::$depth = $depth;
        try {
            $result = (new executor(node_catalogue::registry()))->run(graph::from_json($workflow->graph), $trigger);
            $run->status = $result->status;
            $run->log = json_encode($result->steps);
            $run->error = $result->error;
        } catch (invalid_graph $e) {
            $run->status = 'failed';
            $run->log = '[]';
            $run->error = $e->getMessage();
        } finally {
            self::$depth = $previous;
        }
        $run->timefinished = time();
        $DB->update_record('tool_autoflow_run', $run);
        return $DB->get_record('tool_autoflow_run', ['id' => $run->id]);
    }

    /**
     * The user a trigger is about, if any.
     *
     * @param array $trigger
     * @return int|null
     */
    private static function trigger_user(array $trigger): ?int {
        $userid = $trigger['relateduserid'] ?? $trigger['userid'] ?? null;
        return $userid ? (int) $userid : null;
    }
}
