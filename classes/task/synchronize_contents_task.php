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
 * Synchronize contents task.
 *
 * @package     mod_edflex
 * @copyright   2025 Edflex <support@edflex.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_edflex\task;

use core\task\scheduled_task;
use mod_edflex\api\client;
use mod_edflex\local\content_synchronizer;
use moodle_exception;
use Throwable;

/**
 * Task for synchronizing contents.
 *
 * @package     mod_edflex
 * @copyright   2025 Edflex
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class synchronize_contents_task extends scheduled_task {
    /**
     * Retrieves the name of the content synchronization task.
     *
     * @return string The localized name of the content synchronization task.
     */
    public function get_name(): string {
        return get_string('synchronizecontentstask', 'mod_edflex');
    }

    /**
     * Executes the synchronization of all imported Edflex contents.
     */
    public function execute(): void {
        try {
            if (!$this->get_client()->can_connect_to_the_api()) {
                throw new moodle_exception('apiconnectionerror', 'mod_edflex');
            }
        } catch (Throwable $ex) {
            mtrace('WARNING! Could not connect to Edflex API: ' . $ex->getMessage() .
                '. Skipping contents synchronization...');
            return;
        }

        try {
            $outdatedcontentstime = time() - 22 * 60 * 60;
            $this->get_synchronizer()->synchronize_all_contents($outdatedcontentstime);
        } catch (Throwable $ex) {
            mtrace(
                'ERROR! An exception occurred during contents synchronization: ' .
                $ex->getMessage() . "\n" . $ex->getTraceAsString()
            );
        }
    }

    /**
     * Retrieves a client instance. If the client instance is not already created, it initializes
     * it using a configuration and returns the initialized instance.
     *
     * @return client The client instance.
     */
    public function get_client(): client {
        static $client;

        if (empty($client)) {
            $client = client::from_config();
        }

        return $client;
    }

    /**
     * Retrieves a synchronizer instance. If the synchronizer instance is not already created, it initializes
     * it using a client instance and returns the initialized synchronizer.
     *
     * @return content_synchronizer The synchronizer instance.
     */
    public function get_synchronizer(): content_synchronizer {
        static $synchronizer;

        if (empty($synchronizer)) {
            $synchronizer = new content_synchronizer($this->get_client());
        }

        return $synchronizer;
    }
}
