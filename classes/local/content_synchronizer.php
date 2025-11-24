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

namespace mod_edflex\local;

use mod_edflex\api\client;
use mod_edflex\api\mapper;

/**
 * Synchronizes imported Edflex contents.
 *
 * @package     mod_edflex
 * @copyright   2025 Edflex <support@edflex.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class content_synchronizer {
    /**
     * @var client
     */
    private $client;

    /**
     * @var activity_manager
     */
    private $activitymanager;

    /**
     * Constructor
     *
     * @param client|null $client
     */
    public function __construct(?client $client = null) {
        $this->client = $client ?: client::from_config();
        $this->activitymanager = new activity_manager($this->client);
    }

    /**
     * Synchronizes all contents by fetching outdated content IDs, processing their updates,
     * and handling deletions if necessary.
     *
     * @param int $maxlastsync The maximum timestamp for the last synchronization.
     *                                   Only content modified after this timestamp will be synchronized.
     * @param int|null $maxrecordstosync Optional parameter specifying the maximum number
     *                                   of records to sync. If null, no limit is applied.
     *
     * @return void
     */
    public function synchronize_all_contents(int $maxlastsync, ?int $maxrecordstosync = null): void {
        $contentidschunks = $this->activitymanager->get_outdated_edflex_contentids_in_chunks($maxlastsync, $maxrecordstosync, 200);

        foreach ($contentidschunks as $contentids) {
            $contents = iterator_to_array($this->client->get_contents_by_ids($contentids));
            $contents = mapper::map_contents($contents);
            $this->activitymanager->update_imported_activities_from_contents($contents);
            $deletedcontentids = [];

            foreach ($contentids as $contentid) {
                if (empty($contents[$contentid])) {
                    $deletedcontentids[] = $contentid;
                }
            }

            if (!empty($deletedcontentids)) {
                $this->activitymanager->delete_scorms_by_contentids($deletedcontentids);
            }
        }
    }
}
