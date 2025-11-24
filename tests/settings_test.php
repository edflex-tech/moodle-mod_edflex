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
 * The main mod_edflex configuration form.
 *
 * @package     mod_edflex
 * @copyright   2025 Edflex <support@edflex.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_edflex;

use advanced_testcase;

/**
 * Unit tests for mod_edflex plugin config.
 *
 * @coversNothing
 */
final class settings_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    public function test_default_config_values(): void {
        $this->assertEmpty(get_config('mod_edflex', 'clientid'));
        $this->assertEmpty(get_config('mod_edflex', 'clientsecret'));
        $this->assertEmpty(get_config('mod_edflex', 'apiurl'));
    }

    public function test_set_and_get_config_values(): void {
        set_config('clientid', 'abc123', 'mod_edflex');
        set_config('clientsecret', 'secret456', 'mod_edflex');
        set_config('apiurl', 'https://e.test', 'mod_edflex');

        $this->assertEquals('abc123', get_config('mod_edflex', 'clientid'));
        $this->assertEquals('secret456', get_config('mod_edflex', 'clientsecret'));
        $this->assertEquals('https://e.test', get_config('mod_edflex', 'apiurl'));
    }
}
