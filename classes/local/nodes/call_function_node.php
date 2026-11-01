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

use core_external\external_api;
use tool_autoflow\local\engine\node_failed;
use tool_autoflow\local\engine\node_result;
use tool_autoflow\local\engine\node_type;
use tool_autoflow\local\engine\param;

/**
 * Calls any Moodle web service function: the Moodle API as one node.
 *
 * The call follows external_api::call_external_function() without its sesskey check,
 * which protects browser sessions and has no meaning in a cron task: the arguments are
 * validated against the function's definition, the function runs its own context and
 * capability checks as the user the workflow runs as, and the result is cleaned.
 * Arguments are a JSON object whose values may hold placeholders. Placeholders are
 * filled value by value, never pasted into JSON text, so trigger data cannot add
 * or change keys.
 *
 * @package    tool_autoflow
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class call_function_node implements node_type {
    /**
     * Type name.
     *
     * @return string
     */
    public function type(): string {
        return 'moodle.call_function';
    }

    /**
     * Parameters.
     *
     * @return param[]
     */
    public function params(): array {
        return [new param('function', 'function', true), new param('args', 'json', false)];
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
     * Call the function.
     *
     * @param array $params function, args
     * @param array $context
     * @return node_result
     */
    public function execute(array $params, array $context): node_result {
        $function = (string) ($params['function'] ?? '');
        $args = $params['args'] ?? [];
        if (!is_array($args)) {
            throw new node_failed('The arguments must be a JSON object.');
        }
        return new node_result(['result' => self::call($function, $args)]);
    }

    /**
     * Call a web service function as the current user, the way the web service layer does.
     *
     * Other Moodle nodes use this too, so they get the same validation and capability checks.
     *
     * @param string $function
     * @param array $args
     * @return mixed The cleaned result.
     */
    public static function call(string $function, array $args): mixed {
        try {
            $info = external_api::external_function_info($function);
            $values = array_values(external_api::validate_parameters($info->parameters_desc, $args));
            $result = call_user_func_array([$info->classname, $info->methodname], $values);
            if ($info->returns_desc !== null) {
                $result = external_api::clean_returnvalue($info->returns_desc, $result);
            }
        } catch (\Throwable $e) {
            throw new node_failed("$function: " . $e->getMessage());
        }
        return $result;
    }
}
