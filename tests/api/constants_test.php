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

/**
 * Unit tests for the mod_edflex\api\constants class.
 *
 * @package     mod_edflex
 * @copyright   2025 Edflex <support@edflex.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \mod_edflex\api\constants
 */
final class constants_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * Test CONTENT_TYPES constant.
     */
    public function test_content_types(): void {
        $expectedtypes = [
            'program' => 'contenttypeprogram',
            'article' => 'contenttypearticle',
            'video' => 'contenttypevideo',
            'mooc' => 'contenttypecourse',
            'role-play' => 'contenttyperoleplay',
            'interactive' => 'contenttypeinteractive',
            'top-voice' => 'contenttypetopvoice',
            'assessment' => 'contenttypeassessment',
            'podcast' => 'contenttypepodcast',
        ];

        $this->assertEquals($expectedtypes, constants::CONTENT_TYPES);
    }

    /**
     * Test CONTENT_LEVELS constant.
     */
    public function test_content_levels(): void {
        $expectedlevels = [
            'introductive' => 'difficultyintroductive',
            'intermediate' => 'difficultyintermediate',
            'advanced' => 'difficultyadvanced',
        ];

        $this->assertEquals($expectedlevels, constants::CONTENT_LEVELS);
    }

    /**
     * Test CONTENT_LANGUAGES constant.
     */
    public function test_content_languages(): void {
        $expectedlanguages = [
            'de', 'en', 'ar', 'zh', 'ko', 'es', 'fr', 'hu', 'it', 'ja', 'km', 'nl', 'pl', 'pt', 'ru', 'sk', 'vi',
            'el', 'he', 'hi', 'id', 'ro', 'sv', 'tr',
        ];

        $this->assertEquals($expectedlanguages, constants::CONTENT_LANGUAGES);
    }

    /**
     * Test get_content_types_options method.
     */
    public function test_get_content_types_options(): void {
        $options = constants::get_content_types_options();

        $this->assertNotEmpty($options);

        foreach ($options as $option) {
            $this->assertArrayHasKey('value', $option);
            $this->assertArrayHasKey('label', $option);
        }
    }
}
