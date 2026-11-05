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
 * Tests for the data behind the run history page.
 *
 * @package    tool_autoflow
 * @category   test
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_autoflow\output\run_list
 */
final class run_list_test extends \advanced_testcase {
    /** @var int A fixed "now" so relative times are stable. */
    private const NOW = 1800000000;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * A run record as runner::run() stores it.
     *
     * @param int $id
     * @param string $status
     * @param array $steps
     * @param array $extra
     * @return \stdClass
     */
    private function make_run(int $id, string $status, array $steps, array $extra = []): \stdClass {
        return (object) ($extra + [
            'id' => $id,
            'workflowid' => 1,
            'status' => $status,
            'triggertype' => 'manual',
            'userid' => null,
            'timecreated' => self::NOW - 600,
            'timefinished' => self::NOW - 598,
            'log' => json_encode($steps),
            'error' => null,
        ]);
    }

    /**
     * Export the list.
     *
     * @param array $runs
     * @param string $filter
     * @return array
     */
    private function export(array $runs, string $filter = 'all'): array {
        global $PAGE;
        $list = new run_list((object) ['id' => 1, 'name' => 'Flow'], $runs, $filter, self::NOW);
        return json_decode(json_encode($list->export_for_template($PAGE->get_renderer('core'))), true);
    }

    public function test_steps_use_readable_names_and_hide_raw_ids(): void {
        $data = $this->export([$this->make_run(1, 'success', [
            ['node' => 't', 'type' => 'trigger.manual', 'status' => 'ok', 'branch' => null, 'output' => ['userid' => 2]],
            ['node' => 'n1', 'type' => 'moodle.enrol_user', 'status' => 'ok', 'branch' => null,
                'output' => ['userid' => 4, 'courseid' => 3, 'role' => 'student']],
        ])]);
        $steps = $data['runs'][0]['steps'];
        $this->assertSame('Started by hand', $steps[0]['label']);
        $this->assertSame('Enrol user in course', $steps[1]['label']);
        $this->assertSame('n1', $steps[1]['node']);
        $this->assertSame('Done', $steps[1]['statuslabel']);
        $this->assertFalse($steps[1]['failed']);
        $this->assertSame([
            ['name' => 'userid', 'value' => '4'],
            ['name' => 'courseid', 'value' => '3'],
            ['name' => 'role', 'value' => 'student'],
        ], $steps[1]['fields']);
        $this->assertTrue($steps[1]['hasfields']);
    }

    public function test_failed_step_carries_its_error(): void {
        $data = $this->export([$this->make_run(1, 'failed', [
            ['node' => 't', 'type' => 'trigger.manual', 'status' => 'ok', 'branch' => null, 'output' => []],
            ['node' => 'h', 'type' => 'action.http', 'status' => 'error', 'branch' => null, 'output' => [],
                'error' => 'The URL is blocked.'],
        ], ['error' => 'The URL is blocked.'])]);
        $run = $data['runs'][0];
        $this->assertSame('Failed', $run['statuslabel']);
        $this->assertStringContainsString('bg-danger', $run['badge']);
        $this->assertTrue($run['failed']);
        $step = $run['steps'][1];
        $this->assertTrue($step['failed']);
        $this->assertSame('Failed', $step['statuslabel']);
        $this->assertSame('The URL is blocked.', $step['error']);
        $this->assertFalse($step['hasfields']);
        $this->assertFalse($run['showerror'], 'The failed step already says what went wrong.');
    }

    public function test_run_error_shows_when_no_step_explains_it(): void {
        $data = $this->export([$this->make_run(1, 'failed', [], ['error' => 'The workflow is not valid: notrigger.'])]);
        $this->assertTrue($data['runs'][0]['showerror']);
        $this->assertSame('The workflow is not valid: notrigger.', $data['runs'][0]['error']);
    }

    public function test_condition_steps_say_which_way_they_went(): void {
        $data = $this->export([$this->make_run(1, 'success', [
            ['node' => 'c', 'type' => 'logic.condition', 'status' => 'ok', 'branch' => 'false', 'output' => ['result' => false]],
        ])]);
        $this->assertSame('Result: No', $data['runs'][0]['steps'][0]['branch']);
    }

    public function test_nested_values_are_shortened_and_kept_as_raw_data(): void {
        $long = str_repeat('a', 300);
        $data = $this->export([$this->make_run(1, 'success', [
            ['node' => 'h', 'type' => 'action.http', 'status' => 'ok', 'branch' => null,
                'output' => ['status' => 200, 'body' => ['ok' => true], 'text' => $long, 'flag' => false]],
        ])]);
        $step = $data['runs'][0]['steps'][0];
        $values = array_column($step['fields'], 'value', 'name');
        $this->assertSame('200', $values['status']);
        $this->assertSame('{"ok":true}', $values['body']);
        $this->assertSame('false', $values['flag']);
        $this->assertLessThanOrEqual(121, \core_text::strlen($values['text']));
        $this->assertStringContainsString('"body"', $step['raw']);
    }

    public function test_trigger_labels(): void {
        $event = ['node' => 't', 'type' => 'trigger.event', 'status' => 'ok', 'branch' => null,
            'output' => ['eventname' => '\\core\\event\\course_completed']];
        $data = $this->export([
            $this->make_run(3, 'success', [$event], ['triggertype' => 'event']),
            $this->make_run(2, 'success', [], ['triggertype' => 'schedule']),
            $this->make_run(1, 'success', [], ['triggertype' => 'manual']),
        ]);
        $this->assertSame('Event: Course completed', $data['runs'][0]['trigger']);
        $this->assertSame('On a schedule', $data['runs'][1]['trigger']);
        $this->assertSame('Started by hand', $data['runs'][2]['trigger']);
    }

    public function test_run_shows_the_user_it_was_about_and_when(): void {
        $user = $this->getDataGenerator()->create_user(['firstname' => 'Ines', 'lastname' => 'Bel']);
        $data = $this->export([$this->make_run(1, 'success', [], ['userid' => $user->id, 'timecreated' => self::NOW - 3 * HOURSECS])]);
        $run = $data['runs'][0];
        $this->assertTrue($run['hasuser']);
        $this->assertSame('Ines Bel', $run['user']);
        $this->assertNotEmpty($run['picture']);
        $this->assertSame('3 hours ago', $run['ago']);
        $this->assertNotEmpty($run['time']);
    }

    public function test_only_the_newest_run_starts_open(): void {
        $data = $this->export([$this->make_run(2, 'success', []), $this->make_run(1, 'success', [])]);
        $this->assertTrue($data['runs'][0]['open']);
        $this->assertFalse($data['runs'][1]['open']);
    }

    public function test_filters_count_and_narrow_the_runs(): void {
        $runs = [$this->make_run(3, 'failed', []), $this->make_run(2, 'success', []), $this->make_run(1, 'success', [])];
        $data = $this->export($runs, 'failed');
        $filters = array_column($data['filters'], null, 'name');
        $this->assertSame(3, $filters['all']['count']);
        $this->assertSame(1, $filters['failed']['count']);
        $this->assertSame(2, $filters['success']['count']);
        $this->assertTrue($filters['failed']['active']);
        $this->assertFalse($filters['all']['active']);
        $this->assertStringContainsString('status=failed', $filters['failed']['url']);
        $this->assertCount(1, $data['runs']);
        $this->assertSame(3, $data['runs'][0]['id']);
    }

    public function test_empty_states(): void {
        $data = $this->export([]);
        $this->assertFalse($data['hasruns']);
        $this->assertFalse($data['hasfilters']);
        $this->assertSame('This workflow has not run yet.', $data['emptymessage']);

        $data = $this->export([$this->make_run(1, 'success', [])], 'failed');
        $this->assertFalse($data['hasruns']);
        $this->assertTrue($data['hasfilters']);
        $this->assertSame('No runs match this filter.', $data['emptymessage']);
    }

    public function test_unknown_filter_falls_back_to_all(): void {
        $data = $this->export([$this->make_run(1, 'success', [])], 'nonsense');
        $this->assertCount(1, $data['runs']);
    }
}
