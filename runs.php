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
 * Run history of one workflow, with each step's result.
 *
 * @package    tool_autoflow
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$id = required_param('id', PARAM_INT);

require_login();
require_capability('tool/autoflow:viewruns', context_system::instance());
admin_externalpage_setup('tool_autoflow', '', null, new moodle_url('/admin/tool/autoflow/runs.php', ['id' => $id]));
$workflow = \tool_autoflow\local\workflow_store::get($id);
$PAGE->navbar->add(format_string($workflow->name));

$status = optional_param('status', 'all', PARAM_ALPHA);
$runs = $DB->get_records('tool_autoflow_run', ['workflowid' => $id], 'timecreated DESC, id DESC', '*', 0, 100);
$list = new \tool_autoflow\output\run_list($workflow, $runs, $status, time());

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('workflowruns', 'tool_autoflow') . ': ' . format_string($workflow->name));
if (has_capability('tool/autoflow:manage', context_system::instance())) {
    echo html_writer::div(html_writer::link(
        new moodle_url('/admin/tool/autoflow/edit.php', ['id' => $id]),
        get_string('editworkflow', 'tool_autoflow'),
        ['class' => 'btn btn-secondary']
    ), 'mb-3');
}
echo $OUTPUT->render_from_template('tool_autoflow/run_list', $list->export_for_template($OUTPUT));
echo $OUTPUT->footer();
