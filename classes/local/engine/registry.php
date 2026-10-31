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
 * The node types a run can use, by type name.
 *
 * @package    tool_autoflow
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class registry {
    /** @var node_type[] Node types by type name. */
    private array $nodes = [];

    /**
     * Create a registry.
     *
     * @param node_type ...$nodes
     */
    public function __construct(node_type ...$nodes) {
        foreach ($nodes as $node) {
            $this->nodes[$node->type()] = $node;
        }
    }

    /**
     * The node type for a name.
     *
     * @param string $type
     * @return node_type
     * @throws node_failed When no node has that type, for example after a plugin was removed.
     */
    public function get(string $type): node_type {
        if (!isset($this->nodes[$type])) {
            throw new node_failed("No node of type \"$type\" is installed.");
        }
        return $this->nodes[$type];
    }

    /**
     * Whether a type is registered.
     *
     * @param string $type
     * @return bool
     */
    public function has(string $type): bool {
        return isset($this->nodes[$type]);
    }

    /**
     * All registered type names, in registration order.
     *
     * @return string[]
     */
    public function types(): array {
        return array_keys($this->nodes);
    }
}
