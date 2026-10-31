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

namespace tool_autoflow\local\engine;

/**
 * Fills {{dotted.path}} placeholders from the run context.
 *
 * There is no expression language and no eval: a placeholder is a path into the
 * context and nothing else. Missing paths render as empty text.
 *
 * @package    tool_autoflow
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class template {
    /** One placeholder: word characters separated by dots, optional spaces inside the braces. */
    private const PLACEHOLDER = '\{\{\s*([A-Za-z0-9_]+(?:\.[A-Za-z0-9_]+)*)\s*\}\}';

    /**
     * Replace every placeholder in a string with its value as text.
     *
     * @param string $text
     * @param array $context
     * @return string
     */
    public static function render(string $text, array $context): string {
        return preg_replace_callback(
            '/' . self::PLACEHOLDER . '/',
            fn(array $m) => self::to_text(self::lookup($m[1], $context)),
            $text
        );
    }

    /**
     * Resolve a node parameter. A string that is exactly one placeholder keeps the
     * value's type; other strings are rendered; arrays are resolved item by item.
     *
     * @param mixed $value
     * @param array $context
     * @return mixed
     */
    public static function resolve($value, array $context) {
        if (is_array($value)) {
            return array_map(fn($item) => self::resolve($item, $context), $value);
        }
        if (!is_string($value)) {
            return $value;
        }
        if (preg_match('/^\s*' . self::PLACEHOLDER . '\s*$/', $value, $m)) {
            return self::lookup($m[1], $context);
        }
        return self::render($value, $context);
    }

    /**
     * Read a dotted path from the context. Returns null when any step is missing.
     *
     * @param string $path
     * @param array $context
     * @return mixed
     */
    public static function lookup(string $path, array $context) {
        $value = $context;
        foreach (explode('.', $path) as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }
        return $value;
    }

    /**
     * Convert a value to the text used inside a rendered string.
     *
     * @param mixed $value
     * @return string
     */
    private static function to_text($value): string {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_array($value)) {
            return json_encode($value);
        }
        return (string) $value;
    }
}
