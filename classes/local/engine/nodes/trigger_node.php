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
 * The start of a workflow. Its output is the trigger data, so {{nodes.<trigger id>.x}}
 * and {{trigger.x}} read the same value.
 *
 * @package    tool_autoflow
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class trigger_node implements node_type {
    /**
     * Create a trigger node for one trigger type.
     *
     * @param string $type For example trigger.manual, trigger.schedule or trigger.event.
     */
    public function __construct(
        /** @var string Type name. */
        private readonly string $type,
    ) {
    }

    /**
     * Type name.
     *
     * @return string
     */
    public function type(): string {
        return $this->type;
    }

    /**
     * Parameters.
     *
     * @return param[]
     */
    public function params(): array {
        return match ($this->type) {
            'trigger.schedule' => [new param('cron', 'text', true)],
            'trigger.event' => [new param('eventname', 'event', true)],
            default => [],
        };
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
     * Pass the trigger data on.
     *
     * @param array $params
     * @param array $context
     * @return node_result
     */
    public function execute(array $params, array $context): node_result {
        return new node_result($context['trigger']);
    }
}
