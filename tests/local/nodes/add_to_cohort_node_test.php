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
 * Tests for the node that adds a user to a cohort.
 *
 * @package    tool_autoflow
 * @category   test
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_autoflow\local\nodes\add_to_cohort_node
 */
final class add_to_cohort_node_test extends \advanced_testcase {
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->dirroot . '/cohort/lib.php');
        $this->resetAfterTest();
    }

    public function test_adds_a_user(): void {
        $this->setAdminUser();
        $cohort = $this->getDataGenerator()->create_cohort();
        $user = $this->getDataGenerator()->create_user();
        $output = (new add_to_cohort_node())->execute(['userid' => (string) $user->id, 'cohortid' => (string) $cohort->id], [])
            ->output;
        $this->assertSame(['userid' => (int) $user->id, 'cohortid' => (int) $cohort->id, 'added' => true], $output);
        $this->assertTrue(cohort_is_member($cohort->id, $user->id));
    }

    public function test_existing_member_is_not_a_failure(): void {
        $this->setAdminUser();
        $cohort = $this->getDataGenerator()->create_cohort();
        $user = $this->getDataGenerator()->create_user();
        cohort_add_member($cohort->id, $user->id);
        $output = (new add_to_cohort_node())->execute(['userid' => $user->id, 'cohortid' => $cohort->id], [])->output;
        $this->assertFalse($output['added']);
        $this->assertTrue(cohort_is_member($cohort->id, $user->id));
    }

    public function test_unknown_cohort_fails_with_moodle_message(): void {
        $this->setAdminUser();
        $user = $this->getDataGenerator()->create_user();
        $this->expectException(node_failed::class);
        $this->expectExceptionMessage('cohort id=999999 not exists');
        (new add_to_cohort_node())->execute(['userid' => $user->id, 'cohortid' => 999999], []);
    }

    public function test_unknown_user_fails(): void {
        $this->setAdminUser();
        $cohort = $this->getDataGenerator()->create_cohort();
        $this->expectException(node_failed::class);
        $this->expectExceptionMessage('user id=999999 not exists');
        (new add_to_cohort_node())->execute(['userid' => 999999, 'cohortid' => $cohort->id], []);
    }

    public function test_needs_permission_to_assign_cohorts(): void {
        $cohort = $this->getDataGenerator()->create_cohort();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        try {
            (new add_to_cohort_node())->execute(['userid' => $user->id, 'cohortid' => $cohort->id], []);
            $this->fail('A user without moodle/cohort:assign added a cohort member.');
        } catch (node_failed $e) {
            $this->assertFalse(cohort_is_member($cohort->id, $user->id));
        }
    }

    public function test_ids_must_be_numbers(): void {
        $this->setAdminUser();
        $this->expectException(node_failed::class);
        $this->expectExceptionMessage('cohort id');
        (new add_to_cohort_node())->execute(['userid' => 2, 'cohortid' => 'abc'], []);
    }

    public function test_metadata(): void {
        $node = new add_to_cohort_node();
        $this->assertSame('moodle.add_to_cohort', $node->type());
        $this->assertSame([], $node->branches());
        $this->assertEquals([new param('userid', 'user', true), new param('cohortid', 'number', true)], $node->params());
    }
}
