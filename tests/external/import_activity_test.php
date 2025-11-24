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

namespace mod_edflex\external;

use advanced_testcase;
use context_system;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use mod_edflex\api\client;
use moodle_exception;
use required_capability_exception;

/**
 * Unit tests for the \mod_edflex\external\import_activity class.
 *
 * @package     mod_edflex
 * @copyright   2025 Edflex <support@edflex.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @runTestsInSeparateProcesses
 * @covers \mod_edflex\external\import_activity
 */
final class import_activity_test extends advanced_testcase {
    protected function setUp(): void {
        global $CFG;
        require_once($CFG->libdir . '/externallib.php');

        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * Test import_activity external function
     */
    public function test_import_activity_external_function(): void {
        global $DB;

        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $section = 1;

        $mockclient = $this->getMockBuilder(client::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get_contents_by_ids', 'get_scorm'])
            ->getMock();

        $mockcontents = [
            [
                'id' => 'content1',
                'type' => 'activities',
                'attributes' => [
                    'title' => 'Test Activity 1',
                    'type' => 'article',
                    'url' => 'https://e.test/content1',
                    'language' => 'en',
                    'difficulty' => 'beginner',
                    'duration' => 'PT1H',
                    'description' => 'This is test activity 1.',
                    'author' => [
                        'fullName' => 'John Doe',
                    ],
                ],
                'links' => [
                    'downloadScormZip' => 'https://e.test/downloadscorm1',
                ],
            ],
            [
                'id' => 'content2',
                'type' => 'activities',
                'attributes' => [
                    'title' => 'Test Activity 2',
                    'type' => 'video',
                    'url' => 'https://e.test/content2',
                    'language' => 'fr',
                    'difficulty' => 'intermediate',
                    'duration' => 'PT2H',
                    'description' => 'This is test activity 2.',
                    'author' => [
                        'fullName' => 'Jane Doe',
                    ],
                ],
                'links' => [
                    'downloadScormZip' => 'https://e.test/downloadscorm2',
                ],
            ],
        ];

        $generator = (function () use ($mockcontents) {
            foreach ($mockcontents as $content) {
                yield $content;
            }
        })();

        $mockclient->expects($this->once())
            ->method('get_contents_by_ids')
            ->with(['content1', 'content2'])
            ->willReturn($generator);

        $mockscorm = file_get_contents(realpath(__DIR__ . '/../fixtures/package.zip'));
        $mockclient->method('get_scorm')->willReturn($mockscorm);

        $result = import_activity::execute(
            ['content1', 'content2'],
            $course->id,
            $section,
            $mockclient
        );

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('/course/view.php?id=' . $course->id, $result['course']['url']);
        $this->assertCount(2, $result['activities']);

        foreach ($result['activities'] as $activity) {
            $this->assertArrayHasKey('id', $activity);
            $this->assertArrayHasKey('url', $activity);
            $this->assertStringContainsString('/mod/scorm/view.php', $activity['url']);
        }

        $this->assertEquals(2, $DB->count_records('scorm', ['course' => $course->id]));
        $this->assertEquals(2, $DB->count_records('edflex_scorm'));
    }

    /**
     * Test import_activity with empty content IDs throws exception
     */
    public function test_import_activity_empty_contents_throws_exception(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();

        $mockclient = $this->getMockBuilder(client::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get_contents_by_ids'])
            ->getMock();

        $emptygenerator = (function () {
            return;
            yield;
        })();

        $mockclient->expects($this->once())
            ->method('get_contents_by_ids')
            ->willReturn($emptygenerator);

        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage(get_string('failedtofetchedflexcontents', 'mod_edflex'));

        import_activity::execute(['content1'], $course->id, 0, $mockclient);
    }

    /**
     * Test import_activity without capability throws exception
     */
    public function test_import_activity_without_capability_throws_exception(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $course = $this->getDataGenerator()->create_course();

        $this->expectException(required_capability_exception::class);

        import_activity::execute(['content1'], $course->id, 0);
    }


    /**
     * Test execute_returns method returns correct structure
     */
    public function test_execute_returns(): void {
        $returns = import_activity::execute_returns();

        $this->assertInstanceOf(external_single_structure::class, $returns);

        // Check that the success field is present and is a boolean.
        $keys = $returns->keys;
        $this->assertArrayHasKey('success', $keys);
        $this->assertInstanceOf(external_value::class, $keys['success']);
        $this->assertInstanceOf(external_single_structure::class, $keys['course']);
        $this->assertInstanceOf(external_multiple_structure::class, $keys['activities']);
    }
}
