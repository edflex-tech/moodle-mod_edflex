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

namespace mod_edflex\util;

use DateInterval;
use Exception;
use mod_edflex\api\constants;

/**
 * Service class for formatting contents fields.
 *
 * @package     mod_edflex
 * @copyright   2025 Edflex <support@edflex.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class formatter {
    /**
     * Convert an ISO 8601 duration into a human-readable string.
     *
     * @param ?string $isoduration (e.g. "P2DT3H")
     *
     * @return string
     */
    public static function format_duration(?string $isoduration = null): string {
        if (empty($isoduration)) {
            return '';
        }

        try {
            $interval = new DateInterval($isoduration);
            $seconds = $interval->d * 86400 + $interval->h * 3600 + $interval->i * 60 + $interval->s;
            return format_time($seconds);
        } catch (Exception $e) {
            return $isoduration;
        }
    }

    /**
     * Returns the translated label for a content type slug.
     *
     * @param ?string $type The content type slug.
     *
     * @return string The translated label.
     */
    public static function format_type(?string $type): string {
        if (!$type) {
            return '';
        }

        $stringid = constants::CONTENT_TYPES[$type] ?? null;

        if (!$stringid) {
            return $type;
        }

        return get_string($stringid, 'mod_edflex');
    }

    /**
     * Returns the translated label for difficulty slug.
     *
     * @param ?string $difficulty The difficulty slug.
     *
     * @return string The translated label.
     */
    public static function format_difficulty(?string $difficulty): string {
        if (!$difficulty) {
            return '';
        }

        $stringid = constants::CONTENT_LEVELS[$difficulty] ?? null;

        return $stringid ? get_string($stringid, 'mod_edflex') : $difficulty;
    }
}
