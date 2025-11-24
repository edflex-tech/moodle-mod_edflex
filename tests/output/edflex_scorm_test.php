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

/**
 * Test for the mod_edflex\output\edflex_scorm class
 *
 * @package     mod_edflex
 * @copyright   2025 Edflex <support@edflex.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_edflex\output;
use advanced_testcase;
use mod_edflex\util\formatter;
use renderer_base;

/**
 * Unit test class for the edflex_scorm class.
 *
 * This class extends the advanced_testcase to provide unit tests for the
 * export_for_template functionality of the edflex_scorm class found within
 * the edflex module. It ensures the data transformation and formatting logic
 * within export_for_template behave as expected for various types of input.
 */
final class edflex_scorm_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * Test export_for_template method with valid SCORM data.
     *
     * @covers \mod_edflex\output\edflex_scorm
     */
    public function test_export_for_template_with_valid_data(): void {
        $edflexscorm = (object)[
            'id' => 1,
            'scormid' => 101,
            'edflexid' => 'content1',
            'name' => 'Test SCORM',
            'language' => 'en',
            'duration' => 'PT1H30M',
            'difficulty' => 'beginner',
            'type' => 'article',
            'author' => 'John Doe',
        ];

        $output = $this->getMockBuilder(renderer_base::class)
            ->disableOriginalConstructor()
            ->getMock();

        $edflexscorm = new edflex_scorm($edflexscorm);
        $result = $edflexscorm->export_for_template($output);

        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('scormid', $result);
        $this->assertArrayHasKey('edflexid', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('language', $result);
        $this->assertArrayHasKey('duration', $result);
        $this->assertArrayHasKey('duration_formatted', $result);
        $this->assertArrayHasKey('difficulty', $result);
        $this->assertArrayHasKey('difficulty_formatted', $result);
        $this->assertArrayHasKey('type_formatted', $result);
        $this->assertArrayHasKey('author', $result);

        // Check the values are correctly set.
        $this->assertEquals(1, $result['id']);
        $this->assertEquals(101, $result['scormid']);
        $this->assertEquals('content1', $result['edflexid']);
        $this->assertEquals('Test SCORM', $result['name']);
        $this->assertEquals('en', $result['language']);
        $this->assertEquals('PT1H30M', $result['duration']);
        $this->assertEquals(formatter::format_duration('PT1H30M'), $result['duration_formatted']);
        $this->assertEquals('beginner', $result['difficulty']);
        $this->assertEquals(formatter::format_difficulty('beginner'), $result['difficulty_formatted']);
        $this->assertEquals(formatter::format_type('article'), $result['type_formatted']);
        $this->assertEquals('John Doe', $result['author']);
    }

    /**
     * Test export_for_template with missing data.
     *
     * @covers \mod_edflex\output\edflex_scorm
     */
    public function test_export_for_template_with_missing_data(): void {
        $edflexscorm = (object)[
            'id' => 2,
            'scormid' => 102,
            'edflexid' => 'content2',
            'name' => null,
            'language' => null,
            'duration' => null,
            'difficulty' => null,
            'type' => null,
            'author' => null,
        ];

        $edflexscorm = new edflex_scorm($edflexscorm);
        $output = $this->getMockBuilder(renderer_base::class)
            ->disableOriginalConstructor()
            ->getMock();

        $result = $edflexscorm->export_for_template($output);

        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('scormid', $result);
        $this->assertArrayHasKey('edflexid', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('language', $result);
        $this->assertArrayHasKey('duration', $result);
        $this->assertArrayHasKey('duration_formatted', $result);
        $this->assertArrayHasKey('difficulty', $result);
        $this->assertArrayHasKey('difficulty_formatted', $result);
        $this->assertArrayHasKey('type_formatted', $result);
        $this->assertArrayHasKey('author', $result);

        // Check the values are correctly set.
        $this->assertEquals(2, $result['id']);
        $this->assertEquals(102, $result['scormid']);
        $this->assertEquals('content2', $result['edflexid']);
        $this->assertEquals('', $result['type_formatted']);
        $this->assertNull($result['name']);
        $this->assertNull($result['language']);
        $this->assertNull($result['duration']);
        $this->assertNull($result['difficulty']);
        $this->assertNull($result['author']);
    }
}
