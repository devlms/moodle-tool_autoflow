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

/**
 * Workflow list, with run and delete actions.
 *
 * @package    tool_autoflow
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use tool_autoflow\local\runner;
use tool_autoflow\local\workflow_store;

$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);

require_login();
$context = context_system::instance();
require_capability('tool/autoflow:manage', $context);
admin_externalpage_setup('tool_autoflow');
$url = new moodle_url('/admin/tool/autoflow/index.php');

if ($action === 'run' && confirm_sesskey()) {
    workflow_store::get($id);
    runner::queue($id, ['userid' => (int) $USER->id], 'manual');
    redirect($url, get_string('runqueued', 'tool_autoflow'), null, \core\output\notification::NOTIFY_SUCCESS);
}
if ($action === 'delete') {
    $workflow = workflow_store::get($id);
    if (optional_param('confirm', 0, PARAM_BOOL) && confirm_sesskey()) {
        workflow_store::delete($id);
        redirect($url);
    }
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        get_string('deleteworkflowconfirm', 'tool_autoflow', format_string($workflow->name)),
        new moodle_url($url, ['action' => 'delete', 'id' => $id, 'confirm' => 1, 'sesskey' => sesskey()]),
        $url
    );
    echo $OUTPUT->footer();
    die();
}

$fields = 'id, name, enabled, triggertype, runasuserid, timemodified';
$workflows = $DB->get_records('tool_autoflow_workflow', null, 'name ASC', $fields);
// Not user_get_users_by_id(): it is deprecated in 5.3 and its replacement does not exist in 4.5.
$runasusers = $DB->get_records_list('user', 'id', array_filter(array_column($workflows, 'runasuserid')));
$lastruns = $DB->get_records_sql('SELECT workflowid, MAX(timecreated) AS lastrun FROM {tool_autoflow_run} GROUP BY workflowid');
$strings = get_string_manager();
$templatecontext = [
    'newurl' => (new moodle_url('/admin/tool/autoflow/edit.php'))->out(false),
    'hasworkflows' => !empty($workflows),
    'workflows' => array_values(array_map(fn($w) => [
        'id' => $w->id,
        'name' => format_string($w->name),
        'enabled' => (bool) $w->enabled,
        'trigger' => $strings->string_exists('node:' . $w->triggertype, 'tool_autoflow')
            ? get_string('node:' . $w->triggertype, 'tool_autoflow') : '',
        'runas' => isset($runasusers[$w->runasuserid]) ? fullname($runasusers[$w->runasuserid]) : '',
        'lastrun' => isset($lastruns[$w->id]) ? userdate($lastruns[$w->id]->lastrun) : get_string('never', 'tool_autoflow'),
        'editurl' => (new moodle_url('/admin/tool/autoflow/edit.php', ['id' => $w->id]))->out(false),
        'runsurl' => (new moodle_url('/admin/tool/autoflow/runs.php', ['id' => $w->id]))->out(false),
        'runurl' => (new moodle_url($url, ['action' => 'run', 'id' => $w->id, 'sesskey' => sesskey()]))->out(false),
        'deleteurl' => (new moodle_url($url, ['action' => 'delete', 'id' => $w->id]))->out(false),
    ], $workflows)),
];

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('workflows', 'tool_autoflow'));
echo $OUTPUT->render_from_template('tool_autoflow/workflow_list', $templatecontext);
echo $OUTPUT->footer();
