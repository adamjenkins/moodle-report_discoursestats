<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace report_discoursestats;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/report/discoursestats/lib.php');

/**
 * Unit tests for report_discoursestats lib.php functions.
 *
 * @package    report_discoursestats
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::discoursestats_evaluate_formula
 * @covers     ::discoursestats_apply_feedback_template
 * @covers     ::discoursestats_getforumids
 * @covers     ::discoursestats_getsort
 */
final class lib_test extends \advanced_testcase {
    /**
     * Build a minimal result object with all formula fields set to zero,
     * then override specific keys.
     *
     * @param array $overrides
     * @return \stdClass
     */
    private function make_result(array $overrides = []): \stdClass {
        $result = new \stdClass();
        foreach (discoursestats_formula_fields() as $field) {
            $result->$field = 0;
        }
        foreach ($overrides as $key => $val) {
            $result->$key = $val;
        }
        return $result;
    }

    /**
     * Basic arithmetic: addition, subtraction, multiplication, division.
     */
    public function test_evaluate_formula_arithmetic(): void {
        $r = $this->make_result(['posts' => 10, 'replies' => 4]);
        $this->assertEqualsWithDelta(14.0, discoursestats_evaluate_formula('{posts} + {replies}', $r), 0.001);
        $this->assertEqualsWithDelta(6.0, discoursestats_evaluate_formula('{posts} - {replies}', $r), 0.001);
        $this->assertEqualsWithDelta(40.0, discoursestats_evaluate_formula('{posts} * {replies}', $r), 0.001);
        $this->assertEqualsWithDelta(2.5, discoursestats_evaluate_formula('{posts} / {replies}', $r), 0.001);
    }

    /**
     * Field names without braces are also substituted.
     */
    public function test_evaluate_formula_no_braces(): void {
        $r = $this->make_result(['posts' => 5]);
        $this->assertEqualsWithDelta(10.0, discoursestats_evaluate_formula('posts * 2', $r), 0.001);
    }

    /**
     * Parentheses control evaluation order.
     */
    public function test_evaluate_formula_parentheses(): void {
        $r = $this->make_result(['posts' => 3, 'replies' => 2]);
        $this->assertEqualsWithDelta(23.0, discoursestats_evaluate_formula('{posts} + {replies} * 10', $r), 0.001);
        $this->assertEqualsWithDelta(50.0, discoursestats_evaluate_formula('({posts} + {replies}) * 10', $r), 0.001);
    }

    /**
     * Unary minus.
     */
    public function test_evaluate_formula_unary_minus(): void {
        $r = $this->make_result(['posts' => 5]);
        $this->assertEqualsWithDelta(-5.0, discoursestats_evaluate_formula('-{posts}', $r), 0.001);
    }

    /**
     * Built-in functions: min, max, round, ceil, floor, abs.
     */
    public function test_evaluate_formula_builtin_functions(): void {
        $r = $this->make_result(['posts' => 7, 'replies' => 3]);
        $this->assertEqualsWithDelta(3.0, discoursestats_evaluate_formula('min({posts}, {replies})', $r), 0.001);
        $this->assertEqualsWithDelta(7.0, discoursestats_evaluate_formula('max({posts}, {replies})', $r), 0.001);
        $this->assertEqualsWithDelta(3.0, discoursestats_evaluate_formula('round(3.4)', $r), 0.001);
        $this->assertEqualsWithDelta(4.0, discoursestats_evaluate_formula('round(3.5)', $r), 0.001);
        $this->assertEqualsWithDelta(4.0, discoursestats_evaluate_formula('ceil(3.1)', $r), 0.001);
        $this->assertEqualsWithDelta(3.0, discoursestats_evaluate_formula('floor(3.9)', $r), 0.001);
        $this->assertEqualsWithDelta(5.0, discoursestats_evaluate_formula('abs(-5)', $r), 0.001);
    }

    /**
     * A min() cap on a scaled field value.
     */
    public function test_evaluate_formula_min_cap(): void {
        $r = $this->make_result(['replies' => 300]);
        $this->assertEqualsWithDelta(100.0, discoursestats_evaluate_formula('min({replies} * 0.5, 100)', $r), 0.001);
        $r2 = $this->make_result(['replies' => 50]);
        $this->assertEqualsWithDelta(25.0, discoursestats_evaluate_formula('min({replies} * 0.5, 100)', $r2), 0.001);
    }

    /**
     * Division by zero returns 0.
     */
    public function test_evaluate_formula_division_by_zero(): void {
        $r = $this->make_result();
        $this->assertEqualsWithDelta(0.0, discoursestats_evaluate_formula('10 / 0', $r), 0.001);
    }

    /**
     * Empty or invalid formula returns 0.
     */
    public function test_evaluate_formula_invalid(): void {
        $r = $this->make_result();
        $this->assertEqualsWithDelta(0.0, discoursestats_evaluate_formula('', $r), 0.001);
        $this->assertEqualsWithDelta(0.0, discoursestats_evaluate_formula('!!!', $r), 0.001);
    }

    /**
     * Unknown fields resolve to 0.
     */
    public function test_evaluate_formula_unknown_field(): void {
        $r = $this->make_result();
        $this->assertEqualsWithDelta(0.0, discoursestats_evaluate_formula('{unknownfield}', $r), 0.001);
    }

    /**
     * Literal number with no fields.
     */
    public function test_evaluate_formula_literal(): void {
        $r = $this->make_result();
        $this->assertEqualsWithDelta(42.0, discoursestats_evaluate_formula('42', $r), 0.001);
        $this->assertEqualsWithDelta(3.14, discoursestats_evaluate_formula('3.14', $r), 0.001);
    }

    /**
     * Placeholders are substituted with field values.
     */
    public function test_apply_feedback_template_substitution(): void {
        $result = new \stdClass();
        $result->posts   = 10;
        $result->replies = 4;
        $result->wordcount = 250;

        $template = 'You wrote {posts} posts and {replies} replies ({wordcount} words).';
        $expected = 'You wrote 10 posts and 4 replies (250 words).';
        $this->assertSame($expected, discoursestats_apply_feedback_template($template, $result));
    }

    /**
     * Unknown placeholders are left as-is.
     */
    public function test_apply_feedback_template_unknown_placeholder(): void {
        $result = new \stdClass();
        $result->posts = 5;
        $template = '{posts} posts and {unknown}.';
        $this->assertSame('5 posts and {unknown}.', discoursestats_apply_feedback_template($template, $result));
    }

    /**
     * Empty template returns empty string.
     */
    public function test_apply_feedback_template_empty(): void {
        $result = new \stdClass();
        $this->assertSame('', discoursestats_apply_feedback_template('', $result));
    }

    /**
     * JSON array in schedule->forums is decoded and cast to int.
     */
    public function test_getforumids_json_array(): void {
        $schedule = new \stdClass();
        $schedule->forums = json_encode([3, 7, 12]);
        $schedule->course = 1;
        $this->assertSame([3, 7, 12], discoursestats_getforumids($schedule));
    }

    /**
     * JSON with string IDs are cast to int.
     */
    public function test_getforumids_json_string_ids(): void {
        $schedule = new \stdClass();
        $schedule->forums = json_encode(['5', '8']);
        $schedule->course = 1;
        $this->assertSame([5, 8], discoursestats_getforumids($schedule));
    }

    /**
     * Null forums field falls back to all forums in course (DB-backed).
     */
    public function test_getforumids_null_falls_back_to_all(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $forum1 = $this->getDataGenerator()->create_module('forum', ['course' => $course->id]);
        $forum2 = $this->getDataGenerator()->create_module('forum', ['course' => $course->id]);

        $schedule = new \stdClass();
        $schedule->forums = null;
        $schedule->course = $course->id;

        $ids = discoursestats_getforumids($schedule);
        $this->assertContains((int)$forum1->id, $ids);
        $this->assertContains((int)$forum2->id, $ids);
    }

    /**
     * Valid column name + asc produces correct SQL fragment.
     */
    public function test_getsort_valid_column_asc(): void {
        $this->assertSame('posts ASC', discoursestats_getsort('posts', 'asc'));
    }

    /**
     * Valid column name + desc produces correct SQL fragment.
     */
    public function test_getsort_valid_column_desc(): void {
        $this->assertSame('posts DESC', discoursestats_getsort('posts', 'desc'));
    }

    /**
     * Sort type is case-insensitive.
     */
    public function test_getsort_case_insensitive(): void {
        $this->assertSame('replies ASC', discoursestats_getsort('replies', 'ASC'));
        $this->assertSame('replies DESC', discoursestats_getsort('replies', 'DESC'));
    }

    /**
     * Invalid column name falls back to userid ASC.
     */
    public function test_getsort_invalid_column(): void {
        $this->assertSame('userid ASC', discoursestats_getsort('injected; DROP TABLE--', 'asc'));
    }

    /**
     * Null/empty sort name falls back to userid ASC.
     */
    public function test_getsort_null_sort(): void {
        $this->assertSame('userid ASC', discoursestats_getsort(null, 'asc'));
        $this->assertSame('userid ASC', discoursestats_getsort('', 'asc'));
    }
}
