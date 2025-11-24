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

use Exception;
use mod_edflex\api\client;
use moodle_exception;
use stdClass;

/**
 * Manages Edflex categories.
 *
 * @package     mod_edflex
 * @copyright   2025 Edflex <support@edflex.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class category_manager {
    /**
     * @var client
     */
    private $client;

    /**
     * Constructor
     *
     * @param client|null $client
     */
    public function __construct(?client $client = null) {
        $this->client = $client ?: client::from_config();
    }

    /**
     * Synchronizes Edflex categories.
     */
    public function synchronize_all_catalogs(): void {
        global $DB;
        $perpage = 16;
        $now = time();
        $catalogs = $this->client->get_catalogs()['data'] ?? [];

        foreach ($catalogs as $catalog) {
            $catalogid = $catalog['id'];
            $currentpage = 1;

            do {
                $response = $this->client->get_categories(
                    $catalogid,
                    ['nestingLevel' => 1],
                    $currentpage++,
                    $perpage
                );
                $apicategories = $response['data'] ?? [];
                $this->sync_categories($apicategories, $catalogid);
                $this->sync_category_translations($apicategories);
            } while ($response['links']['next'] ?? false);
        }

        $DB->delete_records_select(
            'edflex_categories',
            'lastsync < :lastsync',
            ['lastsync' => $now]
        );

        $this->delete_orphaned_edflex_category_translations();
    }

    /**
     * Synchronizes categories with the database based on given API categories.
     *
     * This method updates or inserts category records in the database based on the provided API categories input.
     * It also marks the synchronized categories with a timestamp indicating the last sync operation.
     *
     * @param array $apicategories Array of API category data containing category UUID, title, and position.
     * @param string $catalogid UUID of the catalog to which the categories belong.
     */
    public function sync_categories(array $apicategories, string $catalogid): void {
        global $DB;

        $categoryids = array_column($apicategories, 'id');

        if (empty($categoryids)) {
            return;
        }

        $transaction = $DB->start_delegated_transaction();

        try {
            [$insql, $inparams] = $DB->get_in_or_equal($categoryids, SQL_PARAMS_NAMED, 'p');
            $dbcategories = $DB->get_records_sql(
                "SELECT * FROM {edflex_categories} WHERE uuid $insql",
                $inparams
            );
            $dbcategories = array_combine(array_column($dbcategories, 'uuid'), $dbcategories);
            $catins = $catupd = [];
            $now = time();

            foreach ($apicategories as $category) {
                $newtitle = $category['attributes']['title']['default'] ?? '';
                $newposition = $category['attributes']['position'] ?? 0;
                $record = $dbcategories[$category['id']] ?? null;

                if (empty($record)) {
                    $record = new stdClass();
                    $record->uuid = $category['id'];
                    $record->catalog_uuid = $catalogid;
                    $record->title = $newtitle;
                    $record->position = $newposition;
                    $record->lastsync = $now;
                    $catins[] = $record;
                } else {
                    $haschanges = $record->title !== $newtitle || $record->position !== $newposition;

                    if ($haschanges) {
                        $record->title = $newtitle;
                        $record->position = $newposition;
                        $catupd[] = $record;
                    }
                }
            }

            if (!empty($catins)) {
                $DB->insert_records('edflex_categories', $catins);
            }

            if (!empty($catupd)) {
                foreach ($catupd as $record) {
                    $DB->update_record('edflex_categories', $record);
                }
            }

            [$insql, $inparams] = $DB->get_in_or_equal($categoryids, SQL_PARAMS_NAMED, 'p');
            $DB->set_field_select(
                "edflex_categories",
                'lastsync',
                $now,
                "uuid $insql",
                $inparams
            );

            $transaction->allow_commit();
        } catch (Exception $e) {
            $transaction->rollback($e);
        }
    }

    /**
     * Synchronizes category translation data between an external API source and the database.
     * It updates or inserts translation records for specific categories based on their language codes and titles.
     *
     * @param array $apicategories Array of category data retrieved from an external API.
     *                             Each category contains an 'id', and an 'attributes' array with
     *                             translations mapped by language codes.
     *
     * @return void This method does not return a value but performs database operations to sync data.
     */
    public function sync_category_translations(array $apicategories) {
        global $DB;

        $categoryids = array_column($apicategories, 'id');

        if (empty($categoryids)) {
            return;
        }

        $transaction = $DB->start_delegated_transaction();

        try {
            [$insql, $inparams] = $DB->get_in_or_equal($categoryids, SQL_PARAMS_NAMED, 'p');
            $categoryuuidmap = $DB->get_records_sql(
                "
                    SELECT ec.id, ec.uuid FROM {edflex_categories} ec
                    WHERE ec.uuid $insql
                ",
                $inparams
            );

            $categoryuuidmap = array_combine(
                array_column($categoryuuidmap, 'uuid'),
                array_column($categoryuuidmap, 'id')
            );

            $dbtranslations = $DB->get_records_sql(
                "
                    SELECT ect.id, ect.title, ect.language_code, ec.uuid FROM {edflex_category_translations} ect
                    INNER JOIN {edflex_categories} ec ON ec.id = ect.category_id
                    WHERE ec.uuid $insql
                ",
                $inparams
            );

            $keys = array_map(static function ($tr): string {
                return "{$tr->uuid}:{$tr->language_code}";
            }, $dbtranslations);

            $dbtranslations = array_combine($keys, $dbtranslations);

            $insert = $update = [];

            foreach ($apicategories as $apicategory) {
                $titles = $apicategory['attributes']['title'] ?? [];
                unset($titles['default']);
                $uuid = $apicategory['id'];

                if (empty($categoryuuidmap[$uuid])) {
                    throw new moodle_exception('invalidapicategoryid', 'mod_edflex');
                }

                foreach ($titles as $languagecode => $title) {
                    $dbrecord = $dbtranslations["$uuid:$languagecode"] ?? null;

                    if (empty($dbrecord)) {
                        $record = new stdClass();
                        $record->category_id = $categoryuuidmap[$uuid];
                        $record->language_code = $languagecode;
                        $record->title = $title;
                        $insert[] = $record;
                    } else if ($dbrecord->title !== $title) {
                        $record = new stdClass();
                        $record->id = $dbrecord->id;
                        $record->title = $title;
                        $update[] = $record;
                    }
                }
            }

            if (!empty($insert)) {
                $DB->insert_records('edflex_category_translations', $insert);
            }

            if (!empty($update)) {
                foreach ($update as $record) {
                    $DB->update_record('edflex_category_translations', $record);
                }
            }

            $transaction->allow_commit();
        } catch (Exception $e) {
            $transaction->rollback($e);
        }
    }

    /**
     * Deletes orphaned edflex category translations with no associated categories.
     *
     * This method finds and deletes records in the 'edflex_category_translations' table
     * that do not have a valid associated category in the 'edflex_categories' table.
     *
     * @return int The number of deleted orphaned records.
     */
    public function delete_orphaned_edflex_category_translations(): int {
        global $DB;

        $orphanedrecords = $DB->get_records_sql('
            SELECT ect.id
            FROM {edflex_category_translations} ect
            LEFT JOIN {edflex_categories} ec ON ec.id = ect.category_id
            WHERE ec.id IS NULL
        ');

        if (empty($orphanedrecords)) {
            return 0;
        }

        $ids = array_keys($orphanedrecords);
        $DB->delete_records_list('edflex_category_translations', 'id', $ids);

        return count($ids);
    }
}
