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
 * Plugin administration pages are defined here.
 *
 * @package     mod_edflex
 * @category    admin
 * @copyright   2025 Edflex <support@edflex.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configtext(
        'mod_edflex/clientid',
        get_string('clientid', 'mod_edflex'),
        get_string('clientid_desc', 'mod_edflex'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'mod_edflex/clientsecret',
        get_string('clientsecret', 'mod_edflex'),
        get_string('clientsecret_desc', 'mod_edflex'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'mod_edflex/apiurl',
        get_string('apiurl', 'mod_edflex'),
        get_string('apiurl_desc', 'mod_edflex'),
        '',
        PARAM_URL
    ));

    $PAGE->requires->js_call_amd('mod_edflex/testconnection', 'init', ['testconnectionbutton']);

    $settings->add(new admin_setting_heading(
        'mod_edflex/testconnectionheading',
        '',
        '<div id="mod_edflex_button_container" style="margin-left: 1em;">
            <button type="button" class="btn btn-secondary" id="mod_edflex_test_api_connection_btn">
                Test API Connection
            </button>
            <span id="mod_edflex_connection_status" style="margin-left: 1em;"></span>
        </div>'
    ));
}
