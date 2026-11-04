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

namespace tool_autoflow\local\nodes;

use tool_autoflow\local\engine\node_result;
use tool_autoflow\local\engine\node_type;
use tool_autoflow\local\engine\param;

/**
 * Removes a user's manual enrolment from a course.
 *
 * It calls enrol_manual_unenrol_users, so the user the workflow runs as needs
 * enrol/manual:unenrol in the course. Only the manual enrolment is removed:
 * self, cohort or other enrolments stay. A user with no manual enrolment is
 * not a failure; the step reports removed = false.
 *
 * @package    tool_autoflow
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class unenrol_user_node implements node_type {
    /**
     * Type name.
     *
     * @return string
     */
    public function type(): string {
        return 'moodle.unenrol_user';
    }

    /**
     * Parameters.
     *
     * @return param[]
     */
    public function params(): array {
        return [new param('userid', 'user', true), new param('courseid', 'number', true)];
    }

    /**
     * Branches.
     *
     * @return string[]
     */
    public function branches(): array {
        return [];
    }

    /**
     * Remove the manual enrolment.
     *
     * @param array $params userid, courseid
     * @param array $context
     * @return node_result userid, courseid and removed (false when there was no manual enrolment).
     */
    public function execute(array $params, array $context): node_result {
        global $DB;
        $userid = node_values::id($params['userid'] ?? null, 'user id');
        $courseid = node_values::id($params['courseid'] ?? null, 'course id');
        // Checked first: after the call the manual enrolment is gone either way.
        $had = $DB->record_exists_sql(
            'SELECT 1 FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.courseid = ? AND e.enrol = ? AND ue.userid = ?',
            [$courseid, 'manual', $userid]
        );
        call_function_node::call('enrol_manual_unenrol_users', [
            'enrolments' => [['userid' => $userid, 'courseid' => $courseid]],
        ]);
        return new node_result(['userid' => $userid, 'courseid' => $courseid, 'removed' => $had]);
    }
}
