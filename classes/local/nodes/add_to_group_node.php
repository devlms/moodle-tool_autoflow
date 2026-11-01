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
 * Adds a user who is enrolled in the course to one of its groups.
 *
 * It calls core_group_add_group_members, so the user the workflow runs as needs
 * moodle/course:managegroups in the group's course.
 *
 * @package    tool_autoflow
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class add_to_group_node implements node_type {
    /**
     * Type name.
     *
     * @return string
     */
    public function type(): string {
        return 'moodle.add_to_group';
    }

    /**
     * Parameters.
     *
     * @return param[]
     */
    public function params(): array {
        return [new param('userid', 'user', true), new param('groupid', 'number', true)];
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
     * Add the member.
     *
     * @param array $params userid, groupid
     * @param array $context
     * @return node_result userid and groupid.
     */
    public function execute(array $params, array $context): node_result {
        $userid = node_values::id($params['userid'] ?? null, 'user id');
        $groupid = node_values::id($params['groupid'] ?? null, 'group id');
        call_function_node::call('core_group_add_group_members', ['members' => [['groupid' => $groupid, 'userid' => $userid]]]);
        return new node_result(['userid' => $userid, 'groupid' => $groupid]);
    }
}
