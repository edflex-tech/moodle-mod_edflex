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
 *
 * @param string $feature The feature.
 *
 * @return mixed True if module supports feature, null if doesn't know
 */
function edflex_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
        case FEATURE_SHOW_DESCRIPTION:
        case FEATURE_BACKUP_MOODLE2:
            return true;
        default:
            return null;
    }
}

/**
 * Adds a new instance of the edflex module.
 *
 * @param object $moduleinstance The module instance.
 * @param mod_page_mod_form $mform The form.
 *
 * @return bool
 */
function edflex_add_instance($moduleinstance, $mform = null) {
    // The mod_edflex form is a launcher: it spawns SCORM modules via the
    // Edflex browser, so no standalone edflex instance is ever persisted.
    return false;
}

/**
 * Updates an existing instance of the edflex module.
 *
 * @param object $moduleinstance The module instance.
 * @param mod_page_mod_form $mform The form.
 *
 * @return bool
 */
function edflex_update_instance($moduleinstance, $mform = null) {
    return false;
}

/**
 * Removes an instance of the mod_edflex from the database.
 *
 * @param int $id The instance id.
 *
 * @return bool
 */
function edflex_delete_instance($id) {
    global $DB;

    if (!$DB->get_record('edflex', ['id' => $id])) {
        return false;
    }

    $DB->delete_records('edflex', ['id' => $id]);

    return true;
}



/**
 * Extends the course navigation with additional functionality for the edflex module.
 *
 * @param object $navigation The navigation node.
 * @param object $course The course.
 * @param object $context The context.
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
 * Legacy before_footer callback for Moodle < 4.4.
 *
 * On Moodle 4.4+ the {@see \core\hook\output\before_footer_html_generation} hook
 * registered in db/hooks.php supersedes this and this function is not called.
 *
 * @return string|null
 */
function mod_edflex_before_footer() {
    return \mod_edflex\hook_callbacks::scorm_footer_html();
}
