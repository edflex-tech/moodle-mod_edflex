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
 * Test for the test_api_connection class
 *
 * @package     mod_edflex
 * @copyright   2025 Edflex <support@edflex.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_edflex\external;

use advanced_testcase;
use context_system;
use external_function_parameters;
use external_single_structure;
use external_value;
use mod_edflex\api\client;
use moodle_exception;

/**
 * Unit tests for mod_edflex\external\test_api_connection
 *
 * @runTestsInSeparateProcesses
 * @covers \mod_edflex\external\test_api_connection
 */
final class test_api_connection_test extends advanced_testcase {
    protected function setUp(): void {
        global $CFG;
        require_once($CFG->libdir . '/externallib.php');

        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test execute_parameters method returns correct structure
     */
    public function test_execute_parameters(): void {
        $parameters = test_api_connection::execute_parameters();

        $this->assertInstanceOf(external_function_parameters::class, $parameters);

        $keys = $parameters->keys;
        $this->assertArrayHasKey('apiurl', $keys);
        $this->assertArrayHasKey('clientid', $keys);
        $this->assertArrayHasKey('clientsecret', $keys);

        $this->assertInstanceOf(external_value::class, $keys['apiurl']);
        $this->assertInstanceOf(external_value::class, $keys['clientid']);
        $this->assertInstanceOf(external_value::class, $keys['clientsecret']);
    }

    /**
     * Test execute_returns method returns correct structure
     */
    public function test_execute_returns(): void {
        $returns = test_api_connection::execute_returns();

        $this->assertInstanceOf(external_single_structure::class, $returns);

        // Check that the success field is present and is a boolean.
        $keys = $returns->keys;
        $this->assertArrayHasKey('success', $keys);
        $this->assertInstanceOf(external_value::class, $keys['success']);
    }

    /**
     * Test execute method with successful API connection
     */
    public function test_execute_success(): void {
        $mock = $this->getMockBuilder(client::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['request_access_token'])
            ->getMock();

        // Expected fake API response.
        $mock->method('request_access_token')
            ->willReturn([
                'access_token' => 'cached_token',
                'expires_in' => 3600,
                'expire_ts' => time() + 3600,
            ]);

        $this->assign_capability('moodle/site:config');

        $result = test_api_connection::execute(
            'https://test.api',
            'test_client_id',
            'test_client_secret',
            $mock
        );

        $this->assertArrayHasKey('success', $result);
    }

    /**
     * Test invalid parameters for the method test_api_connection::execute
     *
     * @dataProvider provide_test_execute_with_invalid_parameters_data
     *
     * @param string $apiurl The API URL.
     * @param string $clientid The client ID.
     * @param string $clientsecret The client secret.
     */
    public function test_execute_with_invalid_parameters(string $apiurl, string $clientid, string $clientsecret): void {
        $this->expectException(moodle_exception::class);
        $this->assign_capability('moodle/site:config');

        test_api_connection::execute($apiurl, $clientid, $clientsecret);
    }

    /**
     * Provides invalid parameters for the method test_api_connection::execute
     */
    public static function provide_test_execute_with_invalid_parameters_data(): array {
        return [
            ['https://e.test', 'test_client_id', ''],
            ['https://e.test', '', 'test_client_secret'],
            ['', 'test_client_id', 'test_client_secret'],
        ];
    }

    /**
     * Create a user and assign a capability
     *
     * @param string $capability The capability.
     */
    private function assign_capability(string $capability): void {
        $user = $this->getDataGenerator()->create_user();
        $roleid = create_role('Temporary Role', 'temp_role', 'Temporary testing role');
        assign_capability($capability, CAP_ALLOW, $roleid, context_system::instance()->id);
        role_assign($roleid, $user->id, context_system::instance()->id);
        $this->setUser($user);
    }
}
