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
 * The outcome of one run: status, a log entry per step, the final context and any error.
 *
 * @package    tool_autoflow
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class run_result {
    /**
     * Create a result.
     *
     * @param string $status success or failed
     * @param array[] $steps Each with node, type, status, branch and, on error, error.
     * @param array $context trigger, nodes and vars at the end of the run.
     * @param string|null $error Why the run failed.
     */
    public function __construct(
        /** @var string success or failed. */
        public readonly string $status,
        /** @var array[] One entry per step. */
        public readonly array $steps,
        /** @var array Final context. */
        public readonly array $context,
        /** @var string|null Why the run failed. */
        public readonly ?string $error = null,
    ) {
    }
}
