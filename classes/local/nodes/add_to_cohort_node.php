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
 * Adds a user to a site or category cohort.
 *
 * It calls core_cohort_add_cohort_members, so the user the workflow runs as needs
 * moodle/cohort:assign in the cohort's context. That function reports a missing
 * cohort or user as a warning, not an error, so warnings fail the step here,
 * except "already a member", which is reported as added = false.
 *
 * @package    tool_autoflow
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class add_to_cohort_node implements node_type {
    /** Warning code core_cohort_add_cohort_members uses for an existing member. */
    private const ALREADY_MEMBER = '3';

    /**
     * Type name.
     *
     * @return string
     */
    public function type(): string {
        return 'moodle.add_to_cohort';
    }

    /**
     * Parameters.
     *
     * @return param[]
     */
    public function params(): array {
        return [new param('userid', 'user', true), new param('cohortid', 'number', true)];
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
     * Add the user to the cohort.
     *
     * @param array $params userid, cohortid
     * @param array $context
     * @return node_result userid, cohortid and added (false when the user was already a member).
     */
    public function execute(array $params, array $context): node_result {
        $userid = node_values::id($params['userid'] ?? null, 'user id');
        $cohortid = node_values::id($params['cohortid'] ?? null, 'cohort id');
        $result = call_function_node::call('core_cohort_add_cohort_members', ['members' => [[
            'cohorttype' => ['type' => 'id', 'value' => (string) $cohortid],
            'usertype' => ['type' => 'id', 'value' => (string) $userid],
        ]]]);
        $added = true;
        foreach ($result['warnings'] ?? [] as $warning) {
            if ((string) $warning['warningcode'] === self::ALREADY_MEMBER) {
                $added = false;
                continue;
            }
            throw new node_failed('core_cohort_add_cohort_members: ' . $warning['message']);
        }
        return new node_result(['userid' => $userid, 'cohortid' => $cohortid, 'added' => $added]);
    }
}
