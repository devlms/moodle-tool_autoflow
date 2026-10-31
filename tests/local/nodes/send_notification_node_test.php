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

namespace tool_autoflow\local\nodes;

use tool_autoflow\local\engine\node_failed;
use tool_autoflow\local\engine\param;

/**
 * Tests for the send notification node.
 *
 * @package    tool_autoflow
 * @category   test
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_autoflow\local\nodes\send_notification_node
 */
final class send_notification_node_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_sends_a_notification_from_the_noreply_user(): void {
        $user = $this->getDataGenerator()->create_user();
        $sink = $this->redirectMessages();
        $result = (new send_notification_node())->execute(
            ['userid' => (string) $user->id, 'subject' => 'Welcome', 'message' => "Hi there\nSee you"],
            []
        );

        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertEquals($user->id, $messages[0]->useridto);
        $this->assertEquals(\core_user::NOREPLY_USER, $messages[0]->useridfrom);
        $this->assertSame('Welcome', $messages[0]->subject);
        $this->assertSame("Hi there\nSee you", $messages[0]->fullmessage);
        $this->assertSame('tool_autoflow', $messages[0]->component);
        $this->assertSame('notification', $messages[0]->eventtype);
        $this->assertEquals(['messageid' => $messages[0]->id, 'userid' => $user->id], $result->output);
    }

    public function test_unknown_or_deleted_users_fail(): void {
        $deleted = $this->getDataGenerator()->create_user(['deleted' => 1]);
        foreach ([0, 99999999, $deleted->id, 'abc'] as $userid) {
            try {
                (new send_notification_node())->execute(['userid' => $userid, 'subject' => 's', 'message' => 'm'], []);
                $this->fail("Sent to $userid");
            } catch (node_failed $e) {
                $this->assertStringContainsString('user', $e->getMessage());
            }
        }
    }

    public function test_subject_is_required(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->expectException(node_failed::class);
        (new send_notification_node())->execute(['userid' => $user->id, 'subject' => ' ', 'message' => 'm'], []);
    }

    public function test_metadata(): void {
        $node = new send_notification_node();
        $this->assertSame('moodle.send_notification', $node->type());
        $this->assertSame([], $node->branches());
        $this->assertEquals([new param('userid', 'user', true), new param('subject', 'text', true),
            new param('message', 'textarea', true)], $node->params());
    }
}
