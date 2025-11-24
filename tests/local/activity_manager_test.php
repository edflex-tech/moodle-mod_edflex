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
 * Test for the activity_manager class
 *
 * @package     mod_edflex
 * @copyright   2025 Edflex <support@edflex.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_edflex\local;

use advanced_testcase;
use context_module;
use mod_edflex\api\client;
use moodle_exception;
use phpunit_util;
use ReflectionClass;

/**
 * Unit tests for mod_edflex\local\activity_manager
 *
 * @runTestsInSeparateProcesses
 * @covers \mod_edflex\local\activity_manager
 */
final class activity_manager_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test for importing content with missing required fields.
     */
    public function test_import_content_missing_required_fields(): void {
        $this->expectException(moodle_exception::class);
        $mockclient = $this->getMockBuilder(client::class)
            ->disableOriginalConstructor()
            ->getMock();
        $activitymanager = new activity_manager($mockclient);
        $activitymanager->import_content([], 1, 0);
    }

    /**
     * Test for importing content with invalid course ID.
     */
    public function test_import_content_invalid_course_id(): void {
        global $DB;

        $invalidcourseid = 1000 + ($DB->get_field('course', 'MAX(id)', []) ?: 0);
        $this->expectException(moodle_exception::class);

        $mockclient = $this->getMockBuilder(client::class)
            ->disableOriginalConstructor()
            ->getMock();

        $activitymanager = new activity_manager($mockclient);
        $activitymanager->import_content([
            'downloadscormzip' => 'https://e.test/scorm.zip',
            'url' => 'https://e.test/12345',
            'name' => 'Test Activity',
            'intro' => 'Test Intro',
            'edflexid' => '12345',
            'language' => 'en',
            'type' => 'article',
            'difficulty' => 'beginner',
            'duration' => 'PT1H',
            'author' => 'John Doe',
        ], $invalidcourseid, 0);
    }

    /**
     * Test for deleting orphaned SCORM records.
     */
    public function test_delete_orphaned_edflex_scorms(): void {
        global $DB;

        $DB->insert_record('edflex_scorm', (object)[
            'edflexid' => '12345',
            'url' => 'https://e.test/12345',
            'scormid' => 9999,
            'name' => 'Test Edflex',
            'language' => 'en',
            'type' => 'article',
            'difficulty' => 'beginner',
            'duration' => 'PT1H',
            'author' => 'John Doe',
            'downloadscormzip' => 'https://e.test/scorm.zip',
        ]);

        $mockclient = $this->getMockBuilder(client::class)
            ->disableOriginalConstructor()
            ->getMock();

        $activitymanager = new activity_manager($mockclient);
        $deletedcount = $activitymanager->delete_orphaned_edflex_scorms();
        $this->assertEquals(1, $deletedcount);
    }

    /**
     * Test for importing multiple contents.
     */
    public function test_import_multiple_contents(): void {
        $this->setAdminUser();
        $mockclient = $this->getMockBuilder(client::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get_scorm'])
            ->getMock();

        $mockscorm = file_get_contents(realpath(__DIR__ . '/../fixtures/package.zip'));
        $mockclient->method('get_scorm')->willReturn($mockscorm);

        $activitymanager = new activity_manager($mockclient);
        $results = $activitymanager->import_contents([
            [
                'downloadscormzip' => 'https://e.test/scorm1.zip',
                'url' => 'https://e.test/12345',
                'name' => 'Test Activity 1',
                'intro' => 'Test Intro 1',
                'edflexid' => '12345',
                'language' => 'en',
                'type' => 'article',
                'difficulty' => 'beginner',
                'duration' => 'PT1H',
                'author' => 'John Doe',
            ],
            [
                'downloadscormzip' => 'https://e.test/scorm2.zip',
                'url' => 'https://e.test/12345',
                'name' => 'Test Activity 2',
                'intro' => 'Test Intro 2',
                'edflexid' => '67890',
                'language' => 'en',
                'type' => 'article',
                'difficulty' => 'intermediate',
                'duration' => 'PT2H',
                'author' => 'Jane Doe',
            ],
        ], 1, 0);

        $this->assertCount(2, $results);
    }

    /**
     * Test get_modified_records with no changes
     */
    public function test_get_modified_records_no_changes(): void {
        $edflexscorm = (object)[
            'id' => 1,
            'name' => 'Test Activity',
            'language' => 'en',
            'difficulty' => 'beginner',
            'duration' => 'PT1H',
            'author' => 'John Doe',
            'type' => 'article',
            'url' => 'https://example.com/content',
        ];

        $scorm = (object)[
            'id' => 1,
            'name' => 'Test Activity',
            'intro' => 'Test introduction',
        ];

        $content = [
            'name' => 'Test Activity',
            'intro' => 'Test introduction',
            'language' => 'en',
            'difficulty' => 'beginner',
            'duration' => 'PT1H',
            'author' => 'John Doe',
            'type' => 'article',
            'url' => 'https://example.com/content',
            'downloadscormzip' => 'https://example.com/scorm.zip',
        ];

        $mockclient = $this->getMockBuilder(client::class)
            ->disableOriginalConstructor()
            ->getMock();

        $activitymanager = new activity_manager($mockclient);

        $reflection = new ReflectionClass($activitymanager);
        $method = $reflection->getMethod('get_modified_records');
        $method->setAccessible(true);

        [$edflexscormupd, $scormupd, $newscormbinary] = $method->invoke(
            $activitymanager,
            $edflexscorm,
            $scorm,
            $content
        );

        $this->assertNull($edflexscormupd);
        $this->assertNull($scormupd);
        $this->assertNull($newscormbinary);
    }

    /**
     * Test get_modified_records with SCORM changes only
     */
    public function test_get_modified_records_scorm_changes(): void {
        $edflexscorm = (object)[
            'id' => 1,
            'name' => 'Test Activity',
            'language' => 'en',
            'difficulty' => 'beginner',
            'duration' => 'PT1H',
            'author' => 'John Doe',
            'type' => 'article',
            'url' => 'https://example.com/content',
        ];

        $scorm = (object)[
            'id' => 1,
            'name' => 'Old Name',
            'intro' => 'Old introduction',
        ];

        $content = [
            'name' => 'Test Activity',
            'intro' => 'New introduction',
            'language' => 'en',
            'difficulty' => 'beginner',
            'duration' => 'PT1H',
            'author' => 'John Doe',
            'type' => 'article',
            'url' => 'https://example.com/content',
            'downloadscormzip' => 'https://example.com/scorm.zip',
        ];

        $mockclient = $this->getMockBuilder(client::class)
            ->disableOriginalConstructor()
            ->getMock();

        $activitymanager = new activity_manager($mockclient);

        $reflection = new ReflectionClass($activitymanager);
        $method = $reflection->getMethod('get_modified_records');
        $method->setAccessible(true);

        [$edflexscormupd, $scormupd, $newscormbinary] = $method->invoke(
            $activitymanager,
            $edflexscorm,
            $scorm,
            $content
        );

        $this->assertNull($edflexscormupd);
        $this->assertNotNull($scormupd);
        $this->assertEquals(1, $scormupd->id);
        $this->assertEquals('Test Activity', $scormupd->name);
        $this->assertEquals('New introduction', $scormupd->intro);
        $this->assertNull($newscormbinary);
    }

    /**
     * Test get_modified_records with edflex_scorm changes only
     */
    public function test_get_modified_records_edflex_scorm_changes(): void {
        $edflexscorm = (object)[
            'id' => 1,
            'name' => 'Test Activity',
            'language' => 'en',
            'difficulty' => 'beginner',
            'duration' => 'PT1H',
            'author' => 'John Doe',
            'type' => 'article',
            'url' => 'https://example.com/content',
        ];

        $scorm = (object)[
            'id' => 1,
            'name' => 'Test Activity',
            'intro' => 'Test introduction',
        ];

        $content = [
            'name' => 'Test Activity',
            'intro' => 'Test introduction',
            'language' => 'fr',
            'difficulty' => 'intermediate',
            'duration' => 'PT2H',
            'author' => 'Jane Doe',
            'type' => 'video',
            'url' => 'https://example.com/content',
            'downloadscormzip' => 'https://example.com/scorm.zip',
        ];

        $mockclient = $this->getMockBuilder(client::class)
            ->disableOriginalConstructor()
            ->getMock();

        $activitymanager = new activity_manager($mockclient);

        $reflection = new ReflectionClass($activitymanager);
        $method = $reflection->getMethod('get_modified_records');
        $method->setAccessible(true);

        [$edflexscormupd, $scormupd, $newscormbinary] = $method->invoke(
            $activitymanager,
            $edflexscorm,
            $scorm,
            $content
        );

        $this->assertNotNull($edflexscormupd);
        $this->assertEquals(1, $edflexscormupd->id);
        $this->assertEquals('fr', $edflexscormupd->language);
        $this->assertEquals('intermediate', $edflexscormupd->difficulty);
        $this->assertEquals('PT2H', $edflexscormupd->duration);
        $this->assertEquals('Jane Doe', $edflexscormupd->author);
        $this->assertEquals('video', $edflexscormupd->type);
        $this->assertNull($scormupd);
    }

    /**
     * Test get_modified_records triggers SCORM download when name changes
     *
     * @dataProvider provide_get_modified_records_download_triggers_data
     */
    public function test_get_modified_records_download_triggers(
        array $originaldata,
        array $newdata,
        bool $shoulddownload
    ): void {
        $edflexscorm = (object)array_merge([
            'id' => 1,
            'language' => 'en',
            'difficulty' => 'beginner',
            'duration' => 'PT1H',
            'author' => 'John Doe',
        ], $originaldata);

        $scorm = (object)[
            'id' => 1,
            'name' => $originaldata['name'],
            'intro' => 'Test introduction',
        ];

        $content = array_merge([
            'intro' => 'Test introduction',
            'language' => 'en',
            'difficulty' => 'beginner',
            'duration' => 'PT1H',
            'author' => 'John Doe',
            'downloadscormzip' => 'https://example.com/scorm.zip',
        ], $newdata);

        $mockclient = $this->getMockBuilder(client::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get_scorm'])
            ->getMock();

        if ($shoulddownload) {
            $mockclient->expects($this->once())
                ->method('get_scorm')
                ->with('https://example.com/scorm.zip')
                ->willReturn('SCORM binary content');
        } else {
            $mockclient->expects($this->never())
                ->method('get_scorm');
        }

        $activitymanager = new activity_manager($mockclient);

        $reflection = new ReflectionClass($activitymanager);
        $method = $reflection->getMethod('get_modified_records');
        $method->setAccessible(true);

        [$edflexscormupd, $scormupd, $newscormbinary] = $method->invoke(
            $activitymanager,
            $edflexscorm,
            $scorm,
            $content
        );

        if ($shoulddownload) {
            $this->assertEquals('SCORM binary content', $newscormbinary);
        } else {
            $this->assertNull($newscormbinary);
        }
    }

    /**
     * Test get_modified_records with all fields changed
     */
    public function test_get_modified_records_all_changes(): void {
        $edflexscorm = (object)[
            'id' => 1,
            'name' => 'Old Activity',
            'language' => 'en',
            'difficulty' => 'beginner',
            'duration' => 'PT1H',
            'author' => 'John Doe',
            'type' => 'article',
            'url' => 'https://example.com/old',
        ];

        $scorm = (object)[
            'id' => 1,
            'name' => 'Old Activity',
            'intro' => 'Old introduction',
        ];

        $content = [
            'name' => 'New Activity',
            'intro' => 'New introduction',
            'language' => 'fr',
            'difficulty' => 'advanced',
            'duration' => 'PT3H',
            'author' => 'Jane Doe',
            'type' => 'video',
            'url' => 'https://example.com/new',
            'downloadscormzip' => 'https://example.com/scorm.zip',
        ];

        $mockclient = $this->getMockBuilder(client::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get_scorm'])
            ->getMock();

        $mockclient->expects($this->once())
            ->method('get_scorm')
            ->with('https://example.com/scorm.zip')
            ->willReturn('New SCORM content');

        $activitymanager = new activity_manager($mockclient);

        $reflection = new ReflectionClass($activitymanager);
        $method = $reflection->getMethod('get_modified_records');
        $method->setAccessible(true);

        [$edflexscormupd, $scormupd, $newscormbinary] = $method->invoke(
            $activitymanager,
            $edflexscorm,
            $scorm,
            $content
        );

        $this->assertNotNull($scormupd);
        $this->assertEquals(1, $scormupd->id);
        $this->assertEquals('New Activity', $scormupd->name);
        $this->assertEquals('New introduction', $scormupd->intro);

        $this->assertNotNull($edflexscormupd);
        $this->assertEquals(1, $edflexscormupd->id);
        $this->assertEquals('New Activity', $edflexscormupd->name);
        $this->assertEquals('fr', $edflexscormupd->language);
        $this->assertEquals('advanced', $edflexscormupd->difficulty);
        $this->assertEquals('PT3H', $edflexscormupd->duration);
        $this->assertEquals('Jane Doe', $edflexscormupd->author);
        $this->assertEquals('video', $edflexscormupd->type);
        $this->assertEquals('https://example.com/new', $edflexscormupd->url);

        $this->assertEquals('New SCORM content', $newscormbinary);
    }

    /**
     * Test get_edflex_records_by_contentids with empty array
     */
    public function test_get_edflex_records_by_contentids_empty_array(): void {
        $mockclient = $this->getMockBuilder(client::class)
            ->disableOriginalConstructor()
            ->getMock();

        $activitymanager = new activity_manager($mockclient);

        $generator = $activitymanager->get_edflex_records_by_contentids([]);

        $results = [];
        foreach ($generator as $record) {
            $results[] = $record;
        }

        $this->assertCount(0, $results);
    }

    /**
     * Test get_edflex_records_by_contentids with small array
     */
    public function test_get_edflex_records_by_contentids_small_array(): void {
        global $DB;

        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();

        $scorm1 = $this->getDataGenerator()->create_module('scorm', ['course' => $course->id]);
        $scorm2 = $this->getDataGenerator()->create_module('scorm', ['course' => $course->id]);

        $edflexscorm1 = (object)[
            'edflexid' => 'content1',
            'scormid' => $scorm1->id,
            'name' => 'Content 1',
            'url' => 'https://example.com/content1',
            'language' => 'en',
            'type' => 'article',
            'difficulty' => 'beginner',
            'duration' => 'PT1H',
            'author' => 'Author 1',
            'downloadscormzip' => 'https://example.com/scorm1.zip',
            'lastsync' => time(),
        ];
        $edflexscorm1->id = $DB->insert_record('edflex_scorm', $edflexscorm1);

        $edflexscorm2 = (object)[
            'edflexid' => 'content2',
            'scormid' => $scorm2->id,
            'name' => 'Content 2',
            'url' => 'https://example.com/content2',
            'language' => 'fr',
            'type' => 'video',
            'difficulty' => 'intermediate',
            'duration' => 'PT2H',
            'author' => 'Author 2',
            'downloadscormzip' => 'https://example.com/scorm2.zip',
            'lastsync' => time(),
        ];
        $edflexscorm2->id = $DB->insert_record('edflex_scorm', $edflexscorm2);

        $mockclient = $this->getMockBuilder(client::class)
            ->disableOriginalConstructor()
            ->getMock();

        $activitymanager = new activity_manager($mockclient);

        $generator = $activitymanager->get_edflex_records_by_contentids(['content1', 'content2']);

        $results = [];
        foreach ($generator as [$edflexscorm, $scorm]) {
            $results[] = [$edflexscorm, $scorm];
        }

        $this->assertCount(2, $results);

        $this->assertEquals('content1', $results[0][0]->edflexid);
        $this->assertEquals($scorm1->id, $results[0][0]->scormid);
        $this->assertNotNull($results[0][1]);
        $this->assertEquals($scorm1->id, $results[0][1]->id);
        $this->assertTrue(property_exists($results[0][1], 'cmid'));

        $this->assertEquals('content2', $results[1][0]->edflexid);
        $this->assertEquals($scorm2->id, $results[1][0]->scormid);
        $this->assertNotNull($results[1][1]);
        $this->assertEquals($scorm2->id, $results[1][1]->id);
        $this->assertTrue(property_exists($results[1][1], 'cmid'));
    }

    /**
     * Test get_edflex_records_by_contentids with missing SCORM records
     */
    public function test_get_edflex_records_by_contentids_missing_scorm(): void {
        global $DB;

        $edflexscorm = (object)[
            'edflexid' => 'orphaned_content',
            'scormid' => 99999,
            'name' => 'Orphaned Content',
            'url' => 'https://example.com/orphaned',
            'language' => 'en',
            'type' => 'article',
            'difficulty' => 'beginner',
            'duration' => 'PT1H',
            'author' => 'Author',
            'downloadscormzip' => 'https://example.com/orphaned.zip',
            'lastsync' => time(),
        ];
        $edflexscorm->id = $DB->insert_record('edflex_scorm', $edflexscorm);

        $mockclient = $this->getMockBuilder(client::class)
            ->disableOriginalConstructor()
            ->getMock();

        $activitymanager = new activity_manager($mockclient);

        $generator = $activitymanager->get_edflex_records_by_contentids(['orphaned_content']);

        $results = [];
        foreach ($generator as [$edflexscorm, $scorm]) {
            $results[] = [$edflexscorm, $scorm];
        }

        $this->assertCount(1, $results);
        $this->assertEquals('orphaned_content', $results[0][0]->edflexid);
        $this->assertNull($results[0][1]);
    }

    /**
     * Test get_edflex_records_by_contentids with pagination (more than 200 records)
     */
    public function test_get_edflex_records_by_contentids_pagination(): void {
        global $DB;

        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();

        $contentids = [];

        for ($i = 1; $i <= 5; $i++) {
            $scorm = $this->getDataGenerator()->create_module('scorm', ['course' => $course->id]);

            $edflexscorm = (object)[
                'edflexid' => 'content' . $i,
                'scormid' => $scorm->id,
                'name' => 'Content ' . $i,
                'url' => 'https://example.com/content' . $i,
                'language' => 'en',
                'type' => 'article',
                'difficulty' => 'beginner',
                'duration' => 'PT1H',
                'author' => 'Author ' . $i,
                'downloadscormzip' => 'https://example.com/scorm' . $i . '.zip',
                'lastsync' => time(),
            ];
            $DB->insert_record('edflex_scorm', $edflexscorm);
            $contentids[] = 'content' . $i;
        }

        $mockclient = $this->getMockBuilder(client::class)
            ->disableOriginalConstructor()
            ->getMock();

        $activitymanager = new activity_manager($mockclient);

        $generator = $activitymanager->get_edflex_records_by_contentids($contentids, 2);

        $results = [];
        foreach ($generator as [$edflexscorm, $scorm]) {
            $results[] = [$edflexscorm, $scorm];
        }

        $this->assertCount(5, $results);
        $this->assertEquals('content1', $results[0][0]->edflexid);
        $this->assertEquals('content5', $results[4][0]->edflexid);
    }

    /**
     * Test get_edflex_records_by_contentids with non-existent content IDs
     */
    public function test_get_edflex_records_by_contentids_nonexistent(): void {
        $mockclient = $this->getMockBuilder(client::class)
            ->disableOriginalConstructor()
            ->getMock();

        $activitymanager = new activity_manager($mockclient);

        $generator = $activitymanager->get_edflex_records_by_contentids(['nonexistent1', 'nonexistent2']);

        $results = [];
        foreach ($generator as $record) {
            $results[] = $record;
        }

        $this->assertCount(0, $results);
    }

    /**
     * Test get_edflex_records_by_contentids with mixed existing and non-existing IDs
     */
    public function test_get_edflex_records_by_contentids_mixed(): void {
        global $DB;

        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();

        $scorm = $this->getDataGenerator()->create_module('scorm', ['course' => $course->id]);

        $edflexscorm = (object)[
            'edflexid' => 'existing_content',
            'scormid' => $scorm->id,
            'name' => 'Existing Content',
            'url' => 'https://example.com/existing',
            'language' => 'en',
            'type' => 'article',
            'difficulty' => 'beginner',
            'duration' => 'PT1H',
            'author' => 'Author',
            'downloadscormzip' => 'https://example.com/existing.zip',
            'lastsync' => time(),
        ];
        $DB->insert_record('edflex_scorm', $edflexscorm);

        $mockclient = $this->getMockBuilder(client::class)
            ->disableOriginalConstructor()
            ->getMock();

        $activitymanager = new activity_manager($mockclient);

        $generator = $activitymanager->get_edflex_records_by_contentids([
            'existing_content',
            'nonexistent1',
            'nonexistent2',
        ]);

        $results = [];
        foreach ($generator as [$edflexscorm, $scorm]) {
            $results[] = [$edflexscorm, $scorm];
        }

        $this->assertCount(1, $results);
        $this->assertEquals('existing_content', $results[0][0]->edflexid);
    }

    /**
     * Test get_edflex_records_by_contentids returns correct SCORM with cmid
     */
    public function test_get_edflex_records_by_contentids_includes_cmid(): void {
        global $DB;

        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();

        $scorm = $this->getDataGenerator()->create_module('scorm', ['course' => $course->id]);

        $cm = get_coursemodule_from_instance('scorm', $scorm->id, $course->id);

        $edflexscorm = (object)[
            'edflexid' => 'test_content',
            'scormid' => $scorm->id,
            'name' => 'Test Content',
            'url' => 'https://example.com/test',
            'language' => 'en',
            'type' => 'article',
            'difficulty' => 'beginner',
            'duration' => 'PT1H',
            'author' => 'Author',
            'downloadscormzip' => 'https://example.com/test.zip',
            'lastsync' => time(),
        ];
        $DB->insert_record('edflex_scorm', $edflexscorm);

        $mockclient = $this->getMockBuilder(client::class)
            ->disableOriginalConstructor()
            ->getMock();
        $activitymanager = new activity_manager($mockclient);

        $generator = $activitymanager->get_edflex_records_by_contentids(['test_content']);

        $results = [];
        foreach ($generator as [$edflexscorm, $scorm]) {
            $results[] = [$edflexscorm, $scorm];
        }

        $this->assertCount(1, $results);
        $this->assertNotNull($results[0][1]);
        $this->assertEquals($cm->id, $results[0][1]->cmid);
        $this->assertEquals($scorm->id, $results[0][1]->id);
    }

    /**
     * Test get_edflex_records_by_contentids with duplicate content IDs
     */
    public function test_get_edflex_records_by_contentids_duplicates(): void {
        global $DB;

        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();

        $scorm = $this->getDataGenerator()->create_module('scorm', ['course' => $course->id]);

        $edflexscorm = (object)[
            'edflexid' => 'content1',
            'scormid' => $scorm->id,
            'name' => 'Content 1',
            'url' => 'https://example.com/content1',
            'language' => 'en',
            'type' => 'article',
            'difficulty' => 'beginner',
            'duration' => 'PT1H',
            'author' => 'Author',
            'downloadscormzip' => 'https://example.com/scorm1.zip',
            'lastsync' => time(),
        ];
        $DB->insert_record('edflex_scorm', $edflexscorm);

        $mockclient = $this->getMockBuilder(client::class)
            ->disableOriginalConstructor()
            ->getMock();
        $activitymanager = new activity_manager($mockclient);

        $generator = $activitymanager->get_edflex_records_by_contentids(['content1', 'content1', 'content1']);

        $results = [];
        foreach ($generator as [$edflexscorm, $scorm]) {
            $results[] = [$edflexscorm, $scorm];
        }

        $this->assertCount(1, $results);
        $this->assertEquals('content1', $results[0][0]->edflexid);
    }

    /**
     * Test store_scorm_package with valid SCORM binary
     */
    public function test_store_scorm_package_valid_binary(): void {
        global $DB;

        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $scorm = $this->getDataGenerator()->create_module('scorm', ['course' => $course->id]);

        $cm = get_coursemodule_from_instance('scorm', $scorm->id, $course->id);
        $scorm->cmid = $cm->id;

        $scormbinary = file_get_contents(realpath(__DIR__ . '/../fixtures/package.zip'));

        $mockclient = $this->getMockBuilder(client::class)
            ->disableOriginalConstructor()
            ->getMock();

        $activitymanager = new activity_manager($mockclient);

        $activitymanager->store_scorm_package($scorm, $scormbinary);

        $context = context_module::instance($cm->id);
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'mod_scorm', 'package', 0, 'sortorder', false);

        $this->assertCount(1, $files);

        $file = reset($files);
        $this->assertEquals('package.zip', $file->get_filename());
        $this->assertEquals('/', $file->get_filepath());
        $this->assertEquals($scormbinary, $file->get_content());

        $updatedscorm = $DB->get_record('scorm', ['id' => $scorm->id]);
        $this->assertEquals('package.zip', $updatedscorm->reference);
    }

    /**
     * Test store_scorm_package replaces existing package
     */
    public function test_store_scorm_package_replaces_existing(): void {
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $scorm = $this->getDataGenerator()->create_module('scorm', ['course' => $course->id]);

        $cm = get_coursemodule_from_instance('scorm', $scorm->id, $course->id);
        $scorm->cmid = $cm->id;

        $context = context_module::instance($cm->id);
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'mod_scorm', 'package', 0);

        $filerecord = [
            'contextid' => $context->id,
            'component' => 'mod_scorm',
            'filearea'  => 'package',
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => 'old_package.zip',
        ];

        $oldcontent = 'Old SCORM content';
        $fs->create_file_from_string($filerecord, $oldcontent);

        $oldfiles = $fs->get_area_files($context->id, 'mod_scorm', 'package', 0, 'sortorder', false);
        $this->assertCount(1, $oldfiles);

        $newbinary = file_get_contents(realpath(__DIR__ . '/../fixtures/package.zip'));

        $mockclient = $this->getMockBuilder(client::class)
            ->disableOriginalConstructor()
            ->getMock();

        $activitymanager = new activity_manager($mockclient);
        $activitymanager->store_scorm_package($scorm, $newbinary);

        $newfiles = $fs->get_area_files($context->id, 'mod_scorm', 'package', 0, 'sortorder', false);

        $this->assertCount(1, $newfiles);

        $file = reset($newfiles);
        $this->assertEquals('package.zip', $file->get_filename());
        $this->assertNotEquals($oldcontent, $file->get_content());
        $this->assertEquals($newbinary, $file->get_content());
    }

    /**
     * Test store_scorm_package with coursemodule property instead of cmid
     */
    public function test_store_scorm_package_with_coursemodule_property(): void {
        global $DB;

        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $scorm = $this->getDataGenerator()->create_module('scorm', ['course' => $course->id]);

        $cm = get_coursemodule_from_instance('scorm', $scorm->id, $course->id);

        $scorm->coursemodule = $cm->id;
        unset($scorm->cmid);

        $scormbinary = file_get_contents(realpath(__DIR__ . '/../fixtures/package.zip'));

        $mockclient = $this->getMockBuilder(client::class)
            ->disableOriginalConstructor()
            ->getMock();

        $activitymanager = new activity_manager($mockclient);

        $activitymanager->store_scorm_package($scorm, $scormbinary);

        $context = context_module::instance($cm->id);
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'mod_scorm', 'package', 0, 'sortorder', false);

        $this->assertCount(1, $files);

        $file = reset($files);
        $this->assertEquals('package.zip', $file->get_filename());
    }

    /**
     * Test update_imported_activities_from_contents with large batch
     */
    public function test_update_imported_activities_from_contents_large_batch(): void {
        global $DB;

        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();

        $contents = [];
        $edflexscormids = [];

        for ($i = 1; $i <= 5; $i++) {
            $scorm = $this->getDataGenerator()->create_module('scorm', [
                'course' => $course->id,
                'name' => 'Activity ' . $i,
                'intro' => 'Intro ' . $i,
            ]);

            $edflexscorm = (object)[
                'edflexid' => 'content' . $i,
                'scormid' => $scorm->id,
                'name' => 'Activity ' . $i,
                'url' => 'https://example.com/content' . $i,
                'language' => 'en',
                'type' => 'article',
                'difficulty' => 'beginner',
                'duration' => 'PT1H',
                'author' => 'Author ' . $i,
                'downloadscormzip' => 'https://example.com/scorm' . $i . '.zip',
                'lastsync' => time() - 3600,
            ];
            $edflexscorm->id = $DB->insert_record('edflex_scorm', $edflexscorm);
            $edflexscormids[] = $edflexscorm->id;

            $contents['content' . $i] = [
                'edflexid' => 'content' . $i,
                'name' => 'Updated Activity ' . $i,
                'intro' => 'Updated Intro ' . $i,
                'url' => 'https://example.com/content' . $i,
                'language' => 'fr',
                'type' => 'article',
                'difficulty' => 'intermediate',
                'duration' => 'PT2H',
                'author' => 'Updated Author ' . $i,
                'downloadscormzip' => 'https://example.com/scorm' . $i . '.zip',
            ];
        }

        $mockclient = $this->getMockBuilder(client::class)
            ->disableOriginalConstructor()
            ->getMock();

        $activitymanager = new activity_manager($mockclient);
        $activitymanager->update_imported_activities_from_contents($contents, 2);

        $updatededflex = $DB->get_record('edflex_scorm', ['id' => $edflexscormids[0]]);
        $this->assertEquals('Updated Activity 1', $updatededflex->name);
        $this->assertEquals('fr', $updatededflex->language);

        $updatededflex = $DB->get_record('edflex_scorm', ['id' => $edflexscormids[4]]);
        $this->assertEquals('Updated Activity 5', $updatededflex->name);
        $this->assertEquals('fr', $updatededflex->language);
    }

    /**
     * Test get_edflexids_in_the_course method
     */
    public function test_get_edflexids_in_the_course(): void {
        global $DB;

        $this->setAdminUser();

        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();

        $scorm1 = $this->getDataGenerator()->create_module('scorm', ['course' => $course1->id]);
        $scorm2 = $this->getDataGenerator()->create_module('scorm', ['course' => $course1->id]);

        $scorm3 = $this->getDataGenerator()->create_module('scorm', ['course' => $course2->id]);

        $edflexscorm1 = (object)[
            'edflexid' => 'content1',
            'scormid' => $scorm1->id,
            'name' => 'Content 1',
            'url' => 'https://example.com/content1',
            'language' => 'en',
            'type' => 'article',
            'difficulty' => 'beginner',
            'duration' => 'PT1H',
            'author' => 'Author 1',
            'downloadscormzip' => 'https://example.com/scorm1.zip',
            'lastsync' => time(),
        ];
        $DB->insert_record('edflex_scorm', $edflexscorm1);

        $edflexscorm2 = (object)[
            'edflexid' => 'content2',
            'scormid' => $scorm2->id,
            'name' => 'Content 2',
            'url' => 'https://example.com/content2',
            'language' => 'fr',
            'type' => 'video',
            'difficulty' => 'intermediate',
            'duration' => 'PT2H',
            'author' => 'Author 2',
            'downloadscormzip' => 'https://example.com/scorm2.zip',
            'lastsync' => time(),
        ];
        $DB->insert_record('edflex_scorm', $edflexscorm2);

        $edflexscorm3 = (object)[
            'edflexid' => 'content3',
            'scormid' => $scorm3->id,
            'name' => 'Content 3',
            'url' => 'https://example.com/content3',
            'language' => 'es',
            'type' => 'podcast',
            'difficulty' => 'advanced',
            'duration' => 'PT3H',
            'author' => 'Author 3',
            'downloadscormzip' => 'https://example.com/scorm3.zip',
            'lastsync' => time(),
        ];
        $DB->insert_record('edflex_scorm', $edflexscorm3);

        $mockclient = $this->getMockBuilder(client::class)
            ->disableOriginalConstructor()
            ->getMock();

        $activitymanager = new activity_manager($mockclient);

        $edflexids = $activitymanager->get_edflexids_in_the_course($course1->id);

        $this->assertCount(2, $edflexids);
        $this->assertContains('content1', $edflexids);
        $this->assertContains('content2', $edflexids);
        $this->assertNotContains('content3', $edflexids);

        $edflexids = $activitymanager->get_edflexids_in_the_course($course2->id);

        $this->assertCount(1, $edflexids);
        $this->assertContains('content3', $edflexids);
        $this->assertNotContains('content1', $edflexids);
        $this->assertNotContains('content2', $edflexids);

        $edflexids = $activitymanager->get_edflexids_in_the_course(99999);
        $this->assertCount(0, $edflexids);
    }

    /**
     * Test get_edflexids_in_the_course with deleted course modules
     */
    public function test_get_edflexids_in_the_course_with_deleted_modules(): void {
        global $DB;

        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();

        $scorm1 = $this->getDataGenerator()->create_module('scorm', ['course' => $course->id]);
        $scorm2 = $this->getDataGenerator()->create_module('scorm', ['course' => $course->id]);

        $cm1 = get_coursemodule_from_instance('scorm', $scorm1->id, $course->id);
        $cm2 = get_coursemodule_from_instance('scorm', $scorm2->id, $course->id);

        $edflexscorm1 = (object)[
            'edflexid' => 'content1',
            'scormid' => $scorm1->id,
            'name' => 'Content 1',
            'url' => 'https://example.com/content1',
            'language' => 'en',
            'type' => 'article',
            'difficulty' => 'beginner',
            'duration' => 'PT1H',
            'author' => 'Author 1',
            'downloadscormzip' => 'https://example.com/scorm1.zip',
            'lastsync' => time(),
        ];
        $DB->insert_record('edflex_scorm', $edflexscorm1);

        $edflexscorm2 = (object)[
            'edflexid' => 'content2',
            'scormid' => $scorm2->id,
            'name' => 'Content 2',
            'url' => 'https://example.com/content2',
            'language' => 'fr',
            'type' => 'video',
            'difficulty' => 'intermediate',
            'duration' => 'PT2H',
            'author' => 'Author 2',
            'downloadscormzip' => 'https://example.com/scorm2.zip',
            'lastsync' => time(),
        ];
        $DB->insert_record('edflex_scorm', $edflexscorm2);

        $DB->set_field('course_modules', 'deletioninprogress', 1, ['id' => $cm1->id]);

        $mockclient = $this->getMockBuilder(client::class)
            ->disableOriginalConstructor()
            ->getMock();

        $activitymanager = new activity_manager($mockclient);

        $edflexids = $activitymanager->get_edflexids_in_the_course($course->id);

        $this->assertCount(1, $edflexids);
        $this->assertContains('content2', $edflexids);
        $this->assertNotContains('content1', $edflexids);
    }

    /**
     * Test get_edflexids_in_the_course with orphaned edflex_scorm records
     */
    public function test_get_edflexids_in_the_course_with_orphaned_records(): void {
        global $DB;

        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();

        $scorm = $this->getDataGenerator()->create_module('scorm', ['course' => $course->id]);

        $edflexscorm1 = (object)[
            'edflexid' => 'content1',
            'scormid' => $scorm->id,
            'name' => 'Content 1',
            'url' => 'https://example.com/content1',
            'language' => 'en',
            'type' => 'article',
            'difficulty' => 'beginner',
            'duration' => 'PT1H',
            'author' => 'Author 1',
            'downloadscormzip' => 'https://example.com/scorm1.zip',
            'lastsync' => time(),
        ];
        $DB->insert_record('edflex_scorm', $edflexscorm1);

        $edflexscorm2 = (object)[
            'edflexid' => 'content2',
            'scormid' => 99999,
            'name' => 'Content 2',
            'url' => 'https://example.com/content2',
            'language' => 'fr',
            'type' => 'video',
            'difficulty' => 'intermediate',
            'duration' => 'PT2H',
            'author' => 'Author 2',
            'downloadscormzip' => 'https://example.com/scorm2.zip',
            'lastsync' => time(),
        ];
        $DB->insert_record('edflex_scorm', $edflexscorm2);

        $mockclient = $this->getMockBuilder(client::class)
            ->disableOriginalConstructor()
            ->getMock();

        $activitymanager = new activity_manager($mockclient);

        $edflexids = $activitymanager->get_edflexids_in_the_course($course->id);

        $this->assertCount(1, $edflexids);
        $this->assertContains('content1', $edflexids);
        $this->assertNotContains('content2', $edflexids);
    }

    /**
     * Test delete_orphaned_edflex_scorms returns 0 when no orphaned records exist
     */
    public function test_delete_orphaned_edflex_scorms_returns_zero_when_no_orphans(): void {
        global $DB;

        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $scorm = $this->getDataGenerator()->create_module('scorm', ['course' => $course->id]);

        $DB->insert_record('edflex_scorm', (object)[
            'edflexid' => '12345',
            'scormid' => $scorm->id,
            'name' => 'Valid Record',
            'url' => 'https://e.test/12345',
            'language' => 'en',
            'type' => 'article',
            'difficulty' => 'beginner',
            'duration' => 'PT1H',
            'author' => 'John Doe',
            'downloadscormzip' => 'https://e.test/scorm.zip',
            'lastsync' => time(),
        ]);

        $mockclient = $this->getMockBuilder(client::class)
            ->disableOriginalConstructor()
            ->getMock();

        $activitymanager = new activity_manager($mockclient);

        $this->assertEquals(0, $activitymanager->delete_orphaned_edflex_scorms());

        $this->assertEquals(1, $DB->count_records('edflex_scorm'));
    }

    /**
     * Test get_outdated_edflex_contentids_in_chunks returns content IDs in chunks
     */
    public function test_get_outdated_edflex_contentids_in_chunks(): void {
        global $DB;

        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $currenttime = time();

        for ($i = 1; $i <= 5; $i++) {
            $scorm = $this->getDataGenerator()->create_module('scorm', ['course' => $course->id]);
            $DB->insert_record('edflex_scorm', (object)[
                'edflexid' => 'content' . $i,
                'scormid' => $scorm->id,
                'name' => 'Content ' . $i,
                'url' => 'https://e.test/content' . $i,
                'language' => 'en',
                'type' => 'article',
                'difficulty' => 'beginner',
                'duration' => 'PT1H',
                'author' => 'Author ' . $i,
                'downloadscormzip' => 'https://e.test/scorm' . $i . '.zip',
                'lastsync' => $currenttime - 7200, // 2 hours ago
            ]);
        }

        $scorm = $this->getDataGenerator()->create_module('scorm', ['course' => $course->id]);
        $DB->insert_record('edflex_scorm', (object)[
            'edflexid' => 'recent_content',
            'scormid' => $scorm->id,
            'name' => 'Recent Content',
            'url' => 'https://e.test/recent',
            'language' => 'en',
            'type' => 'article',
            'difficulty' => 'beginner',
            'duration' => 'PT1H',
            'author' => 'Recent Author',
            'downloadscormzip' => 'https://e.test/recent.zip',
            'lastsync' => $currenttime + 3600,
        ]);

        $mockclient = $this->getMockBuilder(client::class)
            ->disableOriginalConstructor()
            ->getMock();

        $activitymanager = new activity_manager($mockclient);

        $chunks = [];
        foreach ($activitymanager->get_outdated_edflex_contentids_in_chunks($currenttime, null, 2) as $chunk) {
            $chunks[] = $chunk;
        }

        $this->assertCount(3, $chunks);
        $this->assertCount(2, $chunks[0]);
        $this->assertCount(2, $chunks[1]);
        $this->assertCount(1, $chunks[2]);

        $allcontentids = array_merge(...$chunks);
        $this->assertCount(5, $allcontentids);
        $this->assertNotContains('recent_content', $allcontentids);
    }

    /**
     * Test delete_scorms_by_contentids deletes SCORM modules by content IDs
     */
    public function test_delete_scorms_by_contentids(): void {
        global $DB;

        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();

        $deletedcontentids = [];
        for ($i = 1; $i <= 3; $i++) {
            $scorm = $this->getDataGenerator()->create_module('scorm', ['course' => $course->id]);
            $DB->insert_record('edflex_scorm', (object)[
                'edflexid' => 'delete' . $i,
                'scormid' => $scorm->id,
                'name' => 'Delete ' . $i,
                'url' => 'https://e.test/delete' . $i,
                'language' => 'en',
                'type' => 'article',
                'difficulty' => 'beginner',
                'duration' => 'PT1H',
                'author' => 'Author ' . $i,
                'downloadscormzip' => 'https://e.test/scorm' . $i . '.zip',
                'lastsync' => time(),
            ]);
            $deletedcontentids[] = 'delete' . $i;
        }

        $scormkeep = $this->getDataGenerator()->create_module('scorm', ['course' => $course->id]);
        $DB->insert_record('edflex_scorm', (object)[
            'edflexid' => 'keep1',
            'scormid' => $scormkeep->id,
            'name' => 'Keep This',
            'url' => 'https://e.test/keep',
            'language' => 'en',
            'type' => 'article',
            'difficulty' => 'beginner',
            'duration' => 'PT1H',
            'author' => 'Keep Author',
            'downloadscormzip' => 'https://e.test/keep.zip',
            'lastsync' => time(),
        ]);

        $mockclient = $this->getMockBuilder(client::class)
            ->disableOriginalConstructor()
            ->getMock();

        $activitymanager = new activity_manager($mockclient);

        $this->assertEquals(4, $DB->count_records('scorm', ['course' => $course->id]));
        $this->assertEquals(4, $DB->count_records('edflex_scorm'));

        $activitymanager->delete_scorms_by_contentids($deletedcontentids);

        $this->assertEquals(1, $DB->count_records('scorm', ['course' => $course->id]));
        $this->assertTrue($DB->record_exists('scorm', ['id' => $scormkeep->id]));
        $this->assertTrue($DB->record_exists('edflex_scorm', ['edflexid' => 'keep1']));

        $this->assertEquals(1, $DB->count_records_sql(
            "SELECT COUNT(*) FROM {course_modules} cm
            JOIN {modules} m ON m.id = cm.module
            WHERE m.name = 'scorm' AND cm.course = ?",
            [$course->id]
        ));
    }

    /**
     * Data provider for download trigger tests
     */
    public static function provide_get_modified_records_download_triggers_data(): array {
        return [
            'name_change_triggers_download' => [
                'originaldata' => [
                    'name' => 'Old Name',
                    'type' => 'article',
                    'url' => 'https://example.com/content',
                ],
                'newdata' => [
                    'name' => 'New Name',
                    'type' => 'article',
                    'url' => 'https://example.com/content',
                ],
                'shoulddownload' => true,
            ],
            'type_change_triggers_download' => [
                'originaldata' => [
                    'name' => 'Same Name',
                    'type' => 'article',
                    'url' => 'https://example.com/content',
                ],
                'newdata' => [
                    'name' => 'Same Name',
                    'type' => 'video',
                    'url' => 'https://example.com/content',
                ],
                'shoulddownload' => true,
            ],
            'url_change_triggers_download' => [
                'originaldata' => [
                    'name' => 'Same Name',
                    'type' => 'article',
                    'url' => 'https://example.com/old',
                ],
                'newdata' => [
                    'name' => 'Same Name',
                    'type' => 'article',
                    'url' => 'https://example.com/new',
                ],
                'shoulddownload' => true,
            ],
        ];
    }
}
