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

namespace tool_autoflow\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for tool_autoflow.
 *
 * Workflows and runs live in the system context. Runs reference the user the
 * triggering event was about; workflows reference their editor and run-as user.
 *
 * @package    tool_autoflow
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the data this plugin stores.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('tool_autoflow_run', [
            'userid' => 'privacy:metadata:run:userid',
            'log' => 'privacy:metadata:run:log',
            'error' => 'privacy:metadata:run:error',
            'timecreated' => 'privacy:metadata:run:timecreated',
        ], 'privacy:metadata:run');
        $collection->add_database_table('tool_autoflow_workflow', [
            'usermodified' => 'privacy:metadata:workflow:usermodified',
            'runasuserid' => 'privacy:metadata:workflow:runasuserid',
            'timemodified' => 'privacy:metadata:workflow:timemodified',
        ], 'privacy:metadata:workflow');
        $collection->add_external_location_link('httprequest', [
            'data' => 'privacy:metadata:httprequest:data',
        ], 'privacy:metadata:httprequest');
        return $collection;
    }

    /**
     * Contexts holding data for a user: the system context if any row mentions them.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        if (self::user_has_data($userid)) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    /**
     * Users with data in a context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        if ($userlist->get_context()->contextlevel != CONTEXT_SYSTEM) {
            return;
        }
        $userlist->add_from_sql('userid', 'SELECT userid FROM {tool_autoflow_run} WHERE userid IS NOT NULL', []);
        $userlist->add_from_sql('usermodified', 'SELECT usermodified FROM {tool_autoflow_workflow}', []);
        $userlist->add_from_sql(
            'runasuserid',
            'SELECT runasuserid FROM {tool_autoflow_workflow} WHERE runasuserid IS NOT NULL',
            []
        );
    }

    /**
     * Export a user's runs and the workflows they edit or run as.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_SYSTEM) {
                continue;
            }
            $runs = $DB->get_records(
                'tool_autoflow_run',
                ['userid' => $userid],
                'id ASC',
                'id, workflowid, status, triggertype, log, error, timecreated'
            );
            $workflows = $DB->get_records_select(
                'tool_autoflow_workflow',
                'usermodified = :u1 OR runasuserid = :u2',
                ['u1' => $userid, 'u2' => $userid],
                'id ASC',
                'id, name, timemodified'
            );
            $data = (object) [
                'runs' => array_values(array_map(fn($r) => [
                    'workflowid' => $r->workflowid,
                    'status' => $r->status,
                    'trigger' => $r->triggertype,
                    'log' => json_decode((string) $r->log, true),
                    'error' => (string) $r->error,
                    'timecreated' => transform::datetime($r->timecreated),
                ], $runs)),
                'workflows' => array_values(array_map(fn($w) => [
                    'name' => $w->name,
                    'timemodified' => transform::datetime($w->timemodified),
                ], $workflows)),
            ];
            writer::with_context($context)->export_data([get_string('pluginname', 'tool_autoflow')], $data);
        }
    }

    /**
     * Delete everything about all users in a context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context->contextlevel != CONTEXT_SYSTEM) {
            return;
        }
        $DB->delete_records('tool_autoflow_run');
        $DB->set_field('tool_autoflow_workflow', 'usermodified', 0);
        // A workflow without a run-as user cannot run, so it is turned off rather than left to fail.
        $DB->execute('UPDATE {tool_autoflow_workflow} SET runasuserid = NULL, enabled = 0 WHERE runasuserid IS NOT NULL');
    }

    /**
     * Delete one user's data.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel == CONTEXT_SYSTEM) {
                self::anonymise_users([$contextlist->get_user()->id]);
            }
        }
    }

    /**
     * Delete data for several users in a context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        if ($userlist->get_context()->contextlevel == CONTEXT_SYSTEM) {
            self::anonymise_users($userlist->get_userids());
        }
    }

    /**
     * Whether any row mentions the user.
     *
     * @param int $userid
     * @return bool
     */
    private static function user_has_data(int $userid): bool {
        global $DB;
        return $DB->record_exists('tool_autoflow_run', ['userid' => $userid])
            || $DB->record_exists_select(
                'tool_autoflow_workflow',
                'usermodified = :u1 OR runasuserid = :u2',
                ['u1' => $userid, 'u2' => $userid]
            );
    }

    /**
     * Remove user data. Runs about the user are deleted, because their logs can hold the
     * user's details. Workflows stay, as they belong to the site; only the user references go.
     *
     * @param int[] $userids
     */
    private static function anonymise_users(array $userids) {
        global $DB;
        if (!$userids) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('tool_autoflow_run', "userid $insql", $params);
        $DB->set_field_select('tool_autoflow_workflow', 'usermodified', 0, "usermodified $insql", $params);
        $DB->execute("UPDATE {tool_autoflow_workflow} SET runasuserid = NULL, enabled = 0 WHERE runasuserid $insql", $params);
    }
}
