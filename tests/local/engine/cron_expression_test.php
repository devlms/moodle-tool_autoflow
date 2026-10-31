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
 * Tests for five-field cron expressions.
 *
 * @package    tool_autoflow
 * @category   test
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_autoflow\local\engine\cron_expression
 */
final class cron_expression_test extends \basic_testcase {
    /**
     * A moment in a timezone.
     *
     * @param string $when
     * @param string $tz
     * @return \DateTimeImmutable
     */
    private static function moment(string $when, string $tz = 'UTC'): \DateTimeImmutable {
        return new \DateTimeImmutable($when, new \DateTimeZone($tz));
    }

    /**
     * Expression, moment, expected match.
     *
     * @return array
     */
    public static function matches_cases(): array {
        // 2026-11-02 is a Monday.
        return [
            'every minute' => ['* * * * *', '2026-11-02 13:37', true],
            'exact minute' => ['30 8 * * *', '2026-11-02 08:30', true],
            'wrong minute' => ['30 8 * * *', '2026-11-02 08:31', false],
            'wrong hour' => ['30 8 * * *', '2026-11-02 09:30', false],
            'list' => ['0,15,30,45 * * * *', '2026-11-02 10:45', true],
            'list miss' => ['0,15,30,45 * * * *', '2026-11-02 10:46', false],
            'range' => ['0 9-17 * * *', '2026-11-02 17:00', true],
            'range start' => ['0 9-17 * * *', '2026-11-02 09:00', true],
            'range miss' => ['0 9-17 * * *', '2026-11-02 18:00', false],
            'range miss before' => ['0 9-17 * * *', '2026-11-02 08:00', false],
            'step' => ['*/15 * * * *', '2026-11-02 10:30', true],
            'step miss' => ['*/15 * * * *', '2026-11-02 10:31', false],
            'step in range' => ['10-40/10 * * * *', '2026-11-02 10:30', true],
            'step in range miss' => ['10-40/10 * * * *', '2026-11-02 10:35', false],
            'step in range beyond' => ['10-40/10 * * * *', '2026-11-02 10:50', false],
            'step from a start' => ['5/20 * * * *', '2026-11-02 10:45', true],
            'step from a start miss' => ['5/20 * * * *', '2026-11-02 10:40', false],
            'day of month' => ['0 0 1 * *', '2026-12-01 00:00', true],
            'day of month miss' => ['0 0 1 * *', '2026-12-02 00:00', false],
            'month' => ['0 0 * 12 *', '2026-12-09 00:00', true],
            'month miss' => ['0 0 * 12 *', '2026-11-09 00:00', false],
            'weekday monday' => ['0 9 * * 1', '2026-11-02 09:00', true],
            'weekday miss' => ['0 9 * * 1', '2026-11-03 09:00', false],
            'sunday as 0' => ['0 9 * * 0', '2026-11-08 09:00', true],
            'sunday as 7' => ['0 9 * * 7', '2026-11-08 09:00', true],
            'sunday as 7 with a month' => ['0 9 * 11 7', '2026-11-08 09:00', true],
            'every other day starts on the 1st' => ['0 0 */2 * *', '2026-11-03 00:00', true],
            'every other day skips even days' => ['0 0 */2 * *', '2026-11-04 00:00', false],
            'stepped range of hours' => ['0 9-17/4 * * *', '2026-11-02 13:00', true],
            'weekdays range' => ['0 9 * * 1-5', '2026-11-06 09:00', true],
            'weekdays range miss' => ['0 9 * * 1-5', '2026-11-07 09:00', false],
            'day or weekday when both set: day matches' => ['0 0 13 * 5', '2026-11-13 00:00', true],
            'day or weekday when both set: weekday matches' => ['0 0 13 * 5', '2026-11-20 00:00', true],
            'day or weekday when both set: neither' => ['0 0 13 * 5', '2026-11-19 00:00', false],
            'only day restricted' => ['0 0 13 * *', '2026-11-20 00:00', false],
            'extra spaces' => ['  0   9 * *  1 ', '2026-11-02 09:00', true],
        ];
    }

    /**
     * Matching single moments.
     *
     * @dataProvider matches_cases
     * @param string $expression
     * @param string $when
     * @param bool $expected
     */
    public function test_matches(string $expression, string $when, bool $expected): void {
        $this->assertSame($expected, cron_expression::parse($expression)->matches(self::moment($when)));
    }

    public function test_matches_in_the_given_timezone(): void {
        // 08:00 in Algiers is 07:00 UTC.
        $cron = cron_expression::parse('0 8 * * *');
        $this->assertTrue($cron->matches(self::moment('2026-11-02 07:00')->setTimezone(new \DateTimeZone('Africa/Algiers'))));
        $this->assertFalse($cron->matches(self::moment('2026-11-02 08:00')->setTimezone(new \DateTimeZone('Africa/Algiers'))));
    }

    public function test_due_between_finds_a_matching_minute_in_the_window(): void {
        $cron = cron_expression::parse('0 8 * * *');
        $tz = new \DateTimeZone('UTC');
        $from = self::moment('2026-11-02 07:50')->getTimestamp();
        $this->assertTrue($cron->due_between($from, self::moment('2026-11-02 08:00')->getTimestamp(), $tz));
        $this->assertTrue($cron->due_between($from, self::moment('2026-11-02 08:05')->getTimestamp(), $tz));
        $this->assertFalse($cron->due_between($from, self::moment('2026-11-02 07:59')->getTimestamp(), $tz));
    }

    public function test_due_between_excludes_the_start_and_includes_the_end(): void {
        $cron = cron_expression::parse('0 8 * * *');
        $tz = new \DateTimeZone('UTC');
        $eight = self::moment('2026-11-02 08:00')->getTimestamp();
        $this->assertFalse($cron->due_between($eight, $eight + 60, $tz), 'already ran at 08:00');
        $this->assertTrue($cron->due_between($eight - 60, $eight, $tz));
        $this->assertTrue($cron->due_between($eight - 60, $eight + 30, $tz), 'seconds past the minute still count');
    }

    public function test_due_between_uses_the_site_timezone(): void {
        $cron = cron_expression::parse('0 8 * * *');
        $sevenutc = self::moment('2026-11-02 07:00')->getTimestamp();
        $this->assertTrue($cron->due_between($sevenutc - 60, $sevenutc, new \DateTimeZone('Africa/Algiers')));
        $this->assertFalse($cron->due_between($sevenutc - 60, $sevenutc, new \DateTimeZone('UTC')));
    }

    public function test_due_between_looks_back_at_most_one_day(): void {
        $cron = cron_expression::parse('0 8 1 1 *');
        $tz = new \DateTimeZone('UTC');
        $now = self::moment('2026-11-02 12:00')->getTimestamp();
        // 1 January is in the window, but more than a day ago: do not fire for it.
        $this->assertFalse($cron->due_between(self::moment('2025-12-01 00:00')->getTimestamp(), $now, $tz));
        $this->assertTrue(cron_expression::parse('0 13 * * *')->due_between($now - 3 * 86400, $now, $tz));
    }

    public function test_empty_window_is_never_due(): void {
        $tz = new \DateTimeZone('UTC');
        $t = self::moment('2026-11-02 08:00')->getTimestamp();
        $this->assertFalse(cron_expression::parse('* * * * *')->due_between($t, $t, $tz));
        $this->assertFalse(cron_expression::parse('* * * * *')->due_between($t, $t - 600, $tz));
    }

    /**
     * Invalid expressions.
     *
     * @return array
     */
    public static function invalid_cases(): array {
        return [
            'too few fields' => ['* * * *'],
            'too many fields' => ['* * * * * *'],
            'empty' => [''],
            'minute too large' => ['60 * * * *'],
            'hour too large' => ['0 24 * * *'],
            'day zero' => ['0 0 0 * *'],
            'month too large' => ['0 0 * 13 *'],
            'weekday too large' => ['0 0 * * 8'],
            'negative' => ['-1 * * * *'],
            'backwards range' => ['0 17-9 * * *'],
            'zero step' => ['*/0 * * * *'],
            'letters' => ['a * * * *'],
            'empty list item' => ['1,,2 * * * *'],
            'half range' => ['1- * * * *'],
        ];
    }

    /**
     * Invalid expressions are refused with a clear message.
     *
     * @dataProvider invalid_cases
     * @param string $expression
     */
    public function test_rejects_invalid(string $expression): void {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cron');
        cron_expression::parse($expression);
    }

    public function test_is_valid(): void {
        $this->assertTrue(cron_expression::is_valid('*/5 * * * *'));
        $this->assertFalse(cron_expression::is_valid('nope'));
    }

    public function test_lookback_is_exactly_one_day(): void {
        $cron = cron_expression::parse('30 8 2 11 *');
        $tz = new \DateTimeZone('UTC');
        $match = self::moment('2026-11-02 08:30')->getTimestamp();
        $longago = $match - 7 * 86400;
        $this->assertTrue($cron->due_between($longago, $match + 1439 * 60, $tz), '1439 minutes later is inside the day');
        $this->assertFalse($cron->due_between($longago, $match + 1440 * 60, $tz), '1440 minutes later is outside it');
    }
}
