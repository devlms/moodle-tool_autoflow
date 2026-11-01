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

namespace tool_autoflow\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Tests for the privacy provider.
 *
 * @package    tool_autoflow
 * @category   test
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_autoflow\privacy\provider
 */
final class provider_test extends \core_privacy\tests\provider_testcase {
    /** @var \stdClass Student a run was about. */
    private $student;
    /** @var \stdClass Manager who edited the workflow. */
    private $editor;
    /** @var \stdClass User the workflow runs as. */
    private $runas;
    /** @var \stdClass User with no data. */
    private $other;

    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $this->student = $gen->create_user();
        $this->editor = $gen->create_user();
        $this->runas = $gen->create_user();
        $this->other = $gen->create_user();
        $workflowid = $DB->insert_record('tool_autoflow_workflow', [
            'name' => 'Welcome', 'enabled' => 1, 'graph' => '{}', 'triggertype' => 'trigger.manual', 'triggerdata' => '',
            'runasuserid' => $this->runas->id,
            'usermodified' => $this->editor->id, 'timecreated' => 100, 'timemodified' => 200,
        ]);
        $DB->insert_record('tool_autoflow_run', [
            'workflowid' => $workflowid, 'status' => 'success', 'triggertype' => 'event',
            'userid' => $this->student->id, 'timecreated' => 300,
        ]);
    }

    public function test_metadata_lists_both_tables_and_the_http_node(): void {
        $items = provider::get_metadata(new collection('tool_autoflow'))->get_collection();
        $names = array_map(fn($i) => $i->get_name(), $items);
        $this->assertEqualsCanonicalizing(['tool_autoflow_run', 'tool_autoflow_workflow', 'httprequest'], $names);
        $http = array_values(array_filter($items, fn($i) => $i->get_name() === 'httprequest'))[0];
        $this->assertInstanceOf(\core_privacy\local\metadata\types\external_location::class, $http);
        $run = array_values(array_filter($items, fn($i) => $i->get_name() === 'tool_autoflow_run'))[0];
        $this->assertEqualsCanonicalizing(['userid', 'log', 'error', 'timecreated'], array_keys($run->get_privacy_fields()));
    }

    public function test_contexts_only_for_users_with_data(): void {
        $system = \context_system::instance();
        foreach ([$this->student, $this->editor, $this->runas] as $user) {
            $this->assertEquals([$system->id], provider::get_contexts_for_userid($user->id)->get_contextids());
        }
        $this->assertEmpty(provider::get_contexts_for_userid($this->other->id)->get_contextids());
    }

    public function test_users_in_system_context(): void {
        $userlist = new userlist(\context_system::instance(), 'tool_autoflow');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing(
            [$this->student->id, $this->editor->id, $this->runas->id],
            $userlist->get_userids()
        );

        $courselist = new userlist(\context_course::instance(SITEID), 'tool_autoflow');
        provider::get_users_in_context($courselist);
        $this->assertEmpty($courselist->get_userids());
    }

    public function test_export_contains_runs_and_workflows(): void {
        $system = \context_system::instance();
        $this->export_context_data_for_user($this->student->id, $system, 'tool_autoflow');
        $data = writer::with_context($system)->get_data([get_string('pluginname', 'tool_autoflow')]);
        $this->assertCount(1, $data->runs);
        $this->assertSame('event', $data->runs[0]['trigger']);
        $this->assertEmpty($data->workflows);

        writer::reset();
        $this->export_context_data_for_user($this->editor->id, $system, 'tool_autoflow');
        $data = writer::with_context($system)->get_data([get_string('pluginname', 'tool_autoflow')]);
        $this->assertSame('Welcome', $data->workflows[0]['name']);
    }

    public function test_delete_for_user_removes_only_their_references(): void {
        global $DB;
        $system = \context_system::instance();
        provider::delete_data_for_user(new approved_contextlist($this->student, 'tool_autoflow', [$system->id]));
        // The run log can hold the user's name and details, so the whole run goes.
        $this->assertEquals(0, $DB->count_records('tool_autoflow_run'));
        $this->assertTrue($DB->record_exists('tool_autoflow_workflow', ['usermodified' => $this->editor->id]));
    }

    public function test_delete_for_users_clears_editor_and_runas(): void {
        global $DB;
        $userlist = new approved_userlist(
            \context_system::instance(),
            'tool_autoflow',
            [$this->editor->id, $this->runas->id]
        );
        provider::delete_data_for_users($userlist);
        $workflow = $DB->get_record('tool_autoflow_workflow', ['name' => 'Welcome']);
        $this->assertEquals(0, $workflow->usermodified);
        $this->assertNull($workflow->runasuserid);
        $this->assertTrue($DB->record_exists('tool_autoflow_run', ['userid' => $this->student->id]));
    }

    public function test_delete_all_users_in_system_context(): void {
        global $DB;
        provider::delete_data_for_all_users_in_context(\context_system::instance());
        $this->assertEquals(0, $DB->count_records('tool_autoflow_run'));
        $this->assertFalse($DB->record_exists_select('tool_autoflow_workflow', 'usermodified <> 0 OR runasuserid IS NOT NULL'));
    }

    public function test_delete_all_ignores_other_contexts(): void {
        global $DB;
        provider::delete_data_for_all_users_in_context(\context_course::instance(SITEID));
        $this->assertTrue($DB->record_exists('tool_autoflow_run', ['userid' => $this->student->id]));
    }

    public function test_export_skips_other_contexts(): void {
        $course = \context_course::instance(SITEID);
        provider::export_user_data(new approved_contextlist($this->student, 'tool_autoflow', [$course->id]));
        $this->assertFalse(writer::with_context($course)->has_any_data());
    }

    public function test_delete_for_no_users_changes_nothing(): void {
        global $DB;
        provider::delete_data_for_users(new approved_userlist(\context_system::instance(), 'tool_autoflow', []));
        provider::delete_data_for_user(new approved_contextlist(
            $this->student,
            'tool_autoflow',
            [\context_course::instance(SITEID)->id]
        ));
        $this->assertTrue($DB->record_exists('tool_autoflow_run', ['userid' => $this->student->id]));
        $this->assertTrue($DB->record_exists('tool_autoflow_workflow', ['runasuserid' => $this->runas->id]));
    }
}
