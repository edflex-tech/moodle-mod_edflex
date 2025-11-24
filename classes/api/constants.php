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

/**
 * Class constants
 *
 * Defines constants for Edflex content, such as types, difficulties, and durations.
 *
 * @package     mod_edflex
 * @copyright   2025 Edflex <support@edflex.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class constants {
    /**
     * Edflex content types
     */
    const CONTENT_TYPES = [
        'program' => 'contenttypeprogram',
        'article' => 'contenttypearticle',
        'video' => 'contenttypevideo',
        'mooc' => 'contenttypecourse',
        'role-play' => 'contenttyperoleplay',
        'interactive' => 'contenttypeinteractive',
        'top-voice' => 'contenttypetopvoice',
        'assessment' => 'contenttypeassessment',
        'podcast' => 'contenttypepodcast',
    ];

    /**
     * Edflex content levels
     */
    const CONTENT_LEVELS = [
        'introductive' => 'difficultyintroductive',
        'intermediate' => 'difficultyintermediate',
        'advanced' => 'difficultyadvanced',
    ];

    /**
     * Supported content languages
     */
    const CONTENT_LANGUAGES = [
        'de', 'en', 'ar', 'zh', 'ko', 'es', 'fr', 'hu', 'it', 'ja', 'km', 'nl', 'pl', 'pt', 'ru', 'sk', 'vi',
        'el', 'he', 'hi', 'id', 'ro', 'sv', 'tr',
    ];

    /**
     * Retrieves an array of content type options, each containing a value and a localized label.
     *
     * @return array An array of associative arrays, where each associative array includes:
     *               - 'value': The content type value.
     *               - 'label': The localized label for the content type.
     */
    public static function get_content_types_options(): array {
        return array_map(
            static function (string $value, string $label) {
                return [
                    'value' => $value,
                    'label' => get_string($label, 'mod_edflex'),
                ];
            },
            array_keys(self::CONTENT_TYPES),
            array_values(self::CONTENT_TYPES)
        );
    }
}
