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

/**
 * Bootstrap for running the engine tests without Moodle (phpunit.engine.xml).
 *
 * The engine classes do not use Moodle APIs, so their tests only need Moodle's
 * basic_testcase. This file stands in for it. Moodle's own runner never loads it.
 *
 * @package    tool_autoflow
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable moodle.Files.MoodleInternal.MoodleInternalGlobalState
// phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses

define('MOODLE_INTERNAL', true);

require_once(__DIR__ . '/../../../vendor/autoload.php');

if (!class_exists('basic_testcase')) {
    /**
     * Stand-in for Moodle's basic_testcase.
     */
    abstract class basic_testcase extends \PHPUnit\Framework\TestCase {
    }
}
