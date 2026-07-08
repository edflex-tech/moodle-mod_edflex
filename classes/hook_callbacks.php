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

namespace mod_edflex;

use core\hook\output\before_footer_html_generation;
use mod_edflex\output\edflex_scorm;

/**
 * Hook callbacks for mod_edflex.
 *
 * @package     mod_edflex
 * @copyright   2025 Edflex <support@edflex.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Add edflex content before the footer on SCORM module pages.
     *
     * @param before_footer_html_generation $hook
     */
    public static function before_footer_html_generation(before_footer_html_generation $hook): void {
        $hook->add_html(self::scorm_footer_html());
    }

    /**
     * Render the edflex content injected before the footer on SCORM module pages.
     *
     * @return string|null Rendered HTML, or null when the current page is not a mapped edflex SCORM.
     */
    public static function scorm_footer_html(): ?string {
        global $PAGE, $DB, $OUTPUT;

        if ($PAGE->cm && $PAGE->cm->modname === 'scorm') {
            if ($edflex = $DB->get_record('edflex_scorm', ['scormid' => $PAGE->cm->instance])) {
                return $OUTPUT->render(new edflex_scorm($edflex));
            }
        }

        return null;
    }
}
