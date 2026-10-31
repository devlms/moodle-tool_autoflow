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
 * Admin tree entry for tool_autoflow.
 *
 * @package    tool_autoflow
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig || has_capability('tool/autoflow:manage', context_system::instance())) {
    // Moodle only builds the Admin tools category for site admins. Managers get the page at the top level.
    $parent = $ADMIN->locate('tools') ? 'tools' : 'root';
    $ADMIN->add($parent, new admin_externalpage(
        'tool_autoflow',
        get_string('pluginname', 'tool_autoflow'),
        new moodle_url('/admin/tool/autoflow/index.php'),
        'tool/autoflow:manage'
    ));
}
