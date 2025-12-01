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
 * Provides an external API function for importing Edflex activities into a course.
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
use external_multiple_structure;
use external_single_structure;
use external_value;
use mod_edflex\api\client;
use mod_edflex\api\mapper;
use mod_edflex\local\activity_manager;
use moodle_exception;
use moodle_url;

/**
 * Provides an external API function for importing Edflex activities into a course section
 * and retrieving related information.
 *
 * @package     mod_edflex
 * @copyright   2025 Edflex <support@edflex.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class import_activity extends external_api {
    /**
     * Defines the parameters required for the external function execution.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'edflexid' => new external_multiple_structure(
                new external_value(PARAM_TEXT),
            ),
            'course' => new external_value(PARAM_INT),
            'section' => new external_value(PARAM_INT),
        ]);
    }

    /**
     * Executes the function to request an access token using the provided API URL, client ID, and client secret.
     *
     * @param array $edflexid The Edflex content IDs.
     * @param int $course The course ID.
     * @param int $section The section ID.
     * @param ?client $client The client instance.
     *
     * @return array The result of the import.
     */
    public static function execute(array $edflexid, int $course, int $section, ?client $client = null): array {
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('mod/edflex:addinstance', $context);

        $data = self::validate_parameters(
            self::execute_parameters(),
            compact('edflexid', 'course', 'section')
        );

        $client = $client ?? client::from_config();
        $edflexcontents = iterator_to_array($client->get_contents_by_ids($data['edflexid']));

        if (empty($edflexcontents)) {
            throw new moodle_exception('failedtofetchedflexcontents', 'mod_edflex');
        }

        $edflexcontents = mapper::map_contents($edflexcontents);
        $activitymanager = new activity_manager($client);
        $results = $activitymanager->import_contents($edflexcontents, $data['course'], $data['section']);

        $activities = [];

        foreach ($results as [$cm]) {
            $url = new moodle_url('/mod/scorm/view.php', ['id' => $cm->coursemodule]);
            $activities[] = [
                'id' => $cm->instance,
                'url' => $url->out(false),
            ];
        }

        $url = new moodle_url('/course/view.php', ['id' => $course]);
        $courseurl = $url->out(false);

        return [
            'success' => true,
            'course' => ['url' => $courseurl],
            'activities' => $activities,
        ];
    }

    /**
     * Returns the structure of the data returned by the execute method.
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL),
            'course' => new external_single_structure([
                'url' => new external_value(PARAM_URL),
            ]),
            'activities' => new external_multiple_structure(
                new external_single_structure([
                    'id'  => new external_value(PARAM_INT),
                    'url' => new external_value(PARAM_URL),
                ])
            ),
        ]);
    }
}
