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
 * Upgrade steps for tool_autoflow.
 *
 * @package    tool_autoflow
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the plugin.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_tool_autoflow_upgrade($oldversion) {
    global $DB;
    if ($oldversion < 2026110800) {
        // An empty run-as user used to mean the site admin. It no longer runs at all, so give older
        // workflows the person who last saved them, which never has more rights than the admin did.
        $DB->execute('UPDATE {tool_autoflow_workflow} SET runasuserid = usermodified
                       WHERE runasuserid IS NULL AND usermodified > 0');
        upgrade_plugin_savepoint(true, 2026110800, 'tool', 'autoflow');
    }
    return true;
}
