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
 * Event listeners.
 *
 * @package     mod_edflex
 * @copyright   2025 Edflex <support@edflex.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_edflex;

use cache;
use core\event\config_log_created;
use core\event\course_module_deleted;
use core\task\manager;
use Exception;
use mod_edflex\api\client;
use mod_edflex\local\activity_manager;
use mod_edflex\task\adhoc_synchronize_categories_task;
use moodle_exception;

/**
 * Events observer
 */
class observer {
    /**
     * Event listener for \core\event\config_log_created
     */
    public static function config_changed(config_log_created $event): void {
        $data = $event->get_data();
        $pluginname = $data['other']['plugin'] ?? null;
        $hasedflexsettingschanged = 'mod_edflex' === $pluginname;

        if (!$hasedflexsettingschanged) {
            return;
        }

        self::invalidate_access_token();
        self::create_or_delete_adhoc_synchronize_categories_task();
    }

    /**
     * Event listener for \core\event\course_module_deleted
     */
    public static function course_module_deleted(course_module_deleted $event, ?activity_manager $activitymanager = null) {
        try {
            if (null === $activitymanager) {
                $activitymanager = new activity_manager();
            }

            $activitymanager->delete_orphaned_edflex_scorms();
        } catch (Exception $e) {
            mtrace('WARNING! delete_orphaned_edflex_scorms failed. ' . $e->getMessage());
        }
    }

    /**
     * Cleans the access_token if the settings changed.
     */
    private static function invalidate_access_token(): void {
        $cache = cache::make('mod_edflex', 'api');

        if ($cache->has('access_token')) {
            $cache->delete('access_token');
        }
    }

    /**
     * Creates or deletes an ad-hoc task to synchronize categories based on the API connection status.
     */
    public static function create_or_delete_adhoc_synchronize_categories_task(?client $client = null): void {
        global $DB;

        $task = new adhoc_synchronize_categories_task();
        $classname = '\\' . get_class($task);

        try {
            if (!$client) {
                $client = client::from_config();
            }

            if ($client->can_connect_to_the_api()) {
                manager::reschedule_or_queue_adhoc_task($task);
            } else {
                throw new moodle_exception('apiconnectionerror', 'mod_edflex');
            }
        } catch (Exception $exception) {
            $DB->delete_records('task_adhoc', ['classname' => $classname]);

            return;
        }
    }
}
