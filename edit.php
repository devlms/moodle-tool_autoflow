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
 * The workflow editor. The page is an empty mount point; frontend/dist/editor.js builds the editor in it.
 *
 * @package    tool_autoflow
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$id = optional_param('id', 0, PARAM_INT);

require_login();
require_capability('tool/autoflow:manage', context_system::instance());
admin_externalpage_setup('tool_autoflow', '', null, new moodle_url('/admin/tool/autoflow/edit.php', ['id' => $id]));
if ($id) {
    \tool_autoflow\local\workflow_store::get($id);
}

$version = get_config('tool_autoflow', 'version');
$PAGE->requires->css(new moodle_url('/admin/tool/autoflow/frontend/dist/editor.css', ['v' => $version]));
$PAGE->requires->js(new moodle_url('/admin/tool/autoflow/frontend/dist/editor.js', ['v' => $version]));
$PAGE->navbar->add(get_string($id ? 'editworkflow' : 'newworkflow', 'tool_autoflow'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string($id ? 'editworkflow' : 'newworkflow', 'tool_autoflow'));
echo html_writer::div('', 'tool_autoflow-editor-root', [
    'data-tool-autoflow-editor' => '',
    'data-workflow-id' => $id,
    'data-list-url' => (new moodle_url('/admin/tool/autoflow/index.php'))->out(false),
]);
echo $OUTPUT->footer();
