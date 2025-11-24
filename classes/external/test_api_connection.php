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
 * Provides an external API function for testing an EDFLEX API connection.
 *
 * @package     mod_edflex
 * @copyright   2025 Edflex <support@edflex.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_edflex\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

use context_system;
use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use mod_edflex\api\client;

/**
 * Provides an external API function for verifying an API connection.
 *
 * @package     mod_edflex
 * @copyright   2025 Edflex <support@edflex.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class test_api_connection extends external_api {
    /**
     * Defines the parameters required for the external function execution.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'apiurl' => new external_value(PARAM_URL, get_string('apiurl', 'mod_edflex'), VALUE_REQUIRED),
            'clientid' => new external_value(PARAM_TEXT, get_string('clientid', 'mod_edflex'), VALUE_REQUIRED),
            'clientsecret' => new external_value(PARAM_TEXT, get_string('clientsecret', 'mod_edflex'), VALUE_REQUIRED),
        ]);
    }

    /**
     * Executes the function to request an access token using the provided API URL, client ID, and client secret.
     */
    public static function execute(string $apiurl, string $clientid, string $clientsecret, ?client $client = null): array {
        require_capability('moodle/site:config', context_system::instance());

        $data = compact('apiurl', 'clientid', 'clientsecret');
        $data = self::validate_parameters(self::execute_parameters(), $data);
        $client = $client ?? client::from_array($data);
        $response = $client->request_access_token();

        return [
            'success' => !empty($response['access_token']),
        ];
    }

    /**
     * Returns the structure of the data returned by the execute method.
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL),
        ]);
    }
}
