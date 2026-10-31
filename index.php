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
 * Workflow list.
 *
 * @package    tool_autoflow
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();
require_capability('tool/autoflow:manage', context_system::instance());
admin_externalpage_setup('tool_autoflow');

$workflows = $DB->get_records('tool_autoflow_workflow', null, 'name ASC', 'id, name, enabled, timemodified');

$context = [
    'hasworkflows' => !empty($workflows),
    'workflows' => array_values(array_map(fn($w) => [
        'id' => $w->id,
        'name' => format_string($w->name),
        'enabled' => (bool) $w->enabled,
        'timemodified' => userdate($w->timemodified),
    ], $workflows)),
];

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('workflows', 'tool_autoflow'));
echo $OUTPUT->render_from_template('tool_autoflow/workflow_list', $context);
echo $OUTPUT->footer();
