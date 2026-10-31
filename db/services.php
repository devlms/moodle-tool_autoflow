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
 * Web service functions, all used by the workflow editor.
 *
 * @package    tool_autoflow
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'tool_autoflow_get_editor' => [
        'classname' => '\tool_autoflow\external\get_editor',
        'description' => 'Load a workflow with the node palette for the editor.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'tool/autoflow:manage',
    ],
    'tool_autoflow_save_workflow' => [
        'classname' => '\tool_autoflow\external\save_workflow',
        'description' => 'Save a workflow from the editor.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'tool/autoflow:manage',
    ],
    'tool_autoflow_run_workflow' => [
        'classname' => '\tool_autoflow\external\run_workflow',
        'description' => 'Queue a manual run of a workflow.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'tool/autoflow:manage',
    ],
];
