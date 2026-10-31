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

declare(strict_types=1);

namespace tool_autoflow\local\engine\nodes;

use tool_autoflow\local\engine\node_result;
use tool_autoflow\local\engine\node_type;
use tool_autoflow\local\engine\param;

/**
 * Writes a message to the run log. Useful for checking what a workflow sees.
 *
 * @package    tool_autoflow
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class log_node implements node_type {
    /**
     * Type name.
     *
     * @return string
     */
    public function type(): string {
        return 'action.log';
    }

    /**
     * Parameters.
     *
     * @return param[]
     */
    public function params(): array {
        return [new param('message', 'textarea', true)];
    }

    /**
     * Branches.
     *
     * @return string[]
     */
    public function branches(): array {
        return [];
    }

    /**
     * Record the message.
     *
     * @param array $params message
     * @param array $context
     * @return node_result
     */
    public function execute(array $params, array $context): node_result {
        return new node_result(['message' => (string) ($params['message'] ?? '')]);
    }
}
