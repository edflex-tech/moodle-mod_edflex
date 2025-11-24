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
 * Test for the synchronize_categories_task class
 *
 * @package     mod_edflex
 * @copyright   2025 Edflex <support@edflex.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_edflex\task;

use advanced_testcase;
use mod_edflex\api\client;
use mod_edflex\local\category_manager;
use moodle_exception;
use Exception;

/**
 * Unit tests for mod_edflex\task\synchronize_categories_task
 *
 * @covers \mod_edflex\task\synchronize_categories_task
 */
final class synchronize_categories_task_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test get_name returns correct string
     */
    public function test_get_name(): void {
        $task = new synchronize_categories_task();
        $expectedname = get_string('synchronizecategoriestask', 'mod_edflex');
        $this->assertEquals($expectedname, $task->get_name());
    }

    /**
     * Test execute with successful API connection and synchronization
     */
    public function test_execute_successful_synchronization(): void {
        $mockclient = $this->getMockBuilder(client::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['can_connect_to_the_api'])
            ->getMock();

        $mockclient->expects($this->once())
            ->method('can_connect_to_the_api')
            ->willReturn(true);

        $mockcategorymanager = $this->getMockBuilder(category_manager::class)
            ->setConstructorArgs([$mockclient])
            ->onlyMethods(['synchronize_all_catalogs'])
            ->getMock();

        $mockcategorymanager->expects($this->once())
            ->method('synchronize_all_catalogs');

        $task = $this->getMockBuilder(synchronize_categories_task::class)
            ->onlyMethods(['get_client', 'get_category_manager'])
            ->getMock();

        $task->expects($this->once())
            ->method('get_client')
            ->willReturn($mockclient);

        $task->expects($this->once())
            ->method('get_category_manager')
            ->willReturn($mockcategorymanager);

        ob_start();
        $task->execute();
        $output = ob_get_clean();

        $this->assertEmpty($output);
    }

    /**
     * Test execute with API connection failure
     */
    public function test_execute_api_connection_failure(): void {
        $mockclient = $this->getMockBuilder(client::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['can_connect_to_the_api'])
            ->getMock();

        $mockclient->expects($this->once())
            ->method('can_connect_to_the_api')
            ->willReturn(false);

        $task = $this->getMockBuilder(synchronize_categories_task::class)
            ->onlyMethods(['get_client'])
            ->getMock();

        $task->expects($this->once())
            ->method('get_client')
            ->willReturn($mockclient);

        ob_start();
        $task->execute();
        $output = ob_get_clean();

        $this->assertStringContainsString('WARNING! Could not connect to Edflex API', $output);
        $this->assertStringContainsString('Skipping categories synchronization', $output);
    }

    /**
     * Test execute with exception during API connection check
     */
    public function test_execute_api_connection_exception(): void {
        $mockclient = $this->getMockBuilder(client::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['can_connect_to_the_api'])
            ->getMock();

        $mockclient->expects($this->once())
            ->method('can_connect_to_the_api')
            ->willThrowException(new Exception('Network error'));

        $task = $this->getMockBuilder(synchronize_categories_task::class)
            ->onlyMethods(['get_client'])
            ->getMock();

        $task->expects($this->once())
            ->method('get_client')
            ->willReturn($mockclient);

        ob_start();
        $task->execute();
        $output = ob_get_clean();

        $this->assertStringContainsString('WARNING! Could not connect to Edflex API', $output);
        $this->assertStringContainsString('Network error', $output);
        $this->assertStringContainsString('Skipping categories synchronization', $output);
    }

    /**
     * Test execute with exception during synchronization
     */
    public function test_execute_synchronization_exception(): void {
        $mockclient = $this->getMockBuilder(client::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['can_connect_to_the_api'])
            ->getMock();

        $mockclient->expects($this->once())
            ->method('can_connect_to_the_api')
            ->willReturn(true);

        $mockcategorymanager = $this->getMockBuilder(category_manager::class)
            ->setConstructorArgs([$mockclient])
            ->onlyMethods(['synchronize_all_catalogs'])
            ->getMock();

        $mockcategorymanager->expects($this->once())
            ->method('synchronize_all_catalogs')
            ->willThrowException(new Exception('Database error during sync'));

        $task = $this->getMockBuilder(synchronize_categories_task::class)
            ->onlyMethods(['get_client', 'get_category_manager'])
            ->getMock();

        $task->expects($this->any())
            ->method('get_client')
            ->willReturn($mockclient);

        $task->expects($this->once())
            ->method('get_category_manager')
            ->willReturn($mockcategorymanager);

        ob_start();
        $task->execute();
        $output = ob_get_clean();

        $this->assertStringContainsString('ERROR! An exception occurred during categories synchronization', $output);
        $this->assertStringContainsString('Database error during sync', $output);
    }

    /**
     * Test execute with moodle_exception for API connection error
     */
    public function test_execute_moodle_exception_api_error(): void {
        $mockclient = $this->getMockBuilder(client::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['can_connect_to_the_api'])
            ->getMock();

        $mockclient->expects($this->once())
            ->method('can_connect_to_the_api')
            ->willThrowException(new moodle_exception('apiconnectionerror', 'mod_edflex'));

        $task = $this->getMockBuilder(synchronize_categories_task::class)
            ->onlyMethods(['get_client'])
            ->getMock();

        $task->expects($this->once())
            ->method('get_client')
            ->willReturn($mockclient);

        ob_start();
        $task->execute();
        $output = ob_get_clean();

        $this->assertStringContainsString('WARNING! Could not connect to Edflex API', $output);
        $this->assertStringContainsString('Skipping categories synchronization', $output);
    }

    /**
     * Test get_client returns singleton instance
     */
    public function test_get_client_returns_singleton(): void {
        set_config('clientid', 'test_id', 'mod_edflex');
        set_config('clientsecret', 'test_secret', 'mod_edflex');
        set_config('apiurl', 'https://e.test', 'mod_edflex');

        $task = new synchronize_categories_task();

        $client1 = $task->get_client();
        $client2 = $task->get_client();

        $this->assertSame($client1, $client2);
    }

    /**
     * Test get_category_manager returns singleton instance
     */
    public function test_get_category_manager_returns_singleton(): void {
        set_config('clientid', 'test_id', 'mod_edflex');
        set_config('clientsecret', 'test_secret', 'mod_edflex');
        set_config('apiurl', 'https://e.test', 'mod_edflex');

        $task = new synchronize_categories_task();

        $manager1 = $task->get_category_manager();
        $manager2 = $task->get_category_manager();

        $this->assertSame($manager1, $manager2);
    }

    /**
     * Test execute with invalid configuration
     */
    public function test_execute_with_invalid_config(): void {
        set_config('clientid', '', 'mod_edflex');
        set_config('clientsecret', '', 'mod_edflex');
        set_config('apiurl', '', 'mod_edflex');

        $task = new synchronize_categories_task();

        ob_start();
        $task->execute();
        $output = ob_get_clean();

        $this->assertStringContainsString('WARNING! Could not connect to Edflex API', $output);
        $this->assertStringContainsString('Skipping categories synchronization', $output);
    }

    /**
     * Test execute with partial synchronization
     */
    public function test_execute_partial_synchronization(): void {
        $mockclient = $this->getMockBuilder(client::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['can_connect_to_the_api'])
            ->getMock();

        $mockclient->expects($this->once())
            ->method('can_connect_to_the_api')
            ->willReturn(true);

        $mockcategorymanager = $this->getMockBuilder(category_manager::class)
            ->setConstructorArgs([$mockclient])
            ->onlyMethods(['synchronize_all_catalogs'])
            ->getMock();

        $mockcategorymanager->expects($this->once())
            ->method('synchronize_all_catalogs')
            ->willReturnCallback(function () {
                mtrace('Synchronized 5 catalogs successfully');
                mtrace('Failed to synchronize 1 catalog');
            });

        $task = $this->getMockBuilder(synchronize_categories_task::class)
            ->onlyMethods(['get_client', 'get_category_manager'])
            ->getMock();

        $task->expects($this->any())
            ->method('get_client')
            ->willReturn($mockclient);

        $task->expects($this->once())
            ->method('get_category_manager')
            ->willReturn($mockcategorymanager);

        ob_start();
        $task->execute();
        $output = ob_get_clean();

        $this->assertStringContainsString('Synchronized 5 catalogs successfully', $output);
        $this->assertStringContainsString('Failed to synchronize 1 catalog', $output);
    }

    /**
     * Test execute with timeout during synchronization
     */
    public function test_execute_synchronization_timeout(): void {
        $mockclient = $this->getMockBuilder(client::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['can_connect_to_the_api'])
            ->getMock();

        $mockclient->expects($this->once())
            ->method('can_connect_to_the_api')
            ->willReturn(true);

        $mockcategorymanager = $this->getMockBuilder(category_manager::class)
            ->setConstructorArgs([$mockclient])
            ->onlyMethods(['synchronize_all_catalogs'])
            ->getMock();

        $mockcategorymanager->expects($this->once())
            ->method('synchronize_all_catalogs')
            ->willThrowException(new Exception('Execution timeout reached'));

        $task = $this->getMockBuilder(synchronize_categories_task::class)
            ->onlyMethods(['get_client', 'get_category_manager'])
            ->getMock();

        $task->expects($this->any())
            ->method('get_client')
            ->willReturn($mockclient);

        $task->expects($this->once())
            ->method('get_category_manager')
            ->willReturn($mockcategorymanager);

        ob_start();
        $task->execute();
        $output = ob_get_clean();

        $this->assertStringContainsString('ERROR! An exception occurred during categories synchronization', $output);
        $this->assertStringContainsString('Execution timeout reached', $output);
    }

    /**
     * Test execute with empty catalogs
     */
    public function test_execute_with_empty_catalogs(): void {
        $mockclient = $this->getMockBuilder(client::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['can_connect_to_the_api'])
            ->getMock();

        $mockclient->expects($this->once())
            ->method('can_connect_to_the_api')
            ->willReturn(true);

        $mockcategorymanager = $this->getMockBuilder(category_manager::class)
            ->setConstructorArgs([$mockclient])
            ->onlyMethods(['synchronize_all_catalogs'])
            ->getMock();

        $mockcategorymanager->expects($this->once())
            ->method('synchronize_all_catalogs')
            ->willReturnCallback(function () {
                mtrace('No catalogs found to synchronize');
            });

        $task = $this->getMockBuilder(synchronize_categories_task::class)
            ->onlyMethods(['get_client', 'get_category_manager'])
            ->getMock();

        $task->expects($this->any())
            ->method('get_client')
            ->willReturn($mockclient);

        $task->expects($this->once())
            ->method('get_category_manager')
            ->willReturn($mockcategorymanager);

        ob_start();
        $task->execute();
        $output = ob_get_clean();

        $this->assertStringContainsString('No catalogs found to synchronize', $output);
    }

    /**
     * Test execute with API rate limit error
     */
    public function test_execute_api_rate_limit(): void {
        $mockclient = $this->getMockBuilder(client::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['can_connect_to_the_api'])
            ->getMock();

        $mockclient->expects($this->once())
            ->method('can_connect_to_the_api')
            ->willReturn(true);

        $mockcategorymanager = $this->getMockBuilder(category_manager::class)
            ->setConstructorArgs([$mockclient])
            ->onlyMethods(['synchronize_all_catalogs'])
            ->getMock();

        $mockcategorymanager->expects($this->once())
            ->method('synchronize_all_catalogs')
            ->willThrowException(new moodle_exception('apiratelimit', 'mod_edflex'));

        $task = $this->getMockBuilder(synchronize_categories_task::class)
            ->onlyMethods(['get_client', 'get_category_manager'])
            ->getMock();

        $task->expects($this->any())
            ->method('get_client')
            ->willReturn($mockclient);

        $task->expects($this->once())
            ->method('get_category_manager')
            ->willReturn($mockcategorymanager);

        ob_start();
        $task->execute();
        $output = ob_get_clean();

        $this->assertStringContainsString('ERROR! An exception occurred during categories synchronization', $output);
    }
}
