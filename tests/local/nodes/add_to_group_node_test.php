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
 * Tests for the node that adds a user to a group.
 *
 * @package    tool_autoflow
 * @category   test
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_autoflow\local\nodes\add_to_group_node
 * @covers     \tool_autoflow\local\nodes\node_values
 */
final class add_to_group_node_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_adds_an_enrolled_user(): void {
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $user = $this->getDataGenerator()->create_and_enrol($course);
        $output = (new add_to_group_node())->execute(['userid' => (string) $user->id, 'groupid' => (string) $group->id], [])
            ->output;
        $this->assertSame(['userid' => (int) $user->id, 'groupid' => (int) $group->id], $output);
        $this->assertTrue(groups_is_member($group->id, $user->id));
    }

    public function test_user_must_be_enrolled(): void {
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $user = $this->getDataGenerator()->create_user();
        $this->expectException(node_failed::class);
        $this->expectExceptionMessage('core_group_add_group_members');
        (new add_to_group_node())->execute(['userid' => $user->id, 'groupid' => $group->id], []);
    }

    public function test_needs_permission_to_manage_groups(): void {
        $course = $this->getDataGenerator()->create_course();
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $student = $this->getDataGenerator()->create_and_enrol($course);
        $this->setUser($student);
        $this->expectException(node_failed::class);
        (new add_to_group_node())->execute(['userid' => $student->id, 'groupid' => $group->id], []);
    }

    public function test_ids_must_be_numbers(): void {
        $this->setAdminUser();
        $this->expectException(node_failed::class);
        $this->expectExceptionMessage('group id');
        (new add_to_group_node())->execute(['userid' => 2, 'groupid' => ''], []);
    }

    public function test_metadata(): void {
        $node = new add_to_group_node();
        $this->assertSame('moodle.add_to_group', $node->type());
        $this->assertSame([], $node->branches());
        $this->assertEquals([new param('userid', 'user', true), new param('groupid', 'number', true)], $node->params());
    }
}
