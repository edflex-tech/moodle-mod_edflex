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
use external_single_structure;
use mod_edflex\api\client;
use moodle_exception;

/**
 * Unit tests for the \mod_edflex\external\browser class.
 *
 * @package     mod_edflex
 * @copyright   2025 Edflex <support@edflex.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @runTestsInSeparateProcesses
 * @covers \mod_edflex\external\browser
 */
final class browser_test extends advanced_testcase {
    protected function setUp(): void {
        global $CFG;
        require_once($CFG->libdir . '/externallib.php');

        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * Test search_parameters method returns the correct structure.
     */
    public function test_search_parameters(): void {
        $parameters = browser::search_parameters();
        $this->assertInstanceOf(external_function_parameters::class, $parameters);
        $keys = $parameters->keys;

        $this->assertArrayHasKey('filters', $keys);
        $this->assertArrayHasKey('course', $keys);
        $this->assertArrayHasKey('page', $keys);
    }

    /**
     * Test the search method with valid parameters.
     */
    public function test_search_valid_parameters(): void {
        $mockclient = $this->getMockBuilder(client::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get_contents'])
            ->getMock();

        $mockclient->method('get_contents')->willReturn([
            'data' => [[
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
            ]],
            'links' => ['next' => false],
        ]);

        $this->assign_capability('mod/edflex:addinstance');

        $result = browser::search(['query' => 'test', 'language' => 'en'], 1, 1, $mockclient);
        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['contents']);
    }

    /**
     * Test search method with invalid filters.
     */
    public function test_search_invalid_filters(): void {
        $this->assign_capability('mod/edflex:addinstance');
        $this->expectException(moodle_exception::class);
        browser::search([], 1);
    }

    /**
     * Test search_returns method returns correct structure.
     */
    public function test_search_returns(): void {
        $returns = browser::search_returns();
        $this->assertInstanceOf(external_single_structure::class, $returns);
        $keys = $returns->keys;

        $this->assertArrayHasKey('success', $keys);
        $this->assertArrayHasKey('contents', $keys);
        $this->assertArrayHasKey('pages', $keys);
    }

    /**
     * Create a user and assign a capability
     */
    private function assign_capability(string $capability): void {
        $user = $this->getDataGenerator()->create_user();
        $roleid = create_role('Temporary Role', 'temp_role', 'Temporary testing role');
        assign_capability($capability, CAP_ALLOW, $roleid, context_system::instance()->id);
        role_assign($roleid, $user->id, context_system::instance()->id);
        $this->setUser($user);
    }
}
