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
 * Unit tests for the mod_edflex observer.
 *
 * @package     mod_edflex
 * @copyright   2025 Edflex <support@edflex.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_edflex;

use advanced_testcase;
use cache;
use context_module;
use context_system;
use core\event\config_log_created;
use core\event\course_module_deleted;
use core\task\manager;
use mod_edflex\api\client;
use mod_edflex\local\activity_manager;
use mod_edflex\task\adhoc_synchronize_categories_task;
use moodle_exception;

/**
 * Unit tests for \mod_edflex\observer.
 *
 * @covers \mod_edflex\observer
 */
final class observer_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * Tests that the access_token is removed from cache when the config changes
     *
     * @covers \mod_edflex\observer::config_changed
     * @dataProvider provide_test_access_token_is_deleted_from_cache_when_config_is_changed_data
     *
     * @param string $modulename The module name.
     * @param bool $accesstokenmustbedeleted True if the access token must be deleted, false otherwise.
     */
    public function test_access_token_is_deleted_from_cache_when_config_is_changed(
        string $modulename,
        bool $accesstokenmustbedeleted
    ): void {
        global $DB;

        // Create a fake config_log entry to satisfy event requirements.
        $logid = $DB->insert_record('config_log', (object)[
            'userid'       => 1,
            'timemodified' => time(),
            'name'         => 'someconfig',
            'value'        => 'newvalue',
            'oldvalue'     => 'oldvalue',
            'plugin'       => $modulename,
        ]);

        // Trigger the event exactly as Moodle would.
        $event = config_log_created::create([
            'context' => context_system::instance(),
            'objectid' => $logid,
            'other'   => [
                'plugin' => $modulename,
                'name'   => 'someconfig',
                'oldvalue'  => 'oldvalue',
                'value'  => 'newvalue',
            ],
        ]);

        $cache = cache::make('mod_edflex', 'api');
        $token = [
            'access_token' => 'cached_token',
            'expires_in' => 3600,
            'expire_ts' => time() + 3600,
        ];

        $cache->set('access_token', $token);
        $this->assertEquals($token, $cache->get('access_token'));
        $event->trigger();

        if ($accesstokenmustbedeleted) {
            $this->assertFalse($cache->has('access_token'));
        } else {
            $this->assertTrue($cache->has('access_token'));
        }
    }

    /**
     * Test course_module_deleted event handler
     *
     * @covers \mod_edflex\observer::course_module_deleted
     */
    public function test_course_module_deleted_cleans_orphaned_records(): void {
        global $DB;

        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $scorm = $this->getDataGenerator()->create_module('scorm', ['course' => $course->id]);

        $orphanedrecord = (object)[
            'edflexid' => 'orphaned_content',
            'scormid' => 99999,
            'name' => 'Orphaned Activity',
            'url' => 'https://e.test/orphaned',
            'language' => 'en',
            'type' => 'article',
            'difficulty' => 'beginner',
            'duration' => 'PT1H',
            'author' => 'Author',
            'downloadscormzip' => 'https://e.test/orphaned.zip',
            'lastsync' => time(),
        ];
        $DB->insert_record('edflex_scorm', $orphanedrecord);

        $cm = get_coursemodule_from_instance('scorm', $scorm->id, $course->id);

        $event = course_module_deleted::create([
            'objectid' => $cm->id,
            'context' => context_module::instance($cm->id),
            'courseid' => $course->id,
            'other' => [
                'modulename' => 'scorm',
                'instanceid' => $scorm->id,
            ],
        ]);

        $this->assertTrue($DB->record_exists('edflex_scorm', ['scormid' => 99999]));

        observer::course_module_deleted($event);

        $this->assertFalse($DB->record_exists('edflex_scorm', ['scormid' => 99999]));
    }


    /**
     * Test course_module_deleted with exception
     *
     * @covers \mod_edflex\observer::course_module_deleted
     */
    public function test_course_module_deleted_with_exception(): void {
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $scorm = $this->getDataGenerator()->create_module('scorm', ['course' => $course->id]);

        $cm = get_coursemodule_from_instance('scorm', $scorm->id, $course->id);

        $event = course_module_deleted::create([
            'objectid' => $cm->id,
            'context' => context_module::instance($cm->id),
            'courseid' => $course->id,
            'other' => [
                'modulename' => 'scorm',
                'instanceid' => $scorm->id,
            ],
        ]);

        $mockactivitymanager = $this->getMockBuilder(activity_manager::class)
            ->onlyMethods(['delete_orphaned_edflex_scorms'])
            ->getMock();

        $exception = new moodle_exception('failed to delete');
        $mockactivitymanager->expects($this->once())
            ->method('delete_orphaned_edflex_scorms')
            ->willThrowException($exception);

        ob_start();
        observer::course_module_deleted($event, $mockactivitymanager);
        $content = ob_get_clean();

        $this->assertStringStartsWith('WARNING!', $content);
        $this->assertStringContainsString($exception->getMessage(), $content);
    }

    /**
     * Test create_or_delete_adhoc_synchronize_categories_task with exception
     *
     * @covers \mod_edflex\observer::create_or_delete_adhoc_synchronize_categories_task
     */
    public function test_create_or_delete_adhoc_synchronize_categories_task_with_exception(): void {
        global $DB;

        $mockclient = $this->getMockBuilder(client::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['can_connect_to_the_api'])
            ->getMock();

        $exception = new moodle_exception('failed to connect');
        $mockclient->expects($this->once())
            ->method('can_connect_to_the_api')
            ->willThrowException($exception);

        $task = new adhoc_synchronize_categories_task();
        manager::queue_adhoc_task($task);

        $this->assertEquals(1, $DB->count_records('task_adhoc'));

        observer::create_or_delete_adhoc_synchronize_categories_task($mockclient);

        $this->assertEquals(0, $DB->count_records('task_adhoc'));
    }

    /**
     * Test create_or_delete_adhoc_synchronize_categories_task will create a new task
     *
     * @covers \mod_edflex\observer::create_or_delete_adhoc_synchronize_categories_task
     */
    public function test_create_or_delete_adhoc_synchronize_categories_task_no_existing(): void {
        global $DB;

        $mockclient = $this->getMockBuilder(client::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['can_connect_to_the_api'])
            ->getMock();

        $mockclient->expects($this->once())
            ->method('can_connect_to_the_api')
            ->willReturn(true);

        $this->assertEquals(0, $DB->count_records('task_adhoc'));

        observer::create_or_delete_adhoc_synchronize_categories_task($mockclient);

        $this->assertEquals(1, $DB->count_records('task_adhoc'));
    }

    /**
     * Test create_or_delete_adhoc_synchronize_categories_task will keep the existing task
     *
     * @covers \mod_edflex\observer::create_or_delete_adhoc_synchronize_categories_task
     */
    public function test_create_or_delete_adhoc_synchronize_categories_task_existing(): void {
        global $DB;

        $mockclient = $this->getMockBuilder(client::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['can_connect_to_the_api'])
            ->getMock();

        $mockclient->expects($this->once())
            ->method('can_connect_to_the_api')
            ->willReturn(true);

        $task = new adhoc_synchronize_categories_task();
        manager::queue_adhoc_task($task);

        $this->assertEquals(1, $DB->count_records('task_adhoc'));

        observer::create_or_delete_adhoc_synchronize_categories_task($mockclient);

        $this->assertEquals(1, $DB->count_records('task_adhoc'));
    }

    /**
     * Provides data for access_token deletion test
     */
    public static function provide_test_access_token_is_deleted_from_cache_when_config_is_changed_data(): array {
        return [
            ['mod_edflex', true],
            ['mod_form', false],
        ];
    }
}
