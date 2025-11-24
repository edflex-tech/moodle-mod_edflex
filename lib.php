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
 * Library of interface functions and constants.
 *
 * @package     mod_edflex
 * @copyright   2025 Edflex <support@edflex.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_edflex\output\edflex_scorm;

defined('MOODLE_INTERNAL') || die();

global $CFG;

/**
 * Return if the plugin supports $feature.
 */
function edflex_supports($feature) {
    return null;
}

/**
 * Adds a new instance of the edflex module.
 */
function edflex_add_instance($moduleinstance, $mform = null) {
    return false;
}

/**
 * Updates an existing instance of the edflex module.
 */
function edflex_update_instance($moduleinstance, $mform = null) {
    return false;
}

/**
 * Removes an instance of the mod_edflex from the database.
 */
function edflex_delete_instance($id) {
    return false;
}



/**
 * Extends the course navigation with additional functionality for the edflex module.
 */
function mod_edflex_extend_navigation_course($navigation, $course, $context) {
    mod_edflex_inject_browser_javascript();
}

/**
 * Injects the necessary JavaScript and language strings for the Edflex browser modal functionality.
 *
 * This function initializes the browser modal using AMD JavaScript and provides a set
 * of language strings required for the functionality.
 *
 * @return void
 */
function mod_edflex_inject_browser_javascript() {
    global $PAGE;

    $PAGE->requires->js_call_amd('mod_edflex/initbrowsermodal', 'init');
    $PAGE->requires->strings_for_js([
        'category',
        'confirmsameactivitymultipletimesinthecourse',
        'contenttype',
        'contenttypeprogram',
        'contenttypearticle',
        'contenttypevideo',
        'contenttypecourse',
        'contenttypepodcast',
        'contenttyperoleplay',
        'contenttypeinteractive',
        'contenttypetopvoice',
        'contenttypeassessment',
        'difficultyintroductive',
        'difficultyintermediate',
        'difficultyadvanced',
        'duration',
        'keywordssearch',
        'level',
        'language',
        'edflexbrowsertitle',
        'edflexbrowserloading',
    ], 'mod_edflex');
}

/**
 * Render additional content before the footer in SCORM module pages.
 *
 * Checks if the current course module is a SCORM activity and retrieves
 * associated edflex information from the database. If a record is found,
 * it outputs the rendered content using the appropriate renderer.
 */
function mod_edflex_before_footer() {
    global $PAGE, $DB, $OUTPUT;

    if ($PAGE->cm && $PAGE->cm->modname === 'scorm') {
        $scormid = $PAGE->cm->instance;

        if ($edflex = $DB->get_record('edflex_scorm', ['scormid' => $scormid])) {
            echo $OUTPUT->render(new edflex_scorm($edflex));
        }
    }
}
