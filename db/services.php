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
 * Service definition
 *
 * @package     mod_edflex
 * @copyright   2025 Edflex <support@edflex.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_edflex_test_api_connection' => [
        'classname' => 'mod_edflex\external\test_api_connection',
        'methodname' => 'execute',
        'classpath' => '',
        'description' => 'Tests API connection.',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'mod_edflex_import_activity' => [
        'classname' => 'mod_edflex\external\import_activity',
        'methodname' => 'execute',
        'classpath' => '',
        'description' => 'Import an Edflex activity.',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'mod_edflex_browser_html' => [
        'classname' => 'mod_edflex\external\browser',
        'methodname' => 'html',
        'classpath' => '',
        'description' => 'Edflex browser HTML.',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'mod_edflex_browser_search' => [
        'classname' => 'mod_edflex\external\browser',
        'methodname' => 'search',
        'classpath' => '',
        'description' => 'Edflex browser search.',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],
];

$services = [
    'edflex service' => [
        'functions' => [
            'mod_edflex_test_api_connection',
            'mod_edflex_import_activity',
            'mod_edflex_browser_html',
            'mod_edflex_browser_search',
        ],
        'restrictedusers' => 0,
        'enabled' => 1,
    ],
];
