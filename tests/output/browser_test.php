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
 * Test for the mod_edflex\output\browser class
 *
 * @package     mod_edflex
 * @copyright   2025 Edflex <support@edflex.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_edflex\output;

use advanced_testcase;
use renderer_base;
use stdClass;

/**
 * Unit tests for mod_edflex\output\browser
 *
 * @runTestsInSeparateProcesses
 * @covers \mod_edflex\output\browser
 */
final class browser_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_export_for_template(): void {
        $browser = new browser();
        $output = $this->getMockBuilder(renderer_base::class)
            ->disableOriginalConstructor()
            ->getMock();

        $result = $browser->export_for_template($output);
        $this->assertArrayHasKey('contenttypes', $result);
        $this->assertArrayHasKey('languages', $result);
        $this->assertArrayHasKey('categories', $result);
    }

    public function test_get_languages(): void {
        $languages = browser::get_languages();
        $this->assertNotEmpty($languages);
        foreach ($languages as $language) {
            $this->assertArrayHasKey('value', $language);
            $this->assertArrayHasKey('label', $language);
        }
    }

    public function test_get_categories(): void {
        global $DB;

        $category = new stdClass();
        $category->uuid = 'cat-001';
        $category->catalog_uuid = 'catalog-123';
        $category->title = 'Test Category';
        $category->position = 1;
        $DB->insert_record('edflex_categories', $category);

        $categories = browser::get_categories();
        $this->assertCount(1, $categories);
        $this->assertEquals('Test Category', $categories[0]->label);
        $this->assertEquals('cat-001', $categories[0]->value);
    }

    public function test_export_for_template_no_categories(): void {
        global $DB;

        $DB->delete_records('edflex_categories');

        $browser = new browser();
        $output = $this->getMockBuilder(renderer_base::class)
            ->disableOriginalConstructor()
            ->getMock();

        $result = $browser->export_for_template($output);
        $this->assertArrayHasKey('categories', $result);
        $this->assertEmpty($result['categories']);
    }
}
