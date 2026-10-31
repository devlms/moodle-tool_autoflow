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

use tool_autoflow\local\engine\node_failed;
use tool_autoflow\local\engine\node_result;
use tool_autoflow\local\engine\node_type;
use tool_autoflow\local\engine\param;

/**
 * Stores a value under a name, readable later as {{vars.<name>}}.
 *
 * @package    tool_autoflow
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class set_variable_node implements node_type {
    /**
     * Type name.
     *
     * @return string
     */
    public function type(): string {
        return 'logic.set';
    }

    /**
     * Parameters.
     *
     * @return param[]
     */
    public function params(): array {
        return [new param('name', 'text', true), new param('value', 'text', false)];
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
     * Set the variable.
     *
     * @param array $params name, value
     * @param array $context
     * @return node_result
     */
    public function execute(array $params, array $context): node_result {
        $name = $params['name'] ?? '';
        if (!is_string($name) || $name === '') {
            throw new node_failed('Set variable needs a name.');
        }
        $value = $params['value'] ?? null;
        return new node_result(['value' => $value], null, [$name => $value]);
    }
}
