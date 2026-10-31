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

use tool_autoflow\task\run_workflow;

/**
 * Tests for starting workflows from Moodle events.
 *
 * @package    tool_autoflow
 * @category   test
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_autoflow\observer
 * @covers     \tool_autoflow\local\runner
 */
final class observer_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        // External observers wait for the database transaction to end, and tests normally run inside one.
        $this->preventResetByRollback();
        $this->setAdminUser();
    }

    /**
     * Queued run tasks.
     *
     * @return array[]
     */
    private static function queued(): array {
        return array_values(array_map(
            fn($t) => json_decode($t->get_custom_data_as_string(), true),
            \core\task\manager::get_adhoc_tasks(run_workflow::class)
        ));
    }

    public function test_matching_event_queues_the_workflow_with_event_data(): void {
        $id = workflow_store::save(0, 'New user', workflow_store_test::graph(
            'trigger.event',
            ['eventname' => '\\core\\event\\user_created']
        ), true)['id'];
        $user = $this->getDataGenerator()->create_user();

        $queued = self::queued();
        $this->assertCount(1, $queued);
        $this->assertSame($id, $queued[0]['workflowid']);
        $this->assertSame('event', $queued[0]['triggertype']);
        $this->assertSame(0, $queued[0]['depth']);
        $this->assertSame('\\core\\event\\user_created', $queued[0]['trigger']['eventname']);
        $this->assertEquals($user->id, $queued[0]['trigger']['relateduserid']);
        $this->assertEquals($user->id, $queued[0]['trigger']['objectid']);
    }

    public function test_other_events_queue_nothing(): void {
        workflow_store::save(0, 'Deleted', workflow_store_test::graph(
            'trigger.event',
            ['eventname' => '\\core\\event\\user_deleted']
        ), true);
        $this->getDataGenerator()->create_user();
        $this->assertSame([], self::queued());
    }

    public function test_a_workflow_that_triggers_itself_stops_at_the_depth_limit(): void {
        global $DB;
        // Sending a notification fires notification_sent, which starts the same workflow again.
        $graph = json_encode([
            'nodes' => [
                ['id' => 't', 'type' => 'trigger.event',
                    'data' => ['params' => ['eventname' => '\\core\\event\\notification_sent']]],
                ['id' => 'send', 'type' => 'moodle.send_notification', 'data' => ['params' => [
                    'userid' => get_admin()->id, 'subject' => 'Again', 'message' => 'Loop']]],
            ],
            'edges' => [['source' => 't', 'target' => 'send']],
        ]);
        $id = workflow_store::save(0, 'Echo', $graph, true)['id'];
        // Real sending, not a message sink: the sink skips the notification_sent event. PHPUnit never sends email.
        $this->expectOutputRegex('/success/');

        // A first notification from outside any workflow starts the chain at depth 0.
        $message = new \core\message\message();
        $message->component = 'tool_autoflow';
        $message->name = 'notification';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = get_admin();
        $message->subject = 'Start';
        $message->fullmessage = 'Start';
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->notification = 1;
        message_send($message);

        for ($i = 0; $i < 10 && \core\task\manager::get_adhoc_tasks(run_workflow::class); $i++) {
            $this->runAdhocTasks(run_workflow::class);
        }
        $this->assertSame([], self::queued(), 'the chain ended');
        $this->assertSame(runner::MAX_DEPTH + 1, $DB->count_records('tool_autoflow_run', ['workflowid' => $id]));
    }
}
