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
 * Restore task for mod_edflex.
 *
 * @package     mod_edflex
 * @copyright   2025 Edflex <support@edflex.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/edflex/backup/moodle2/restore_edflex_stepslib.php');

/**
 * Restore task for the edflex activity module.
 *
 * @package     mod_edflex
 * @copyright   2025 Edflex <support@edflex.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_edflex_activity_task extends restore_activity_task {

    /**
     * Defines particular settings for this activity task. None required.
     *
     * @return void
     */
    protected function define_my_settings() {
    }

    /**
     * Defines particular steps for this activity restore.
     *
     * @return void
     */
    protected function define_my_steps() {
        $this->add_step(new restore_edflex_activity_structure_step('edflex_structure', 'edflex.xml'));
    }

    /**
     * Defines the contents in the activity that must be processed by the link decoder.
     *
     * @return array The decode contents.
     */
    public static function define_decode_contents() {
        $contents = [];
        $contents[] = new restore_decode_content('edflex', ['intro'], 'edflex');

        return $contents;
    }

    /**
     * Defines the decoding rules for links belonging to the activity to be executed by the link decoder.
     *
     * @return array The decode rules.
     */
    public static function define_decode_rules() {
        $rules = [];
        $rules[] = new restore_decode_rule('EDFLEXVIEWBYID', '/mod/edflex/view.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('EDFLEXINDEX', '/mod/edflex/index.php?id=$1', 'course');

        return $rules;
    }

    /**
     * Defines the restore log rules that will be applied for the activity logs.
     *
     * @return array The restore log rules.
     */
    public static function define_restore_log_rules() {
        $rules = [];
        $rules[] = new restore_log_rule('edflex', 'view', 'view.php?id={course_module}', '{edflex}');

        return $rules;
    }
}
