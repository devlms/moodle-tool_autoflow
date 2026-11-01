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
 * Tests for the node that enrols a user in a course.
 *
 * @package    tool_autoflow
 * @category   test
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_autoflow\local\nodes\enrol_user_node
 * @covers     \tool_autoflow\local\nodes\call_function_node
 * @covers     \tool_autoflow\local\nodes\node_values
 */
final class enrol_user_node_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_enrols_as_student_by_default(): void {
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $output = (new enrol_user_node())->execute(['userid' => (string) $user->id, 'courseid' => (string) $course->id], [])
            ->output;
        $this->assertSame(['userid' => (int) $user->id, 'courseid' => (int) $course->id, 'role' => 'student'], $output);
        $context = \context_course::instance($course->id);
        $this->assertTrue(is_enrolled($context, $user));
        $this->assertTrue(user_has_role_assignment($user->id, $this->role('student'), $context->id));
    }

    public function test_enrols_with_another_role(): void {
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        (new enrol_user_node())->execute(['userid' => $user->id, 'courseid' => $course->id, 'role' => 'editingteacher'], []);
        $context = \context_course::instance($course->id);
        $this->assertTrue(user_has_role_assignment($user->id, $this->role('editingteacher'), $context->id));
    }

    public function test_unknown_role_fails(): void {
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $this->expectException(node_failed::class);
        $this->expectExceptionMessage('No role with the short name "wizard"');
        (new enrol_user_node())->execute(['userid' => 2, 'courseid' => $course->id, 'role' => 'wizard'], []);
    }

    public function test_needs_permission_to_enrol(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($this->getDataGenerator()->create_user());
        $this->expectException(node_failed::class);
        $this->expectExceptionMessage('enrol_manual_enrol_users');
        (new enrol_user_node())->execute(['userid' => $user->id, 'courseid' => $course->id], []);
    }

    public function test_ids_must_be_numbers(): void {
        $this->setAdminUser();
        $this->expectException(node_failed::class);
        $this->expectExceptionMessage('course id');
        (new enrol_user_node())->execute(['userid' => 2, 'courseid' => '1 OR 1=1'], []);
    }

    public function test_metadata(): void {
        $node = new enrol_user_node();
        $this->assertSame('moodle.enrol_user', $node->type());
        $this->assertSame([], $node->branches());
        $this->assertEquals(
            [new param('userid', 'user', true), new param('courseid', 'number', true), new param('role', 'text', false)],
            $node->params()
        );
    }

    /**
     * Role id by short name.
     *
     * @param string $shortname
     * @return int
     */
    private function role(string $shortname): int {
        global $DB;
        return (int) $DB->get_field('role', 'id', ['shortname' => $shortname], MUST_EXIST);
    }
}
