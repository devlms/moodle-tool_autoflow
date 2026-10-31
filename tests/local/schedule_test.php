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

use tool_autoflow\task\check_schedules;
use tool_autoflow\task\run_workflow;

/**
 * Tests for the task that starts scheduled workflows.
 *
 * @package    tool_autoflow
 * @category   test
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_autoflow\task\check_schedules
 */
final class schedule_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->setTimezone('Africa/Algiers');
    }

    /**
     * Save an enabled schedule workflow.
     *
     * @param string $cron
     * @return int
     */
    private static function scheduled(string $cron): int {
        return workflow_store::save(
            0,
            'Every so often',
            workflow_store_test::graph('trigger.schedule', ['cron' => $cron]),
            true
        )['id'];
    }

    /**
     * Unix time for a wall clock time in Algiers.
     *
     * @param string $when
     * @return int
     */
    private static function algiers(string $when): int {
        return (new \DateTimeImmutable($when, new \DateTimeZone('Africa/Algiers')))->getTimestamp();
    }

    /**
     * Queued run tasks.
     *
     * @return array[] Custom data of each queued run.
     */
    private static function queued(): array {
        return array_values(array_map(
            fn($t) => json_decode($t->get_custom_data_as_string(), true),
            \core\task\manager::get_adhoc_tasks(run_workflow::class)
        ));
    }

    public function test_due_workflow_is_queued_once(): void {
        global $DB;
        $id = self::scheduled('0 8 * * *');
        $eight = self::algiers('2026-11-02 08:00:20');
        (new check_schedules())->check($eight);

        $this->assertSame(
            [['workflowid' => $id, 'trigger' => ['time' => $eight], 'triggertype' => 'schedule', 'depth' => 0]],
            self::queued()
        );
        $this->assertEquals($eight, $DB->get_field('tool_autoflow_workflow', 'lastscheduled', ['id' => $id]));

        (new check_schedules())->check($eight + 30);
        $this->assertCount(1, self::queued(), 'the same minute does not queue twice');
    }

    public function test_schedule_reads_the_site_timezone(): void {
        self::scheduled('0 8 * * *');
        // 08:00 UTC is 09:00 in Algiers.
        (new check_schedules())->check((new \DateTimeImmutable('2026-11-02 08:00', new \DateTimeZone('UTC')))->getTimestamp());
        $this->assertSame([], self::queued());
    }

    public function test_not_due_is_not_queued_but_time_moves_on(): void {
        global $DB;
        $id = self::scheduled('0 8 * * *');
        $now = self::algiers('2026-11-02 10:00');
        (new check_schedules())->check($now);
        $this->assertSame([], self::queued());
        $this->assertEquals($now, $DB->get_field('tool_autoflow_workflow', 'lastscheduled', ['id' => $id]));
    }

    public function test_missed_minutes_since_the_last_check_count_once(): void {
        global $DB;
        $id = self::scheduled('*/5 * * * *');
        $DB->set_field('tool_autoflow_workflow', 'lastscheduled', self::algiers('2026-11-02 07:58'), ['id' => $id]);
        (new check_schedules())->check(self::algiers('2026-11-02 08:12'));
        $this->assertCount(1, self::queued());
    }

    public function test_new_workflow_only_looks_at_the_last_minute(): void {
        self::scheduled('0 8 * * *');
        (new check_schedules())->check(self::algiers('2026-11-02 08:01:30'));
        $this->assertSame([], self::queued());
    }

    public function test_disabled_workflows_are_ignored(): void {
        workflow_store::save(0, 'Off', workflow_store_test::graph('trigger.schedule', ['cron' => '* * * * *']), false);
        (new check_schedules())->check(time());
        $this->assertSame([], self::queued());
    }

    public function test_task_name_and_execute(): void {
        self::scheduled('* * * * *');
        $task = new check_schedules();
        $this->assertSame('Start scheduled workflows', $task->get_name());
        $task->execute();
        $this->assertCount(1, self::queued());
    }
}
