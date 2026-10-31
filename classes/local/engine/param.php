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
 * One parameter a node takes, as shown in the editor's form for that node.
 *
 * Labels are not here: the Moodle layer reads them from language strings, so the
 * engine stays free of Moodle APIs.
 *
 * @package    tool_autoflow
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class param {
    /**
     * Describe a parameter.
     *
     * @param string $name Key in the node's params.
     * @param string $kind Input to show: text, textarea, number, select, event or user.
     * @param bool $required Whether the node needs it.
     * @param string[] $options Choices for a select.
     */
    public function __construct(
        /** @var string Key in the node's params. */
        public readonly string $name,
        /** @var string Input kind. */
        public readonly string $kind,
        /** @var bool Required. */
        public readonly bool $required,
        /** @var string[] Choices for a select. */
        public readonly array $options = [],
    ) {
    }

    /**
     * As a plain array for the editor.
     *
     * @return array
     */
    public function to_array(): array {
        return ['name' => $this->name, 'kind' => $this->kind, 'required' => $this->required, 'options' => $this->options];
    }
}
