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
 * Test for the category_manager class
 *
 * @package     mod_edflex
 * @copyright   2025 Edflex <support@edflex.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_edflex\local;

use advanced_testcase;
use cache;
use Exception;
use mod_edflex\api\client;
use moodle_exception;
use PHPUnit\Framework\MockObject\MockObject;
use stdClass;

/**
 * Unit tests for mod_edflex\local\category_manager
 *
 * @runTestsInSeparateProcesses
 * @covers \mod_edflex\local\category_manager
 */
final class category_manager_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test sync_categories with new categories insertion
     */
    public function test_sync_categories_insert_new_categories(): void {
        global $DB;

        $catalogid = 'catalog-123';
        $apicategories = [
            [
                'id' => 'cat-001',
                'attributes' => [
                    'title' => ['default' => 'Category 1'],
                    'position' => 1,
                ],
            ],
            [
                'id' => 'cat-002',
                'attributes' => [
                    'title' => ['default' => 'Category 2'],
                    'position' => 2,
                ],
            ],
        ];

        $manager = new category_manager($this->get_empty_mock_client());

        $manager->sync_categories($apicategories, $catalogid);
        $categories = $DB->get_records('edflex_categories');
        $this->assertCount(2, $categories);

        $cat1 = $DB->get_record('edflex_categories', ['uuid' => 'cat-001']);
        $this->assertEquals('Category 1', $cat1->title);
        $this->assertEquals(1, $cat1->position);
        $this->assertEquals($catalogid, $cat1->catalog_uuid);

        $cat2 = $DB->get_record('edflex_categories', ['uuid' => 'cat-002']);
        $this->assertEquals('Category 2', $cat2->title);
        $this->assertEquals(2, $cat2->position);
    }

    /**
     * Test sync_categories with existing categories update
     */
    public function test_sync_categories_update_existing_categories(): void {
        global $DB;

        $catalogid = 'catalog-123';

        // Insert an existing category.
        $existing = new stdClass();
        $existing->uuid = 'cat-001';
        $existing->catalog_uuid = $catalogid;
        $existing->title = 'Old Title';
        $existing->position = 5;
        $existing->lastsync = time() - 3600;
        $DB->insert_record('edflex_categories', $existing);

        $apicategories = [
            [
                'id' => 'cat-001',
                'attributes' => [
                    'title' => ['default' => 'New Title'],
                    'position' => 10,
                ],
            ],
        ];

        $manager = new category_manager($this->get_empty_mock_client());
        $manager->sync_categories($apicategories, $catalogid);

        // Verify that the category was updated.
        $updated = $DB->get_record('edflex_categories', ['uuid' => 'cat-001']);
        $this->assertEquals('New Title', $updated->title);
        $this->assertEquals(10, $updated->position);
        $this->assertGreaterThan($existing->lastsync, $updated->lastsync);
    }

    /**
     * Test sync_categories with empty categories array
     */
    public function test_sync_categories_with_empty_array(): void {
        global $DB;

        $existing = new stdClass();
        $existing->uuid = 'cat-001';
        $existing->catalog_uuid = 'catalog-123';
        $existing->title = 'Old Title';
        $existing->position = 5;
        $existing->lastsync = time() - 3600;
        $DB->insert_record('edflex_categories', $existing);

        $manager = new category_manager($this->get_empty_mock_client());
        $manager->sync_categories([], 'catalog-123');

        // Verify no categories were inserted or deleted.
        $categories = $DB->get_records('edflex_categories');
        $this->assertCount(1, $categories);
    }

    /**
     * Test sync_category_translations update
     */
    public function test_sync_category_translations_update(): void {
        global $DB;

        // Insert a category.
        $category = new stdClass();
        $category->uuid = 'cat-001';
        $category->catalog_uuid = 'catalog-123';
        $category->title = 'Default Title';
        $category->position = 1;
        $category->lastsync = time();
        $categoryid = $DB->insert_record('edflex_categories', $category);

        // Insert an existing translation.
        $existingtranslation = new stdClass();
        $existingtranslation->category_id = $categoryid;
        $existingtranslation->language_code = 'en';
        $existingtranslation->title = 'Old English Title';
        $translationid = $DB->insert_record('edflex_category_translations', $existingtranslation);

        $apicategories = [
            [
                'id' => 'cat-001',
                'attributes' => [
                    'title' => [
                        'default' => 'Default Title',
                        'en' => 'New English Title',
                    ],
                ],
            ],
        ];

        $manager = new category_manager($this->get_empty_mock_client());
        $manager->sync_category_translations($apicategories);

        // Verify translation was updated.
        $updated = $DB->get_record('edflex_category_translations', ['id' => $translationid]);
        $this->assertEquals('New English Title', $updated->title);
    }

    /**
     * Test sync_category_translations with empty categories
     */
    public function test_sync_category_translations_with_empty_array(): void {
        global $DB;

        // Insert a category.
        $category = new stdClass();
        $category->uuid = 'cat-001';
        $category->catalog_uuid = 'catalog-123';
        $category->title = 'Default Title';
        $category->position = 1;
        $category->lastsync = time();
        $categoryid = $DB->insert_record('edflex_categories', $category);

        // Insert an existing translation.
        $existingtranslation = new stdClass();
        $existingtranslation->category_id = $categoryid;
        $existingtranslation->language_code = 'en';
        $existingtranslation->title = 'Old English Title';
        $DB->insert_record('edflex_category_translations', $existingtranslation);

        $manager = new category_manager($this->get_empty_mock_client());
        $manager->sync_category_translations([]);

        // Verify no translations were inserted or deleted.
        $translations = $DB->get_records('edflex_category_translations');
        $this->assertCount(1, $translations);
    }

    /**
     * Test delete_orphaned_edflex_category_translations
     */
    public function test_delete_orphaned_edflex_category_translations(): void {
        global $DB;

        // Insert a valid category.
        $category = new stdClass();
        $category->uuid = 'cat-001';
        $category->catalog_uuid = 'catalog-123';
        $category->title = 'Valid Category';
        $category->position = 1;
        $category->lastsync = time();
        $validcategoryid = $DB->insert_record('edflex_categories', $category);

        // Insert valid translation.
        $validtranslation = new stdClass();
        $validtranslation->category_id = $validcategoryid;
        $validtranslation->language_code = 'en';
        $validtranslation->title = 'Valid Translation';
        $DB->insert_record('edflex_category_translations', $validtranslation);

        // Insert orphaned translations (with non-existent category_id).
        $orphaned1 = new stdClass();
        $orphaned1->category_id = 99999;
        $orphaned1->language_code = 'fr';
        $orphaned1->title = 'Orphaned Translation 1';
        $DB->insert_record('edflex_category_translations', $orphaned1);

        $orphaned2 = new stdClass();
        $orphaned2->category_id = 99998;
        $orphaned2->language_code = 'es';
        $orphaned2->title = 'Orphaned Translation 2';
        $DB->insert_record('edflex_category_translations', $orphaned2);

        $manager = new category_manager($this->get_empty_mock_client());
        $deletedcount = $manager->delete_orphaned_edflex_category_translations();

        // Verify orphaned records were deleted.
        $this->assertEquals(2, $deletedcount);

        // Verify valid translation still exists.
        $remaining = $DB->get_records('edflex_category_translations');
        $this->assertCount(1, $remaining);
        $this->assertEquals($validcategoryid, reset($remaining)->category_id);
    }

    /**
     * Test delete_orphaned_edflex_category_translations with no orphans
     */
    public function test_delete_orphaned_edflex_category_translations_no_orphans(): void {
        global $DB;

        // Insert a valid category.
        $category = new stdClass();
        $category->uuid = 'cat-001';
        $category->catalog_uuid = 'catalog-123';
        $category->title = 'Valid Category';
        $category->position = 1;
        $category->lastsync = time();
        $categoryid = $DB->insert_record('edflex_categories', $category);

        // Insert valid translation.
        $translation = new stdClass();
        $translation->category_id = $categoryid;
        $translation->language_code = 'en';
        $translation->title = 'Valid Translation';
        $DB->insert_record('edflex_category_translations', $translation);

        $manager = new category_manager($this->get_empty_mock_client());
        $deletedcount = $manager->delete_orphaned_edflex_category_translations();

        // Verify no records were deleted.
        $this->assertEquals(0, $deletedcount);

        // Verify translation still exists.
        $remaining = $DB->get_records('edflex_category_translations');
        $this->assertCount(1, $remaining);
    }

    /**
     * Test synchronize_all_catalogs with mock client
     */
    public function test_synchronize_all_catalogs(): void {
        global $DB;

        $mockclient = $this->getMockBuilder(client::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get_catalogs', 'get_categories'])
            ->getMock();

        $mockclient->method('get_catalogs')->willReturn([
            'data' => [
                ['id' => 'catalog-001'],
                ['id' => 'catalog-002'],
            ],
        ]);

        $mockclient->method('get_categories')->willReturnOnConsecutiveCalls(
            // First catalog, first page.
            [
                'data' => [
                    [
                        'id' => 'cat-001',
                        'attributes' => [
                            'title' => [
                                'default' => 'Category 1',
                                'en' => 'Category 1 EN',
                            ],
                            'position' => 1,
                        ],
                    ],
                ],
                'links' => ['next' => false],
            ],
            // Second catalog, first page.
            [
                'data' => [
                    [
                        'id' => 'cat-002',
                        'attributes' => [
                            'title' => [
                                'default' => 'Category 2',
                                'fr' => 'Category 2 FR',
                            ],
                            'position' => 2,
                        ],
                    ],
                ],
                'links' => ['next' => false],
            ]
        );

        $manager = new category_manager($mockclient);
        $manager->synchronize_all_catalogs();

        // Verify categories were created.
        $categories = $DB->get_records('edflex_categories');
        $this->assertCount(2, $categories);

        // Verify translations were created.
        $translations = $DB->get_records('edflex_category_translations');
        $this->assertCount(2, $translations);
    }

    /**
     * Test synchronize_all_catalogs deletes old categories
     */
    public function test_synchronize_all_catalogs_deletes_old_categories(): void {
        global $DB;

        // Insert an old category that won't be in the sync.
        $oldcategory = new stdClass();
        $oldcategory->uuid = 'old-cat-001';
        $oldcategory->catalog_uuid = 'catalog-001';
        $oldcategory->title = 'Old Category';
        $oldcategory->position = 1;
        $oldcategory->lastsync = time() - 7200; // 2 hours ago
        $DB->insert_record('edflex_categories', $oldcategory);

        $mockclient = $this->getMockBuilder(client::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get_catalogs', 'get_categories'])
            ->getMock();

        $mockclient->method('get_catalogs')->willReturn([
            'data' => [
                ['id' => 'catalog-001'],
            ],
        ]);

        $mockclient->method('get_categories')->willReturn([
            'data' => [
                [
                    'id' => 'new-cat-001',
                    'attributes' => [
                        'title' => ['default' => 'New Category'],
                        'position' => 1,
                    ],
                ],
            ],
            'links' => ['next' => false],
        ]);

        $manager = new category_manager($mockclient);
        $manager->synchronize_all_catalogs();

        // Verify old category was deleted.
        $oldrecord = $DB->get_record('edflex_categories', ['uuid' => 'old-cat-001']);
        $this->assertFalse($oldrecord);

        // Verify new category exists.
        $newrecord = $DB->get_record('edflex_categories', ['uuid' => 'new-cat-001']);
        $this->assertNotFalse($newrecord);
        $this->assertEquals('New Category', $newrecord->title);
    }

    /**
     * Test sync_categories rollback with invalid data
     */
    public function test_sync_categories_rollback_with_invalid_data(): void {
        global $DB;

        $catalogid = 'catalog-123';

        $validcategory = new stdClass();
        $validcategory->uuid = 'cat-001';
        $validcategory->catalog_uuid = $catalogid;
        $validcategory->title = 'Valid Category';
        $validcategory->position = 1;
        $validcategory->lastsync = time();
        $DB->insert_record('edflex_categories', $validcategory);

        $apicategories = [
            [
                'id' => 'cat-002',
                'attributes' => [
                    'title' => ['default' => 'Category 1'],
                    'position' => 2,
                ],
            ],
            [
                'id' => 'cat-003',
                'attributes' => [
                    'title' => ['default' => 'Category 2'],
                    'position' => "string position", // This will cause NOT INT violation.
                ],
            ],
        ];

        $manager = new category_manager($this->get_empty_mock_client());

        try {
            $manager->sync_categories($apicategories, $catalogid);
            $this->fail('Expected exception was not thrown');
        } catch (Exception $e) {
            $this->assertInstanceOf(Exception::class, $e);
        }

        $categories = $DB->get_records('edflex_categories');
        $this->assertCount(1, $categories);

        $cat002 = $DB->get_record('edflex_categories', ['uuid' => 'cat-002']);
        $this->assertFalse($cat002);
    }

    /**
     * Test sync_category_translations rollback with invalid category_id
     */
    public function test_sync_category_translations_rollback_with_invalid_category_id(): void {
        global $DB;

        // Don't create any categories - use non-existent category IDs.
        $apicategories = [
            [
                'id' => 'non-existent-cat',
                'attributes' => [
                    'title' => [
                        'default' => 'Default Title',
                        'en' => 'English Title',
                        'fr' => 'French Title',
                    ],
                ],
            ],
        ];

        $manager = new category_manager($this->get_empty_mock_client());

        try {
            $manager->sync_category_translations($apicategories);
        } catch (Exception $e) {
            $this->assertInstanceOf(Exception::class, $e);
        }

        $translations = $DB->get_records('edflex_category_translations');
        $this->assertCount(0, $translations);
    }


    /**
     * Creates and returns a mock instance of the client class with the original constructor disabled.
     */
    private function get_empty_mock_client(): client {
        return $this->getMockBuilder(client::class)
            ->disableOriginalConstructor()
            ->getMock();
    }
}
