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

namespace tool_autoflow\local;

/**
 * Small display helpers shared by the plugin's pages.
 *
 * @package    tool_autoflow
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class display {
    /** @var string[] Badge classes per run status. Text colours pass WCAG AA on 4.5 and 5.x. */
    private const RUNBADGES = [
        'success' => 'bg-success text-white',
        'failed' => 'bg-danger text-white',
        'running' => 'bg-info text-dark',
    ];

    /**
     * A short relative time: "Just now", "5 minutes ago", "3 hours ago", "Yesterday", "5 days ago", then the date.
     *
     * @param int $time
     * @param int $now
     * @return string
     */
    public static function ago(int $time, int $now): string {
        $diff = max(0, $now - $time);
        if ($diff < MINSECS) {
            return get_string('ago:now', 'tool_autoflow');
        }
        if ($diff < HOURSECS) {
            return get_string('ago:minutes', 'tool_autoflow', intdiv($diff, MINSECS));
        }
        if ($diff < DAYSECS) {
            return get_string('ago:hours', 'tool_autoflow', intdiv($diff, HOURSECS));
        }
        if ($diff < 2 * DAYSECS) {
            return get_string('ago:yesterday', 'tool_autoflow');
        }
        if ($diff < 14 * DAYSECS) {
            return get_string('ago:days', 'tool_autoflow', intdiv($diff, DAYSECS));
        }
        return userdate($time, get_string('strftimedate', 'langconfig'));
    }

    /**
     * Badge classes for a run status.
     *
     * @param string $status success, failed or running.
     * @return string
     */
    public static function run_badge(string $status): string {
        return 'badge ' . (self::RUNBADGES[$status] ?? 'bg-secondary text-dark');
    }

    /**
     * The label of a run status.
     *
     * @param string $status
     * @return string
     */
    public static function run_status(string $status): string {
        return self::string_or('status:' . $status, $status);
    }

    /**
     * A plugin string if it exists, otherwise the fallback.
     *
     * @param string $key
     * @param string $fallback
     * @return string
     */
    public static function string_or(string $key, string $fallback): string {
        return get_string_manager()->string_exists($key, 'tool_autoflow') ? get_string($key, 'tool_autoflow') : $fallback;
    }
}
