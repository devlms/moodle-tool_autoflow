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

use tool_autoflow\local\engine\nodes\condition_node;

/**
 * Tests for the condition node.
 *
 * @package    tool_autoflow
 * @category   test
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_autoflow\local\engine\nodes\condition_node
 * @covers     \tool_autoflow\local\engine\node_result
 */
final class condition_node_test extends \basic_testcase {
    /**
     * Run the node and return the branch it picked.
     *
     * @param mixed $left
     * @param string $operator
     * @param mixed $right
     * @return string|null
     */
    private static function branch($left, string $operator, $right = null): ?string {
        return (new condition_node())->execute(['left' => $left, 'operator' => $operator, 'right' => $right], [])->branch;
    }

    /**
     * Cases: left, operator, right, expected branch.
     *
     * @return array
     */
    public static function cases(): array {
        return [
            'equal numbers' => [85, 'eq', '85', 'true'],
            'equal floats and ints' => ['1.0', 'eq', 1, 'true'],
            'different numbers' => [84, 'eq', 85, 'false'],
            'equal text' => ['Amina', 'eq', 'Amina', 'true'],
            'text is case sensitive' => ['amina', 'eq', 'Amina', 'false'],
            'true and the word true' => [true, 'eq', 'true', 'true'],
            'null and empty text' => [null, 'eq', '', 'true'],
            'not equal' => ['a', 'neq', 'b', 'true'],
            'not equal when equal' => [3, 'neq', '3', 'false'],
            'greater' => [10, 'gt', 9, 'true'],
            'greater when equal' => [9, 'gt', 9, 'false'],
            'numbers compare as numbers, not text' => ['10', 'gt', '9', 'true'],
            'greater or equal' => [9, 'gte', 9, 'true'],
            'greater or equal when less' => [8, 'gte', 9, 'false'],
            'less' => [1, 'lt', 2, 'true'],
            'less when equal' => [2, 'lt', 2, 'false'],
            'less or equal' => [2, 'lte', 2, 'true'],
            'less or equal when greater' => [3, 'lte', 2, 'false'],
            'text order' => ['apple', 'lt', 'banana', 'true'],
            'number against text compares as text' => ['10', 'gt', 'abc', 'false'],
            'text against number compares as text' => ['abc', 'lt', '10', 'false'],
            'text order reversed' => ['banana', 'lt', 'apple', 'false'],
            'text contains' => ['Hello world', 'contains', 'world', 'true'],
            'text does not contain' => ['Hello world', 'contains', 'moon', 'false'],
            'list contains' => [['a', 'b'], 'contains', 'b', 'true'],
            'list contains a number as text' => [[1, 2], 'contains', '2', 'true'],
            'list does not contain' => [['a'], 'contains', 'z', 'false'],
            'list with smaller items does not contain' => [[1, 2], 'contains', 3, 'false'],
            'list with larger items does not contain' => [['y', 'z'], 'contains', 'a', 'false'],
            'empty text' => ['', 'empty', null, 'true'],
            'empty null' => [null, 'empty', null, 'true'],
            'empty list' => [[], 'empty', null, 'true'],
            'zero is not empty' => [0, 'empty', null, 'false'],
            'not empty' => ['x', 'notempty', null, 'true'],
            'not empty on empty' => ['', 'notempty', null, 'false'],
        ];
    }

    /**
     * Each operator picks the right branch.
     *
     * @dataProvider cases
     * @param mixed $left
     * @param string $operator
     * @param mixed $right
     * @param string $expected
     */
    public function test_operators($left, string $operator, $right, string $expected): void {
        $this->assertSame($expected, self::branch($left, $operator, $right));
    }

    public function test_output_records_the_result(): void {
        $result = (new condition_node())->execute(['left' => 1, 'operator' => 'eq', 'right' => 1], []);
        $this->assertSame(['result' => true], $result->output);
        $this->assertSame([], $result->vars);
    }

    public function test_unknown_operator_fails(): void {
        $this->expectException(node_failed::class);
        $this->expectExceptionMessage('like');
        self::branch(1, 'like', 1);
    }

    public function test_missing_operator_fails(): void {
        $this->expectException(node_failed::class);
        (new condition_node())->execute(['left' => 1], []);
    }

    public function test_type_name(): void {
        $this->assertSame('logic.condition', (new condition_node())->type());
    }
}
