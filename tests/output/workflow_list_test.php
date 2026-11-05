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

namespace tool_autoflow\output;

/**
 * Tests for the data behind the workflow list.
 *
 * @package    tool_autoflow
 * @category   test
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_autoflow\output\workflow_list
 */
final class workflow_list_test extends \advanced_testcase {
    /** @var int A fixed "now" so relative times are stable. */
    private const NOW = 1800000000;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Export the list.
     *
     * @return array
     */
    private function export(): array {
        global $PAGE;
        $list = new workflow_list(self::NOW);
        return json_decode(json_encode($list->export_for_template($PAGE->get_renderer('core'))), true);
    }

    /**
     * Store a finished run.
     *
     * @param int $workflowid
     * @param string $status
     * @param int $ago Seconds before NOW.
     */
    private function add_run(int $workflowid, string $status, int $ago): void {
        global $DB;
        $DB->insert_record('tool_autoflow_run', ['workflowid' => $workflowid, 'status' => $status, 'triggertype' => 'manual',
            'log' => '[]', 'timecreated' => self::NOW - $ago, 'timefinished' => self::NOW - $ago]);
    }

    public function test_empty(): void {
        $data = $this->export();
        $this->assertFalse($data['hasworkflows']);
        $this->assertStringContainsString('/admin/tool/autoflow/edit.php', $data['newurl']);
    }

    public function test_rows_show_status_trigger_and_the_last_run(): void {
        $gen = $this->getDataGenerator()->get_plugin_generator('tool_autoflow');
        $on = $gen->create_workflow(['name' => 'Bravo', 'enabled' => true]);
        $off = $gen->create_workflow(['name' => 'Alpha', 'enabled' => false]);
        $this->add_run($on, 'success', 2 * DAYSECS);
        $this->add_run($on, 'failed', 3 * HOURSECS);

        $rows = array_column($this->export()['workflows'], null, 'name');
        $this->assertSame(['Alpha', 'Bravo'], array_keys($rows));

        $bravo = $rows['Bravo'];
        $this->assertTrue($bravo['enabled']);
        $this->assertSame('Enabled', $bravo['statuslabel']);
        $this->assertStringContainsString('bg-success', $bravo['statusbadge']);
        $this->assertSame('Started by hand', $bravo['trigger']);
        $this->assertSame('Admin User', $bravo['runas']);
        $this->assertTrue($bravo['hasrun']);
        $this->assertSame('3 hours ago', $bravo['lastrun']);
        $this->assertSame('Failed', $bravo['laststatus']);
        $this->assertStringContainsString('bg-danger', $bravo['lastbadge']);
        $this->assertStringContainsString('runs.php?id=' . $on, $bravo['runsurl']);
        $this->assertStringContainsString('sesskey=', $bravo['runurl']);

        $alpha = $rows['Alpha'];
        $this->assertSame('Disabled', $alpha['statuslabel']);
        $this->assertStringContainsString('bg-secondary', $alpha['statusbadge']);
        $this->assertFalse($alpha['hasrun']);
        $this->assertSame('Never', $alpha['lastrun']);
        $this->assertSame($off, $alpha['id']);
    }
}
