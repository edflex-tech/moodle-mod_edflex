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

use context_module;
use Generator;
use mod_edflex\api\client;
use moodle_exception;
use stdClass;
use Throwable;

/**
 * Manages activities within the Moodle ecosystem, integrating with Edflex.
 *
 * @package     mod_edflex
 * @copyright   2025 Edflex <support@edflex.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activity_manager {
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
        $this->client = $client;
    }

    /**
     * Imports multiple Edflex contents to Moodle
     *
     * @param array $edflexcontents The Edflex contents to import.
     * @param int $course The course ID.
     * @param int $section The section ID.
     *
     * @return array The results of the import.
     */
    public function import_contents(array $edflexcontents, int $course, int $section): array {
        $results = [];

        foreach ($edflexcontents as $edflexcontent) {
            $results[] = $this->import_content($edflexcontent, $course, $section);
        }

        return $results;
    }

    /**
     * Imports an activity from Edflex in the course
     *
     * @param array $datafromedflex The Edflex content to import.
     * @param int $courseid The course ID.
     * @param int $section The section ID.
     *
     * @return array The result of the import.
     */
    public function import_content(array $datafromedflex, int $courseid, int $section = 0): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->dirroot . '/course/format/lib.php');
        require_once($CFG->dirroot . '/mod/scorm/mod_form.php');

        $course = get_course($courseid);

        if (empty($course)) {
            throw new moodle_exception('invalidcourseid', 'mod_edflex');
        }

        if (empty($datafromedflex['downloadscormzip'])) {
            throw new moodle_exception('downloadscormzipmissing', 'mod_edflex');
        }

        $module = $DB->get_record('modules', ['name' => 'scorm'], 'id', MUST_EXIST);
        $scorm = new stdClass();
        $scorm->modulename = 'scorm';
        $scorm->course = $courseid;
        $scorm->section = $section;
        $scorm->visible = 1;
        $scorm->scormtype = SCORM_TYPE_LOCAL;
        $scorm->width = 100;
        $scorm->height = 600;
        $scorm->skipview = 2;
        $scorm->maxattempt = 0;
        $scorm->hidetoc = 1;
        $scorm->module = $module->id;
        $scorm->name = $datafromedflex['name'];
        $scorm->intro = $datafromedflex['intro'];
        $scorm->introformat = FORMAT_HTML;
        $scorm->packagefile = '';
        $scorm->cmidnumber = null;

        $cm = add_moduleinfo($scorm, $course);
        $scormbinary = $this->get_client()->get_scorm($datafromedflex['downloadscormzip']);

        $scorm = $DB->get_record('scorm', ['id' => $cm->instance]);
        $scorm->cmid = $cm->coursemodule;
        $this->store_scorm_package($scorm, $scormbinary);

        $edflexscorm = new stdClass();
        $edflexscorm->edflexid = $datafromedflex['edflexid'];
        $edflexscorm->name = $datafromedflex['name'];
        $edflexscorm->url = $datafromedflex['url'];
        $edflexscorm->type = $datafromedflex['type'];
        $edflexscorm->language = $datafromedflex['language'];
        $edflexscorm->difficulty = $datafromedflex['difficulty'];
        $edflexscorm->duration = $datafromedflex['duration'];
        $edflexscorm->author = $datafromedflex['author'];
        $edflexscorm->downloadscormzip = $datafromedflex['downloadscormzip'];
        $edflexscorm->scormid = $cm->instance;
        $edflexscorm->lastsync = time();

        $edflexscorm->id = $DB->insert_record('edflex_scorm', $edflexscorm);

        return [$cm, $edflexscorm];
    }

    /**
     * Delete orphaned edflex_scorm records.
     *
     * A record is considered orphaned if its scormid does not exist in the scorm table.
     *
     * @return int Number of deleted records.
     */
    public function delete_orphaned_edflex_scorms(): int {
        global $DB;

        // Fetch all orphaned records (where scormid is set but does not exist in scorm table).
        $orphanedrecords = $DB->get_records_sql('
            SELECT e.id
            FROM {edflex_scorm} e
            LEFT JOIN {scorm} s ON e.scormid = s.id
            WHERE e.scormid IS NOT NULL AND s.id IS NULL
        ');

        if (empty($orphanedrecords)) {
            return 0;
        }

        $ids = array_keys($orphanedrecords);

        // Delete the orphaned records.
        $DB->delete_records_list('edflex_scorm', 'id', $ids);

        return count($ids);
    }

    /**
     * Retrieves a list of Edflex IDs associated with SCORM packages in a specified course.
     *
     * @param int|string $courseid The ID of the course for which Edflex IDs will be fetched.
     *
     * @return array An array of Edflex IDs associated with SCORM packages in the given course.
     */
    public function get_edflexids_in_the_course($courseid): array {
        global $DB;

        $edflexids = $DB->get_fieldset_sql(
            '
                SELECT edflexid FROM {edflex_scorm} es
                INNER JOIN {scorm} s ON s.id = es.scormid
                INNER JOIN {course_modules} cm ON cm.instance = s.id AND cm.deletioninprogress = 0
                WHERE s.course = :course
            ',
            ['course' => $courseid]
        );

        return $edflexids ?? [];
    }

    /**
     * Retrieves outdated Edflex content IDs in chunks based on the `lastsync` timestamp.
     *
     * This method fetches Edflex content IDs from the database where the `lastsync` timestamp
     * is less than the provided maximum value (`maxlastsync`). The results are retrieved in
     * chunks of a specified size and yield one chunk of IDs at a time.
     *
     * @param int $maxlastsync The maximum timestamp value for the `lastsync` field to consider an Edflex content as outdated.
     * @param int|null $limit Optional limit to the maximum number of IDs to retrieve.
     * @param int|null $chunksize The size of each chunk to be fetched. Defaults to 1000.
     *
     * @return Generator Yields arrays of Edflex content IDs, each with a size up to the specified chunk size.
     */
    public function get_outdated_edflex_contentids_in_chunks(
        int $maxlastsync,
        ?int $limit = null,
        ?int $chunksize = 1000
    ): Generator {
        global $DB;

        $offset = 0;

        while (true) {
            $contentids = $DB->get_fieldset_sql(
                "
                    SELECT distinct es.edflexid FROM {edflex_scorm} es
                    INNER JOIN {scorm} s ON s.id = es.scormid
                    INNER JOIN {course_modules} cm ON cm.instance = s.id AND cm.deletioninprogress = 0
                    WHERE es.lastsync < :maxlastsync
                    ORDER BY es.lastsync, es.id
                    LIMIT $chunksize OFFSET $offset
                ",
                [
                    'limit' => $chunksize,
                    'offset' => $offset,
                    'maxlastsync' => $maxlastsync,
                ]
            );

            yield $contentids;

            if (count($contentids) < $chunksize) {
                break;
            }

            if ($limit && ($offset < $limit)) {
                break;
            }

            $offset += $chunksize;
        }
    }

    /**
     * Deletes SCORM modules by given content IDs.
     *
     * This function removes SCORM modules associated with the given content IDs from the database
     * and deletes their corresponding course modules.
     *
     * @param array $deletedcontentids Array of content IDs whose associated SCORM modules are to be deleted.
     *
     * @return void
     */
    public function delete_scorms_by_contentids(array $deletedcontentids): void {
        global $CFG, $DB;

        [$insql, $inparams] = $DB->get_in_or_equal($deletedcontentids, SQL_PARAMS_NAMED, 'p');

        $sql = "SELECT cm.id AS cmid
                  FROM {edflex_scorm} es
                  JOIN {scorm} s ON s.id = es.scormid
                  JOIN {course_modules} cm ON cm.instance = es.scormid
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'scorm'
                 WHERE es.edflexid $insql";

        $cmids = $DB->get_fieldset_sql($sql, $inparams);

        if (empty($cmids)) {
            return;
        }

        require_once($CFG->dirroot . '/course/modlib.php');
        $cmids = array_map('intval', $cmids);

        foreach ($cmids as $cmid) {
            course_delete_module($cmid);
        }
    }

    /**
     * Updates imported activities from the provided contents.
     *
     * This method processes an array of content data, identifies changes,
     * updates corresponding database records, and rebuilds course caches if necessary.
     *
     * @param array $contents Array of content data, with each item expected to include an 'edflexid' key.
     * @param int $maxcommitrecords The maximum number of records to be updated before committing transactions (default is 200).
     *
     * @return void
     */
    public function update_imported_activities_from_contents(array $contents, int $maxcommitrecords = 200): void {
        global $DB;

        $contentids = array_column($contents, 'edflexid');
        $records = $this->get_edflex_records_by_contentids($contentids);
        $updatedrecords = 0;
        $modifiedcourseids = [];
        $edflexscormids = [];

        try {
            foreach ($records as $record) {
                [$edflexscorm, $scorm] = $record;
                $edflexscormids[$edflexscorm->id] = $edflexscorm->id;
                $content = $contents[$edflexscorm->edflexid];

                [$edflexscormupd, $scormupd, $newscormbinary] = $this->get_modified_records(
                    $edflexscorm,
                    $scorm,
                    $content
                );

                $haschanges = $edflexscormupd || $scormupd || $newscormbinary;

                if (!$haschanges) {
                    continue;
                }

                if (empty($transaction)) {
                    $transaction = $DB->start_delegated_transaction();
                }

                if (!empty($edflexscormupd)) {
                    $DB->update_record('edflex_scorm', $edflexscormupd);
                }

                if (!empty($scormupd)) {
                    $DB->update_record('scorm', $scormupd);
                }

                if (!empty($newscormbinary)) {
                    $this->store_scorm_package($scorm, $newscormbinary);
                }

                if (++$updatedrecords % $maxcommitrecords === 0) {
                    $transaction->allow_commit();
                    $transaction = null;
                }

                $modifiedcourseids[$scorm->course] = $scorm->course;
            }

            if (!empty($edflexscormids)) {
                [$edflexsql, $edflexparams] = $DB->get_in_or_equal($edflexscormids, SQL_PARAMS_NAMED, 'p');
                $sql = "UPDATE {edflex_scorm} SET lastsync = :lastsync WHERE id $edflexsql";
                $DB->execute($sql, ['lastsync' => time()] + $edflexparams);
            }

            if (!empty($transaction)) {
                $transaction->allow_commit();
            }

            foreach ($modifiedcourseids as $courseid) {
                rebuild_course_cache($courseid, true);
            }
        } catch (Throwable $exception) {
            if (!empty($transaction)) {
                $transaction->rollback($exception);
            }
        }
    }

    /**
     * Stores a SCORM package by saving the binary data as a file in the SCORM module's file area,
     * deletes any previous package, and parses the new package.
     *
     * @param object $scorm An object representing the SCORM instance, which must include at least the coursemodule ID.
     * @param string $binary The binary data of the SCORM package in ZIP format to be stored.
     *
     * @return void
     */
    public function store_scorm_package(object $scorm, string $binary) {
        global $CFG;

        require_once($CFG->dirroot . '/mod/scorm/locallib.php');

        if (empty($scorm->cmid)) {
            $scorm->cmid = $scorm->coursemodule;
        }

        $context = context_module::instance($scorm->cmid);

        $tmpzip = make_temp_directory('edflex') . '/scorm_' . $scorm->id . '.zip';
        file_put_contents($tmpzip, $binary);

        $fs = get_file_storage();

        $fs->delete_area_files($context->id, 'mod_scorm', 'package', 0);

        $filerecord = [
            'contextid' => $context->id,
            'component' => 'mod_scorm',
            'filearea'  => 'package',
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => 'package.zip',
        ];

        $fs->create_file_from_pathname($filerecord, $tmpzip);

        $scorm->reference = 'package.zip';

        scorm_parse($scorm, true);

        @unlink($tmpzip);
    }

    /**
     * Retrieves Edflex records and their corresponding SCORM records by content IDs.
     *
     * @param array $contentids The list of content IDs to fetch associated Edflex records for.
     * @param int $batchsize The batch size.
     *
     * @return Generator Yields arrays containing Edflex records from the 'edflex_scorm' table
     *                   and their matching SCORM records from the 'scorm' table if present.
     */
    public function get_edflex_records_by_contentids(array $contentids, int $batchsize = 200): Generator {
        global $DB;

        if (empty($contentids)) {
            return;
        }

        $offset = 0;

        [$edflexsql, $edflexparams] = $DB->get_in_or_equal($contentids, SQL_PARAMS_NAMED, 'p');

        do {
            $edflexscorms = $DB->get_records_select(
                'edflex_scorm',
                "edflexid $edflexsql",
                $edflexparams,
                'id ASC',
                '*',
                $offset,
                $batchsize
            );

            if (empty($edflexscorms)) {
                return;
            }

            $scormids = [];

            foreach ($edflexscorms as $edflexscorm) {
                $scormids[] = (int)$edflexscorm->scormid;
            }

            [$scormsql, $scormparams] = $DB->get_in_or_equal($scormids, SQL_PARAMS_NAMED, 'p');

            $sql = "SELECT s.*, cm.id AS cmid
                  FROM {scorm} s
                  JOIN {course_modules} cm ON cm.instance = s.id
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'scorm'
                 WHERE s.id $scormsql";

            $scorms = $DB->get_records_sql($sql, $scormparams);

            foreach ($edflexscorms as $edflexscorm) {
                $scorm = $scorms[$edflexscorm->scormid] ?? null;

                yield [$edflexscorm, $scorm];
            }
            $offset += $batchsize;
        } while (count($edflexscorms) === $batchsize);
    }

    /**
     * Retrieves the client instance. If no client is currently set, a new client
     * instance is created using the configuration.
     *
     * @return client The client instance.
     */
    public function get_client() {
        if (empty($this->client)) {
            $this->client = client::from_config();
        }

        return $this->client;
    }

    /**
     * Identifies and retrieves the modified records for SCORM and edflex_scorm updates.
     *
     * @param object $edflexscorm The edflex_scorm object containing existing data.
     * @param object $scorm The SCORM object containing existing data.
     * @param array $content An associative array containing new data to compare with the existing ones.
     *
     * @return array An array containing the updated edflex_scorm object, the updated SCORM object,
     *               and the new SCORM package binary, if applicable.
     */
    private function get_modified_records(object $edflexscorm, object $scorm, array $content): array {
        // Get scorm updates.
        $scormfields = ['name', 'intro'];
        $scormupd = [];

        foreach ($scormfields as $field) {
            if ($scorm->{$field} !== $content[$field]) {
                $scormupd[$field] = $content[$field];
            }
        }

        if (!empty($scormupd)) {
            $scormupd = (object) $scormupd;
            $scormupd->id = $scorm->id;
        }

        // Get edflex_scorm updates.
        $edflexscormfields = ['name', 'language', 'difficulty', 'duration', 'author', 'type', 'url'];
        $edflexscormupd = [];

        foreach ($edflexscormfields as $field) {
            if ($edflexscorm->{$field} !== $content[$field]) {
                $edflexscormupd[$field] = $content[$field];
            }
        }

        if (!empty($edflexscormupd)) {
            $edflexscormupd = (object) $edflexscormupd;
            $edflexscormupd->id = $edflexscorm->id;
        }

        $shoulddownloadscormzip = $edflexscorm->name !== $content['name']
            || $edflexscorm->type !== $content['type']
            || $edflexscorm->url !== $content['url'];

        if ($shoulddownloadscormzip) {
            $newscormbinary = $this->get_client()->get_scorm($content['downloadscormzip']);
        }

        return [$edflexscormupd ?: null, $scormupd ?: null, $newscormbinary ?? null];
    }
}
