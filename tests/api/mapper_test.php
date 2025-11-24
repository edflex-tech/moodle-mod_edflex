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

namespace mod_edflex\api;

use advanced_testcase;
use function PHPUnit\Framework\assertEquals;

/**
 * Unit tests for the mod_edflex mapper class.
 *
 * @package     mod_edflex
 * @copyright   2025 Edflex <support@edflex.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \mod_edflex\api\mapper
 */
final class mapper_test extends advanced_testcase {
    /**
     * Edflex content mapping for import multiple contents.
     */
    public function test_map_contents(): void {
        $edflexcontents = [[
            'id' => '12345',
            'type' => 'activities',
            'attributes' => [
                'title' => 'Test Activity',
                'type' => 'article',
                'url' => 'https://e.test/12345',
                'language' => 'en',
                'difficulty' => 'beginner',
                'duration' => 'PT1H30M',
                'description' => 'This is a test activity.',
                'author' => [
                    'fullName' => 'John Doe',
                ],
            ],
            'links' => [
                'downloadScormZip' => 'https://e.test/downloadablezip54321',
            ],
        ]];

        $mappedcontents = mapper::map_contents($edflexcontents);

        foreach ($edflexcontents as $idx => $edflexcontent) {
            $mappedcontent = $mappedcontents[$idx];
            $this->assertEquals($edflexcontent['id'], $mappedcontent['edflexid']);
            $this->assertEquals($edflexcontent['attributes']['title'], $mappedcontent['name']);
            $this->assertEquals($edflexcontent['attributes']['language'], $mappedcontent['language']);
            $this->assertEquals($edflexcontent['attributes']['difficulty'], $mappedcontent['difficulty']);
            $this->assertEquals($edflexcontent['attributes']['duration'], $mappedcontent['duration']);
            $this->assertEquals($edflexcontent['attributes']['author']['fullName'], $mappedcontent['author']);
            $this->assertEquals($edflexcontent['attributes']['description'], $mappedcontent['intro']);
            $this->assertEquals($edflexcontent['attributes']['url'], $mappedcontent['url']);
            $this->assertEquals($edflexcontent['attributes']['type'], $mappedcontent['type']);
            $this->assertEquals($edflexcontent['links']['downloadScormZip'], $mappedcontent['downloadscormzip']);
        }
    }

    /**
     * Edflex content mapping for import single content.
     */
    public function test_map_content(): void {
        $mappedcontent = mapper::map_content(['id' => '12346', 'attributes' => ['creator' => ['name' => 'John Doe']]]);
        $this->assertEquals('John Doe', $mappedcontent['author']);

        $mappedcontent = mapper::map_content(['id' => '12347', 'attributes' => []]);
        $this->assertEquals('', $mappedcontent['author']);
    }
}
