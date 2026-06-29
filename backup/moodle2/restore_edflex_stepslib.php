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
 * Restore steps for mod_edflex.
 *
 * @package     mod_edflex
 * @copyright   2025 Edflex <support@edflex.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Defines the structure step to restore one edflex activity.
 *
 * @package     mod_edflex
 * @copyright   2025 Edflex <support@edflex.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_edflex_activity_structure_step extends restore_activity_structure_step {

    /**
     * Defines the structure to be restored for the edflex activity.
     *
     * @return mixed The prepared activity structure.
     */
    protected function define_structure() {
        $paths = [];
        $paths[] = new restore_path_element('edflex', '/activity/edflex');

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restores one edflex instance from the backup data.
     *
     * @param array $data The edflex record to restore.
     *
     * @return void
     */
    protected function process_edflex($data) {
        global $DB;

        $data = (object) $data;
        $data->course = $this->get_courseid();
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('edflex', $data);
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Re-adds the files belonging to the restored instance once execution completes.
     *
     * @return void
     */
    protected function after_execute() {
        $this->add_related_files('mod_edflex', 'intro', null);
    }
}
