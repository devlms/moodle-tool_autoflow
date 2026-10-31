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
 * A standard five-field cron expression: minute, hour, day of month, month, day of week.
 *
 * Supports *, lists (1,15), ranges (9-17), and steps (* /15, 10-40/10, 5/20).
 * Day of week runs 0-7, where 0 and 7 are both Sunday. As in Vixie cron, when
 * both day fields are restricted a moment matches if either one does.
 *
 * @package    tool_autoflow
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class cron_expression {
    /** @var int[][] Lowest and highest value per field. */
    private const LIMITS = [[0, 59], [0, 23], [1, 31], [1, 12], [0, 7]];

    /** @var int The longest window due_between() looks back over, in minutes (one day). */
    private const MAX_LOOKBACK = 1440;

    /**
     * Use parse().
     *
     * @param array[] $allowed Per field, allowed values as keys.
     * @param bool $dayrestricted Day of month is not *.
     * @param bool $weekdayrestricted Day of week is not *.
     */
    private function __construct(
        /** @var array[] Allowed values per field. */
        private readonly array $allowed,
        /** @var bool Day of month is restricted. */
        private readonly bool $dayrestricted,
        /** @var bool Day of week is restricted. */
        private readonly bool $weekdayrestricted,
    ) {
    }

    /**
     * Parse an expression.
     *
     * @param string $expression
     * @return self
     * @throws \InvalidArgumentException
     */
    public static function parse(string $expression): self {
        $fields = preg_split('/\s+/', trim($expression));
        if (count($fields) !== 5) {
            throw new \InvalidArgumentException("A cron expression needs 5 fields: \"$expression\".");
        }
        $allowed = [];
        foreach ($fields as $i => $field) {
            $allowed[$i] = self::parse_field($field, ...self::LIMITS[$i]);
        }
        if (isset($allowed[4][7])) {
            $allowed[4][0] = true;
        }
        return new self($allowed, $fields[2] !== '*', $fields[4] !== '*');
    }

    /**
     * Whether an expression parses.
     *
     * @param string $expression
     * @return bool
     */
    public static function is_valid(string $expression): bool {
        try {
            self::parse($expression);
            return true;
        } catch (\InvalidArgumentException $e) {
            return false;
        }
    }

    /**
     * Whether the expression matches a moment, read in the moment's own timezone.
     *
     * @param \DateTimeInterface $moment
     * @return bool
     */
    public function matches(\DateTimeInterface $moment): bool {
        [$minute, $hour, $day, $month, $weekday] = array_map('intval', explode(' ', $moment->format('i G j n w')));
        if (!isset($this->allowed[0][$minute], $this->allowed[1][$hour], $this->allowed[3][$month])) {
            return false;
        }
        $daymatch = isset($this->allowed[2][$day]);
        $weekdaymatch = isset($this->allowed[4][$weekday]);
        if ($this->dayrestricted && $this->weekdayrestricted) {
            return $daymatch || $weekdaymatch;
        }
        return $daymatch && $weekdaymatch;
    }

    /**
     * Whether any whole minute in (from, to] matches, in the given timezone.
     *
     * Used by the schedule task: from is the last time the workflow was queued,
     * to is now. Several missed minutes still mean one run. Only the last day is
     * checked, so a site whose cron was off for a week does not catch up old runs.
     *
     * @param int $from Unix time, not included.
     * @param int $to Unix time, included.
     * @param \DateTimeZone $timezone The site timezone.
     * @return bool
     */
    public function due_between(int $from, int $to, \DateTimeZone $timezone): bool {
        $last = intdiv($to, 60);
        $first = max(intdiv($from, 60) + 1, $last - self::MAX_LOOKBACK + 1);
        for ($minute = $last; $minute >= $first; $minute--) {
            if ($this->matches((new \DateTimeImmutable('@' . ($minute * 60)))->setTimezone($timezone))) {
                return true;
            }
        }
        return false;
    }

    /**
     * Parse one field into the set of values it allows.
     *
     * @param string $field
     * @param int $min
     * @param int $max
     * @return array Allowed values as keys.
     */
    private static function parse_field(string $field, int $min, int $max): array {
        $allowed = [];
        foreach (explode(',', $field) as $part) {
            $pattern = '/^(?:(?<all>\*)|(?<start>\d+)(?:-(?<end>\d+))?)(?:\/(?<step>\d+))?$/';
            if (!preg_match($pattern, $part, $m, PREG_UNMATCHED_AS_NULL)) {
                throw new \InvalidArgumentException("Cannot read \"$part\" in the cron expression.");
            }
            $step = $m['step'] === null ? 1 : (int) $m['step'];
            if ($m['all'] !== null) {
                [$start, $end] = [$min, $max];
            } else {
                $start = (int) $m['start'];
                // From a start such as 5/20: up to the end of the range, every 20.
                $end = $m['end'] !== null ? (int) $m['end'] : ($m['step'] !== null ? $max : $start);
            }
            if ($step < 1 || $start < $min || $end > $max || $start > $end) {
                throw new \InvalidArgumentException("\"$part\" is out of range in the cron expression.");
            }
            for ($v = $start; $v <= $end; $v += $step) {
                $allowed[$v] = true;
            }
        }
        return $allowed;
    }
}
