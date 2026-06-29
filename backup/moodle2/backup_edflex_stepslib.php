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
 * Backup steps for mod_edflex.
 *
 * @package     mod_edflex
 * @copyright   2025 Edflex <support@edflex.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Defines the complete edflex structure for backup, with file annotations.
 *
 * @package     mod_edflex
 * @copyright   2025 Edflex <support@edflex.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_edflex_activity_structure_step extends backup_activity_structure_step {

    /**
     * Defines the backup structure of the edflex activity.
     *
     * @return backup_nested_element The prepared activity structure to back up.
     */
    protected function define_structure() {
        $edflex = new backup_nested_element('edflex', ['id'], [
            'name', 'intro', 'introformat', 'timemodified',
        ]);

        $edflex->set_source_table('edflex', ['id' => backup::VAR_ACTIVITYID]);

        $edflex->annotate_files('mod_edflex', 'intro', null);

        return $this->prepare_activity_structure($edflex);
    }
}
