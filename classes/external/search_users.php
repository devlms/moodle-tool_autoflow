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

namespace tool_autoflow\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Find users a workflow could run as, for the editor's run-as picker.
 *
 * Needs the same permissions as picking a run-as user (moodle/user:loginas). Site
 * administrators are only offered to site administrators; workflow_store still
 * checks the choice when the workflow is saved.
 *
 * @package    tool_autoflow
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search_users extends external_api {
    /** Most users returned by one search. */
    public const LIMIT = 20;

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'query' => new external_value(PARAM_TEXT, 'Part of a name or an identity field such as the email address'),
        ]);
    }

    /**
     * Search.
     *
     * @param string $query
     * @return array users (id, fullname, identity) and overflow.
     */
    public static function execute(string $query): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/user/lib.php');
        ['query' => $query] = self::validate_parameters(self::execute_parameters(), ['query' => $query]);
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('tool/autoflow:manage', $context);
        require_capability('moodle/user:loginas', $context);

        $fields = \core_user\fields::for_name()->with_identity($context, false);
        $identity = $fields->get_required_fields([\core_user\fields::PURPOSE_IDENTITY]);
        $exclude = is_siteadmin() ? [] : array_map('intval', explode(',', $CFG->siteadmins));
        [$where, $params] = users_search_sql($query, '', USER_SEARCH_CONTAINS, $identity, $exclude);
        [$sort, $sortparams] = users_order_by_sql('', $query, $context);
        $records = $DB->get_records_select(
            'user',
            "$where AND suspended = 0",
            array_merge($params, $sortparams),
            $sort,
            'id' . $fields->get_sql()->selects,
            0,
            self::LIMIT + 1
        );

        $viewfullnames = has_capability('moodle/site:viewfullnames', $context);
        $users = [];
        foreach (array_slice($records, 0, self::LIMIT) as $record) {
            $users[] = [
                'id' => (int) $record->id,
                'fullname' => fullname($record, $viewfullnames),
                'identity' => implode(', ', array_filter(array_map(fn($f) => (string) ($record->$f ?? ''), $identity))),
            ];
        }
        return ['users' => $users, 'overflow' => count($records) > self::LIMIT];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'users' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'User id'),
                'fullname' => new external_value(PARAM_TEXT, 'Full name'),
                'identity' => new external_value(PARAM_TEXT, 'Identity fields the viewer may see, joined with commas'),
            ])),
            'overflow' => new external_value(PARAM_BOOL, 'Whether more users matched than were returned'),
        ]);
    }
}
