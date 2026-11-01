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

namespace tool_autoflow\local\nodes;

use tool_autoflow\local\engine\node_failed;

/**
 * Checks shared by the Moodle nodes for values that arrive as text from placeholders.
 *
 * @package    tool_autoflow
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class node_values {
    /**
     * A record id: a positive whole number, given as a number or as digits.
     *
     * @param mixed $value
     * @param string $what What the id is for, such as "course id", used in the error.
     * @return int
     */
    public static function id(mixed $value, string $what): int {
        if (is_int($value) && $value > 0) {
            return $value;
        }
        if (is_string($value) && preg_match('/^[1-9][0-9]*$/', trim($value))) {
            return (int) $value;
        }
        $shown = is_scalar($value) ? (string) $value : gettype($value);
        throw new node_failed("The $what must be a positive whole number, not \"$shown\".");
    }
}
