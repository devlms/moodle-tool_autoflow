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
 * Compares two values and follows the "true" or "false" branch.
 *
 * Two numbers compare as numbers, so "10" is greater than "9". Anything else
 * compares as text, case sensitive. true and false read as the words, null as "".
 *
 * @package    tool_autoflow
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class condition_node implements node_type {
    /**
     * Type name.
     *
     * @return string
     */
    public function type(): string {
        return 'logic.condition';
    }

    /**
     * Parameters.
     *
     * @return param[]
     */
    public function params(): array {
        return [
            new param('left', 'text', true),
            new param('operator', 'select', true, ['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'contains', 'empty', 'notempty']),
            new param('right', 'text', false),
        ];
    }

    /**
     * Branches.
     *
     * @return string[]
     */
    public function branches(): array {
        return ['true', 'false'];
    }

    /**
     * Compare left and right with the operator.
     *
     * @param array $params left, operator, right
     * @param array $context
     * @return node_result
     */
    public function execute(array $params, array $context): node_result {
        $left = $params['left'] ?? null;
        $right = $params['right'] ?? null;
        $operator = $params['operator'] ?? '';
        $result = match ($operator) {
            'eq' => self::compare($left, $right) === 0,
            'neq' => self::compare($left, $right) !== 0,
            'gt' => self::compare($left, $right) > 0,
            'gte' => self::compare($left, $right) >= 0,
            'lt' => self::compare($left, $right) < 0,
            'lte' => self::compare($left, $right) <= 0,
            'contains' => self::contains($left, $right),
            'empty' => self::is_empty($left),
            'notempty' => !self::is_empty($left),
            default => throw new node_failed("Unknown condition operator \"$operator\"."),
        };
        return new node_result(['result' => $result], $result ? 'true' : 'false');
    }

    /**
     * Compare as numbers when both are numeric, otherwise as text.
     *
     * @param mixed $a
     * @param mixed $b
     * @return int Negative, zero or positive.
     */
    private static function compare($a, $b): int {
        // On two strings PHP 8 compares as numbers when both are numeric, and as text otherwise.
        return self::scalar($a) <=> self::scalar($b);
    }

    /**
     * Whether text contains a piece, or a list contains a value.
     *
     * @param mixed $haystack
     * @param mixed $needle
     * @return bool
     */
    private static function contains($haystack, $needle): bool {
        if (is_array($haystack)) {
            foreach ($haystack as $item) {
                if (self::compare($item, $needle) === 0) {
                    return true;
                }
            }
            return false;
        }
        return str_contains(self::scalar($haystack), self::scalar($needle));
    }

    /**
     * Empty means null, empty text or an empty list. Zero is a value, not empty.
     *
     * @param mixed $value
     * @return bool
     */
    private static function is_empty($value): bool {
        return $value === null || $value === '' || $value === [];
    }

    /**
     * A value as text for comparing.
     *
     * @param mixed $value
     * @return string
     */
    private static function scalar($value): string {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_array($value)) {
            return json_encode($value);
        }
        return (string) $value;
    }
}
