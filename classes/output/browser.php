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

namespace mod_edflex\output;

use mod_edflex\api\constants;
use renderable;
use renderer_base;
use templatable;

/**
 * Class browser implementing renderable and templatable interfaces.
 *
 * This class provides the implementation for exporting renderer data
 * suitable for use in mustache templates.
 *
 * @package     mod_edflex
 * @copyright   2025 Edflex <support@edflex.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class browser implements renderable, templatable {
    /**
     * Prepares data for export to a template.
     *
     * @param renderer_base $output The renderer instance used for exporting the data.
     * @return array An array of data ready for use in templates.
     */
    public function export_for_template(renderer_base $output) {
        return [
            'contenttypes' => constants::get_content_types_options(),
            'languages' => self::get_languages(),
            'categories' => self::get_categories(),
        ];
    }

    /**
     * Retrieves a list of available languages and their translations.
     *
     * @return array An array of languages, each represented as an associative array with 'value' and 'label' keys.
     */
    public static function get_languages(): array {
        $languages = get_string_manager()->get_list_of_languages();
        $languages = array_intersect_key($languages, array_flip(constants::CONTENT_LANGUAGES));

        return array_map(
            static function (string $value, string $label) {
                return [
                    'value' => $value,
                    'label' => $label,
                ];
            },
            array_keys($languages),
            array_values($languages)
        );
    }

    /**
     * Retrieves and formats categories from the API.
     *
     * @return array An array of categories, each containing a label and value.
     */
    public static function get_categories(): array {
        global $DB;
        $language = current_language();

        $sql = "
            SELECT ec.uuid AS value, COALESCE(ect.title, ec.title) AS label
            FROM {edflex_categories} ec
            LEFT JOIN {edflex_category_translations} ect
                ON ect.category_id = ec.id AND ect.language_code = :language_code
            ORDER BY label
        ";

        $categories = $DB->get_records_sql($sql, ['language_code' => $language]) ?: [];

        return array_values($categories);
    }
}
