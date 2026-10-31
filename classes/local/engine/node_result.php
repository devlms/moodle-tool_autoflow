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
 * What a node returns: its output, the branch it chose, and variables it sets.
 *
 * @package    tool_autoflow
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class node_result {
    /**
     * Create a result.
     *
     * @param array $output Readable by later nodes as {{nodes.<id>.<key>}}.
     * @param string|null $branch Handle to follow, or null to follow only edges without a handle.
     * @param array $vars Variables to set, readable as {{vars.<name>}}.
     */
    public function __construct(
        /** @var array Output for later nodes. */
        public readonly array $output = [],
        /** @var string|null Branch to follow. */
        public readonly ?string $branch = null,
        /** @var array Variables to set. */
        public readonly array $vars = [],
    ) {
    }
}
