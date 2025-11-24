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
 * Synchronize categories task.
 *
 * @package     mod_edflex
 * @copyright   2025 Edflex <support@edflex.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_edflex\task;

use core\task\scheduled_task;
use mod_edflex\api\client;
use mod_edflex\local\category_manager;
use moodle_exception;
use Throwable;

/**
 * Task for synchronizing categories.
 *
 * @package     mod_edflex
 * @copyright   2025 Edflex
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class synchronize_categories_task extends scheduled_task {
    /**
     * Retrieves the name of the synchronization categories task.
     *
     * @return string The localized name of the synchronization categories task.
     */
    public function get_name(): string {
        return get_string('synchronizecategoriestask', 'mod_edflex');
    }

    /**
     * Executes the synchronization of all catalogs using the category manager.
     *
     * @return void
     */
    public function execute(): void {
        try {
            if (!$this->get_client()->can_connect_to_the_api()) {
                throw new moodle_exception('apiconnectionerror', 'mod_edflex');
            }
        } catch (Throwable $ex) {
            mtrace('WARNING! Could not connect to Edflex API: ' . $ex->getMessage() .
                '. Skipping categories synchronization...');
            return;
        }

        try {
            $this->get_category_manager()->synchronize_all_catalogs();
        } catch (Throwable $ex) {
            mtrace('ERROR! An exception occurred during categories synchronization: ' . $ex->getMessage());
        }
    }

    /**
     * Retrieves a client instance. If the client instance does not already exist,
     * it initializes the client using the configuration and stores it statically.
     */
    public function get_client(): client {
        static $client;

        if (empty($client)) {
            $client = client::from_config();
        }

        return $client;
    }

    /**
     * Retrieves an instance of the category manager. If the instance does not already exist,
     * it initializes a new category manager using the current client.
     */
    public function get_category_manager(): category_manager {
        static $categorymanager;

        if (empty($categorymanager)) {
            $categorymanager = new category_manager($this->get_client());
        }

        return $categorymanager;
    }
}
