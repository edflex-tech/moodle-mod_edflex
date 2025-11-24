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

namespace mod_edflex\api;

use mod_edflex\util\formatter;

/**
 * Service class for mapping Edflex contents with Moodle activities.
 *
 * @package     mod_edflex
 * @copyright   2025 Edflex <support@edflex.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mapper {
    /**
     * Maps each element of the input array using the map_content method.
     *
     * @param array $contents The array of contents to be mapped.
     * @return array The array of mapped contents.
     */
    public static function map_contents(array $contents): array {
        foreach ($contents as $idx => $content) {
            $contents[$idx] = self::map_content($content);
        }

        return $contents;
    }

    /**
     * Normalizes and formats the content data retrieved from the Edflex API.
     *
     * Transforms the raw API response into a standardized structure suitable for use
     * across different parts of the plugin, such as importing activities into Moodle
     * courses or displaying Edflex search results within the plugin interface.
     *
     * @param array $content The raw content array retrieved from the Edflex API.
     *
     * @return array The normalized content data ready for internal use.
     */
    public static function map_content(array $content): array {
        $type = $content['attributes']['type'] ?? null;
        $duration = $content['attributes']['duration'] ?? '';
        $difficulty = $content['attributes']['difficulty'] ?? null;
        $author = $content['attributes']['author']['fullName']
            ?? $content['attributes']['creator']['name']
            ?? '';

        return [
            'intro' => $content['attributes']['description'] ?? '',
            'edflexid' => $content['id'] ?? null,
            'name' => $content['attributes']['title'] ?? null,
            'language' => $content['attributes']['language'] ?? null,
            'difficulty' => $difficulty,
            'difficulty_formatted' => formatter::format_difficulty($difficulty),
            'type' => $type,
            'type_formatted' => formatter::format_type($type),
            'duration' => $duration,
            'duration_formatted' => formatter::format_duration($duration),
            'author' => $author,
            'downloadscormzip' => $content['links']['downloadScormZip'] ?? null,
            'image_small' => $content['attributes']['image']['smallUrl'] ?? null,
            'image_medium' => $content['attributes']['image']['mediumUrl'] ?? null,
            'image_big' => $content['attributes']['image']['bigUrl'] ?? null,
            'url' => $content['attributes']['url'] ?? '',
        ];
    }
}
