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
 * Backup task for mod_edflex.
 *
 * @package     mod_edflex
 * @copyright   2025 Edflex <support@edflex.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/edflex/backup/moodle2/backup_edflex_stepslib.php');

/**
 * Provides the steps to perform one complete backup of the edflex instance.
 *
 * @package     mod_edflex
 * @copyright   2025 Edflex <support@edflex.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_edflex_activity_task extends backup_activity_task {

    /**
     * Defines particular settings for this activity task. None required.
     *
     * @return void
     */
    protected function define_my_settings() {
    }

    /**
     * Defines particular steps for this activity backup.
     *
     * @return void
     */
    protected function define_my_steps() {
        $this->add_step(new backup_edflex_activity_structure_step('edflex_structure', 'edflex.xml'));
    }

    /**
     * Encodes URLs to the view and index scripts so they can be decoded on restore.
     *
     * @param string $content The content to encode.
     *
     * @return string The content with encoded links.
     */
    public static function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, '/');

        $search = '/(' . $base . '\/mod\/edflex\/view\.php\?id\=)([0-9]+)/';
        $content = preg_replace($search, '$@EDFLEXVIEWBYID*$2@$', $content);

        $search = '/(' . $base . '\/mod\/edflex\/index\.php\?id\=)([0-9]+)/';
        $content = preg_replace($search, '$@EDFLEXINDEX*$2@$', $content);

        return $content;
    }
}
