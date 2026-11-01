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

use tool_autoflow\local\engine\node_failed;
use tool_autoflow\local\engine\node_result;
use tool_autoflow\local\engine\node_type;
use tool_autoflow\local\engine\param;

/**
 * Enrols a user in a course with manual enrolments, as a student unless another role is named.
 *
 * It calls enrol_manual_enrol_users, so the course must have a manual enrolment
 * method and the user the workflow runs as needs enrol/manual:enrol there, plus
 * the right to assign the role.
 *
 * @package    tool_autoflow
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class enrol_user_node implements node_type {
    /**
     * Type name.
     *
     * @return string
     */
    public function type(): string {
        return 'moodle.enrol_user';
    }

    /**
     * Parameters.
     *
     * @return param[]
     */
    public function params(): array {
        return [new param('userid', 'user', true), new param('courseid', 'number', true), new param('role', 'text', false)];
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
     * Enrol the user.
     *
     * @param array $params userid, courseid, role (short name, default student)
     * @param array $context
     * @return node_result userid, courseid and role.
     */
    public function execute(array $params, array $context): node_result {
        global $DB;
        $userid = node_values::id($params['userid'] ?? null, 'user id');
        $courseid = node_values::id($params['courseid'] ?? null, 'course id');
        $role = trim((string) ($params['role'] ?? '')) ?: 'student';
        $roleid = $DB->get_field('role', 'id', ['shortname' => $role]);
        if (!$roleid) {
            throw new node_failed("No role with the short name \"$role\".");
        }
        call_function_node::call('enrol_manual_enrol_users', [
            'enrolments' => [['roleid' => (int) $roleid, 'userid' => $userid, 'courseid' => $courseid]],
        ]);
        return new node_result(['userid' => $userid, 'courseid' => $courseid, 'role' => $role]);
    }
}
