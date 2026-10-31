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

namespace tool_autoflow\local;

use tool_autoflow\local\engine\nodes\condition_node;
use tool_autoflow\local\engine\nodes\log_node;
use tool_autoflow\local\engine\nodes\set_variable_node;
use tool_autoflow\local\engine\nodes\trigger_node;
use tool_autoflow\local\engine\registry;
use tool_autoflow\local\nodes\send_notification_node;

/**
 * Every node type this site can use: the engine's own and the Moodle ones.
 *
 * @package    tool_autoflow
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class node_catalogue {
    /**
     * The registry used to run workflows.
     *
     * @return registry
     */
    public static function registry(): registry {
        return new registry(
            new trigger_node('trigger.manual'),
            new trigger_node('trigger.schedule'),
            new trigger_node('trigger.event'),
            new condition_node(),
            new set_variable_node(),
            new log_node(),
            new send_notification_node(),
        );
    }
}
