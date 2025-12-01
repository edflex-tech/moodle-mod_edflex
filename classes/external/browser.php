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
 * External API for importing activities in the mod_edflex module.
 *
 * This class provides functions for searching and retrieving content data
 * based on specified filters through the Edflex API.
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
use mod_edflex\api\constants;
use mod_edflex\api\mapper;
use mod_edflex\local\activity_manager;
use mod_edflex\output\browser as output_browser;
use moodle_exception;

/**
 * Provides external API functions for browsing and searching Edflex content.
 *
 * @package     mod_edflex
 * @copyright   2025 Edflex <support@edflex.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class browser extends external_api {
    /**
     * Defines the parameters required for the external function execution.
     */
    public static function search_parameters(): external_function_parameters {
        return new external_function_parameters([
            'filters' => new external_single_structure([
                'query'  => new external_value(PARAM_TEXT, '', VALUE_OPTIONAL, ''),
                'language'  => new external_value(PARAM_TEXT, '', VALUE_OPTIONAL, ''),
                'type'  => new external_value(PARAM_TEXT, '', VALUE_OPTIONAL, ''),
                'category'  => new external_value(PARAM_TEXT, '', VALUE_OPTIONAL, ''),
                'difficulty'  => new external_value(PARAM_TEXT, '', VALUE_OPTIONAL, ''),
                'duration'  => new external_value(PARAM_TEXT, '', VALUE_OPTIONAL, ''),
            ]),
            'course'  => new external_value(PARAM_INT, '', VALUE_REQUIRED, 0),
            'page'  => new external_value(PARAM_INT, '', VALUE_REQUIRED, 1),
        ]);
    }

    /**
     * Searches for content based on the provided filters and course context.
     *
     * @param array $filters An associative array of filters used to narrow down the search results.
     *                       Accepts language and other filter parameters.
     * @param int $course The ID of the course where the content search is being performed.
     * @param int $page The page number for paginated results. Defaults to 1.
     * @param client|null $client An optional client instance for managing content search requests.
     *                            If not provided, a default client will be used.
     *
     * @return array Returns an associative array containing the search results:
     *               - success: A boolean indicating if the search was successful.
     *               - contents: An array of content data mapped to specific fields.
     *               - pages: An array of pagination information.
     *
     * @throws moodle_exception If the course parameter is not provided or invalid.
     */
    public static function search(array $filters, int $course, int $page = 1, ?client $client = null): array {
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('mod/edflex:addinstance', $context);

        $data = compact('filters', 'course', 'page');
        $data = self::validate_parameters(self::search_parameters(), $data);
        $filters = $data['filters'];
        $course = $data['course'] ?? null;

        if (empty($course)) {
            throw new moodle_exception('missingcourse', 'mod_edflex');
        }

        $filters['language'] = implode(',', array_intersect(
            explode(',', $filters['language'] ?? ''),
            constants::CONTENT_LANGUAGES
        ));

        $client = $client ?? client::from_config();
        $response = $client->get_contents($filters, $page);
        $contents = mapper::map_contents($response['data'] ?? []);
        $hasnext = !empty($response['links']['next']);
        $pages = self::get_search_pages($page, $hasnext);

        $activitymanager = new activity_manager($client);
        $edflexidsinthecourse = $activitymanager->get_edflexids_in_the_course($course);

        $fields = [
            'name',
            'edflexid',
            'duration_formatted',
            'type_formatted',
            'language',
            'difficulty_formatted',
            'image_small',
        ];

        $contents = array_map(function ($item) use ($fields, $edflexidsinthecourse) {
            $row = array_intersect_key($item, array_flip($fields));
            $row['is_in_the_course'] = in_array($item['edflexid'], $edflexidsinthecourse);

            return $row;
        }, $contents);

        return [
            'success' => true,
            'contents' => $contents,
            'pages' => $pages,
        ];
    }

    /**
     * Returns the structure of the data returned by the search method.
     */
    public static function search_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL),
            'contents' => new external_multiple_structure(
                new external_single_structure([
                    'edflexid'  => new external_value(PARAM_TEXT),
                    'name' => new external_value(PARAM_TEXT),
                    'duration_formatted' => new external_value(PARAM_TEXT),
                    'type_formatted' => new external_value(PARAM_TEXT),
                    'language' => new external_value(PARAM_TEXT),
                    'difficulty_formatted' => new external_value(PARAM_TEXT),
                    'is_in_the_course' => new external_value(PARAM_BOOL),
                    'image_small' => new external_value(PARAM_URL),
                ])
            ),
            'pages' => new external_multiple_structure(
                new external_single_structure([
                    'current'  => new external_value(PARAM_BOOL, '', VALUE_OPTIONAL),
                    'number' => new external_value(PARAM_INT),
                    'title' => new external_value(PARAM_TEXT),
                ])
            ),
        ]);
    }

    /**
     * Defines the parameters required for the external function execution.
     */
    public static function html_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Returns the HTML for the browser form.
     */
    public static function html(): array {
        global $OUTPUT;

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('mod/edflex:addinstance', $context);

        return [
            'success' => true,
            'html' => $OUTPUT->render(new output_browser()),
            'title' => get_string('openedflexbrowser', 'mod_edflex'),
        ];
    }

    /**
     * Returns the structure of the data returned by the HTML method.
     */
    public static function html_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL),
            'html' => new external_value(PARAM_RAW),
            'title' => new external_value(PARAM_TEXT),
        ]);
    }

    /**
     * Build pages for navigation.
     *
     * @param int $page The current page.
     * @param bool $hasnext Whether there is a next page.
     *
     * @return array The searched pages.
     */
    private static function get_search_pages(int $page, bool $hasnext = false): array {
        $pages = [];

        if ($page <= 10) {
            for ($p = 1; $p <= $page; $p++) {
                $pages[] = ['number' => $p, 'title' => $p, 'current' => ($page === $p)];
            }
        } else {
            for ($p = 1; $p <= 3; $p++) {
                $pages[] = ['number' => $p, 'title' => $p, 'current' => ($page === $p)];
            }

            $pages[] = ['number' => null, 'title' => '...', 'current' => false];

            for ($p = $page - 3; $p <= $page; $p++) {
                $pages[] = ['number' => $p, 'title' => $p, 'current' => ($page === $p)];
            }
        }

        if ($hasnext) {
            $pages[] = ['number' => $page + 1, 'title' => '»', 'current' => false];
        }

        return $pages;
    }
}
