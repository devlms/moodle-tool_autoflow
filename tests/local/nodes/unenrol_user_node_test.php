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
 * Tests for the node that removes a manual enrolment.
 *
 * @package    tool_autoflow
 * @category   test
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_autoflow\local\nodes\unenrol_user_node
 */
final class unenrol_user_node_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_removes_a_manual_enrolment(): void {
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student', null, 'manual');
        $output = (new unenrol_user_node())->execute(['userid' => (string) $user->id, 'courseid' => (string) $course->id], [])
            ->output;
        $this->assertSame(['userid' => (int) $user->id, 'courseid' => (int) $course->id, 'removed' => true], $output);
        $this->assertFalse(is_enrolled(\context_course::instance($course->id), $user));
    }

    public function test_user_not_enrolled_is_not_a_failure(): void {
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $output = (new unenrol_user_node())->execute(['userid' => $user->id, 'courseid' => $course->id], [])->output;
        $this->assertFalse($output['removed']);
    }

    public function test_other_enrolment_methods_are_left_alone(): void {
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student', null, 'self');
        $output = (new unenrol_user_node())->execute(['userid' => $user->id, 'courseid' => $course->id], [])->output;
        $this->assertFalse($output['removed']);
        $this->assertTrue(is_enrolled(\context_course::instance($course->id), $user));
    }

    public function test_needs_permission_to_unenrol(): void {
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student', null, 'manual');
        $this->setUser($student);
        try {
            (new unenrol_user_node())->execute(['userid' => $student->id, 'courseid' => $course->id], []);
            $this->fail('A student removed an enrolment.');
        } catch (node_failed $e) {
            $this->assertStringContainsString('enrol_manual_unenrol_users', $e->getMessage());
            $this->assertTrue(is_enrolled(\context_course::instance($course->id), $student));
        }
    }

    public function test_ids_must_be_numbers(): void {
        $this->setAdminUser();
        $this->expectException(node_failed::class);
        $this->expectExceptionMessage('course id');
        (new unenrol_user_node())->execute(['userid' => 2, 'courseid' => ''], []);
    }

    public function test_metadata(): void {
        $node = new unenrol_user_node();
        $this->assertSame('moodle.unenrol_user', $node->type());
        $this->assertSame([], $node->branches());
        $this->assertEquals([new param('userid', 'user', true), new param('courseid', 'number', true)], $node->params());
    }
}
