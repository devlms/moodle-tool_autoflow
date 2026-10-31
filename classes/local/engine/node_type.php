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
 * One kind of node in a workflow, such as a condition or "send a notification".
 *
 * Nodes get their parameters already resolved from placeholders, and the run
 * context (trigger data, earlier node outputs, variables) as read-only data.
 *
 * @package    tool_autoflow
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface node_type {
    /**
     * The type name stored in the graph, such as logic.condition.
     *
     * @return string
     */
    public function type(): string;

    /**
     * The parameters the node takes, in display order.
     *
     * @return param[]
     */
    public function params(): array;

    /**
     * Named branches the node can choose, such as true and false. Empty for nodes
     * that always continue on their plain edges.
     *
     * @return string[]
     */
    public function branches(): array;

    /**
     * Run the node.
     *
     * @param array $params Node parameters with placeholders resolved.
     * @param array $context The run context.
     * @return node_result
     * @throws node_failed When the node cannot do its job; the run stops.
     */
    public function execute(array $params, array $context): node_result;
}
