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

namespace mod_edflex\util;

use advanced_testcase;

/**
 * Unit tests for the mod_edflex\util\formatter class.
 *
 * @package     mod_edflex
 * @copyright   2025 Edflex <support@edflex.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \mod_edflex\util\formatter
 */
final class formatter_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * Test format_duration with valid ISO 8601 duration.
     */
    public function test_format_duration_valid(): void {
        $result = formatter::format_duration('P2DT3H');
        $this->assertEquals('2 days 3 hours', $result);
    }

    /**
     * Test format_duration with invalid ISO 8601 duration.
     */
    public function test_format_duration_invalid(): void {
        $result = formatter::format_duration('invalid_duration');
        $this->assertEquals('invalid_duration', $result);
    }

    /**
     * Test format_duration with null input.
     */
    public function test_format_duration_null(): void {
        $result = formatter::format_duration(null);
        $this->assertEquals('', $result);
    }

    /**
     * Test format_type with valid content type.
     */
    public function test_format_type_valid(): void {
        $result = formatter::format_type('program');
        $this->assertEquals(get_string('contenttypeprogram', 'mod_edflex'), $result);
    }

    /**
     * Test format_type with invalid content type.
     */
    public function test_format_type_invalid(): void {
        $result = formatter::format_type('invalid_type');
        $this->assertEquals('invalid_type', $result);
    }

    /**
     * Test format_type with null input.
     */
    public function test_format_type_null(): void {
        $result = formatter::format_type(null);
        $this->assertEquals('', $result);
    }

    /**
     * Test format_difficulty with valid difficulty.
     */
    public function test_format_difficulty_valid(): void {
        $result = formatter::format_difficulty('introductive');
        $this->assertEquals(get_string('difficultyintroductive', 'mod_edflex'), $result);
    }

    /**
     * Test format_difficulty with invalid difficulty.
     */
    public function test_format_difficulty_invalid(): void {
        $result = formatter::format_difficulty('invalid_difficulty');
        $this->assertEquals('invalid_difficulty', $result);
    }

    /**
     * Test format_difficulty with null input.
     */
    public function test_format_difficulty_null(): void {
        $result = formatter::format_difficulty(null);
        $this->assertEquals('', $result);
    }
}
