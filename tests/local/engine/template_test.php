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

declare(strict_types=1);

namespace tool_autoflow\local\engine;

/**
 * Tests for the placeholder template renderer.
 *
 * @package    tool_autoflow
 * @category   test
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_autoflow\local\engine\template
 */
final class template_test extends \basic_testcase {
    /** @var array Context used by most tests. */
    private const CTX = [
        'trigger' => ['relateduserid' => 42, 'courseid' => 7, 'other' => ['grade' => 85.5]],
        'user' => ['firstname' => 'Amina', 'email' => 'amina@example.com'],
        'flag' => true,
        'nothing' => null,
    ];

    public function test_text_without_placeholders_is_unchanged(): void {
        $this->assertSame('Hello there', template::render('Hello there', self::CTX));
    }

    public function test_placeholder_is_replaced_by_path_value(): void {
        $this->assertSame('Hi Amina!', template::render('Hi {{user.firstname}}!', self::CTX));
    }

    public function test_spaces_inside_braces_are_allowed(): void {
        $this->assertSame('Hi Amina', template::render('Hi {{ user.firstname }}', self::CTX));
    }

    public function test_nested_paths_and_numbers(): void {
        $this->assertSame(
            'User 42 got 85.5 in 7',
            template::render('User {{trigger.relateduserid}} got {{trigger.other.grade}} in {{trigger.courseid}}', self::CTX)
        );
    }

    public function test_missing_path_renders_empty(): void {
        $this->assertSame('Hi !', template::render('Hi {{user.lastname}}!', self::CTX));
        $this->assertSame('x', template::render('x{{nope.deeper.still}}', self::CTX));
    }

    public function test_booleans_and_null_render_as_words_and_empty(): void {
        $this->assertSame('true|', template::render('{{flag}}|{{nothing}}', self::CTX));
    }

    public function test_arrays_render_as_json(): void {
        $this->assertSame('{"grade":85.5}', template::render('{{trigger.other}}', self::CTX));
    }

    public function test_path_through_a_scalar_is_missing(): void {
        $this->assertSame('', template::render('{{user.firstname.length}}', self::CTX));
    }

    public function test_invalid_placeholder_syntax_is_left_as_text(): void {
        $this->assertSame('{{ user firstname }} {{}}', template::render('{{ user firstname }} {{}}', self::CTX));
    }

    public function test_resolve_keeps_type_for_a_single_placeholder(): void {
        $this->assertSame(42, template::resolve('{{trigger.relateduserid}}', self::CTX));
        $this->assertSame(['grade' => 85.5], template::resolve(' {{ trigger.other }} ', self::CTX));
        $this->assertNull(template::resolve('{{missing}}', self::CTX));
    }

    public function test_resolve_falls_back_to_render_for_mixed_text(): void {
        $this->assertSame('id 42', template::resolve('id {{trigger.relateduserid}}', self::CTX));
    }

    public function test_resolve_passes_non_strings_through(): void {
        $this->assertSame(5, template::resolve(5, self::CTX));
        $this->assertSame(['a' => 'Amina'], template::resolve(['a' => '{{user.firstname}}'], self::CTX));
    }

    public function test_lookup_reads_dotted_paths(): void {
        $this->assertSame(85.5, template::lookup('trigger.other.grade', self::CTX));
        $this->assertNull(template::lookup('trigger.missing', self::CTX));
        $this->assertSame(self::CTX['user'], template::lookup('user', self::CTX));
    }
}
