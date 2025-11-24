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
 * Test for the content_synchronizer class
 *
 * @package     mod_edflex
 * @copyright   2025 Edflex <support@edflex.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_edflex\local;

use advanced_testcase;
use mod_edflex\api\client;
use mod_edflex\api\mapper;

/**
 * Unit tests for mod_edflex\local\content_synchronizer
 *
 * @covers \mod_edflex\local\content_synchronizer
 */
final class content_synchronizer_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test synchronize_all_contents with no outdated contents
     */
    public function test_synchronize_all_contents_no_outdated(): void {
        $mockclient = $this->getMockBuilder(client::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockclient->expects($this->never())
            ->method('get_contents_by_ids');

        $mockactivitymanager = $this->getMockBuilder(activity_manager::class)
            ->setConstructorArgs([$mockclient])
            ->onlyMethods([
                'get_outdated_edflex_contentids_in_chunks',
                'update_imported_activities_from_contents',
                'delete_scorms_by_contentids',
            ])
            ->getMock();

        $emptygenerator = function () {
            return;
            yield;
        };

        $mockactivitymanager->expects($this->once())
            ->method('get_outdated_edflex_contentids_in_chunks')
            ->with(time() - 3600, null, 200)
            ->willReturn($emptygenerator());

        $mockactivitymanager->expects($this->never())
            ->method('update_imported_activities_from_contents');

        $mockactivitymanager->expects($this->never())
            ->method('delete_scorms_by_contentids');

        $synchronizer = new content_synchronizer($mockclient);
        $reflection = new \ReflectionClass($synchronizer);
        $property = $reflection->getProperty('activitymanager');
        $property->setAccessible(true);
        $property->setValue($synchronizer, $mockactivitymanager);

        $synchronizer->synchronize_all_contents(time() - 3600);
    }

    /**
     * Test synchronize_all_contents with contents to update
     */
    public function test_synchronize_all_contents_with_updates(): void {
        $contentids = ['content1', 'content2', 'content3'];

        $apicontents = [
            'content1' => [
                'id' => 'content1',
                'name' => 'Updated Content 1',
                'description' => 'Updated Description 1',
                'url' => 'https://e.test/content1',
                'language' => 'en',
                'type' => 'article',
                'difficulty' => 'intermediate',
                'duration' => 'PT2H',
                'author' => 'Updated Author 1',
                'downloadUrl' => 'https://e.test/scorm1.zip',
            ],
            'content2' => [
                'id' => 'content2',
                'name' => 'Updated Content 2',
                'description' => 'Updated Description 2',
                'url' => 'https://e.test/content2',
                'language' => 'fr',
                'type' => 'video',
                'difficulty' => 'advanced',
                'duration' => 'PT3H',
                'author' => 'Updated Author 2',
                'downloadUrl' => 'https://e.test/scorm2.zip',
            ],
            'content3' => [
                'id' => 'content3',
                'name' => 'Updated Content 3',
                'description' => 'Updated Description 3',
                'url' => 'https://e.test/content3',
                'language' => 'es',
                'type' => 'podcast',
                'difficulty' => 'beginner',
                'duration' => 'PT1H',
                'author' => 'Updated Author 3',
                'downloadUrl' => 'https://e.test/scorm3.zip',
            ],
        ];

        $mappedcontents = mapper::map_contents($apicontents);

        $mockclient = $this->getMockBuilder(client::class)
            ->disableOriginalConstructor()
            ->getMock();

        $contentsgenerator = function () use ($apicontents) {
            foreach ($apicontents as $id => $content) {
                yield $id => $content;
            }
        };

        $mockclient->expects($this->once())
            ->method('get_contents_by_ids')
            ->with($contentids)
            ->willReturn($contentsgenerator());

        $mockactivitymanager = $this->getMockBuilder(activity_manager::class)
            ->setConstructorArgs([$mockclient])
            ->onlyMethods([
                'get_outdated_edflex_contentids_in_chunks',
                'update_imported_activities_from_contents',
                'delete_scorms_by_contentids',
            ])
            ->getMock();

        $chunksgenerator = function () use ($contentids) {
            yield $contentids;
        };

        $mockactivitymanager->expects($this->once())
            ->method('get_outdated_edflex_contentids_in_chunks')
            ->with(time() - 3600, null, 200)
            ->willReturn($chunksgenerator());

        $mockactivitymanager->expects($this->once())
            ->method('update_imported_activities_from_contents')
            ->with($mappedcontents);

        $mockactivitymanager->expects($this->never())
            ->method('delete_scorms_by_contentids');

        $synchronizer = new content_synchronizer($mockclient);
        $reflection = new \ReflectionClass($synchronizer);
        $property = $reflection->getProperty('activitymanager');
        $property->setAccessible(true);
        $property->setValue($synchronizer, $mockactivitymanager);

        $synchronizer->synchronize_all_contents(time() - 3600);
    }

    /**
     * Test synchronize_all_contents with deleted contents
     */
    public function test_synchronize_all_contents_with_deletions(): void {
        $contentids = ['content1', 'content2', 'content3', 'content4'];

        $apicontents = [
            'content1' => [
                'id' => 'content1',
                'name' => 'Content 1',
                'description' => 'Description 1',
                'url' => 'https://e.test/content1',
                'language' => 'en',
                'type' => 'article',
                'difficulty' => 'beginner',
                'duration' => 'PT1H',
                'author' => 'Author 1',
                'downloadUrl' => 'https://e.test/scorm1.zip',
            ],
            'content3' => [
                'id' => 'content3',
                'name' => 'Content 3',
                'description' => 'Description 3',
                'url' => 'https://e.test/content3',
                'language' => 'en',
                'type' => 'article',
                'difficulty' => 'beginner',
                'duration' => 'PT1H',
                'author' => 'Author 3',
                'downloadUrl' => 'https://e.test/scorm3.zip',
            ],
        ];

        $mappedcontents = mapper::map_contents($apicontents);

        $mockclient = $this->getMockBuilder(client::class)
            ->disableOriginalConstructor()
            ->getMock();

        $contentsgenerator = function () use ($apicontents) {
            foreach ($apicontents as $id => $content) {
                yield $id => $content;
            }
        };

        $mockclient->expects($this->once())
            ->method('get_contents_by_ids')
            ->with($contentids)
            ->willReturn($contentsgenerator());

        $mockactivitymanager = $this->getMockBuilder(activity_manager::class)
            ->setConstructorArgs([$mockclient])
            ->onlyMethods([
                'get_outdated_edflex_contentids_in_chunks',
                'update_imported_activities_from_contents',
                'delete_scorms_by_contentids',
            ])
            ->getMock();

        $chunksgenerator = function () use ($contentids) {
            yield $contentids;
        };

        $mockactivitymanager->expects($this->once())
            ->method('get_outdated_edflex_contentids_in_chunks')
            ->with(time() - 3600, 100, 200)
            ->willReturn($chunksgenerator());

        $mockactivitymanager->expects($this->once())
            ->method('update_imported_activities_from_contents')
            ->with($mappedcontents);

        $mockactivitymanager->expects($this->once())
            ->method('delete_scorms_by_contentids')
            ->with(['content2', 'content4']);

        $synchronizer = new content_synchronizer($mockclient);
        $reflection = new \ReflectionClass($synchronizer);
        $property = $reflection->getProperty('activitymanager');
        $property->setAccessible(true);
        $property->setValue($synchronizer, $mockactivitymanager);

        $synchronizer->synchronize_all_contents(time() - 3600, 100);
    }

    /**
     * Test synchronize_all_contents with multiple chunks
     */
    public function test_synchronize_all_contents_multiple_chunks(): void {
        $chunk1 = ['content1', 'content2'];
        $chunk2 = ['content3', 'content4'];
        $chunk3 = ['content5'];

        $apicontents1 = [
            'content1' => [
                'id' => 'content1',
                'name' => 'Content 1',
                'description' => 'Description 1',
                'url' => 'https://e.test/content1',
                'language' => 'en',
                'type' => 'article',
                'difficulty' => 'beginner',
                'duration' => 'PT1H',
                'author' => 'Author 1',
                'downloadUrl' => 'https://e.test/scorm1.zip',
            ],
            'content2' => [
                'id' => 'content2',
                'name' => 'Content 2',
                'description' => 'Description 2',
                'url' => 'https://e.test/content2',
                'language' => 'en',
                'type' => 'article',
                'difficulty' => 'beginner',
                'duration' => 'PT1H',
                'author' => 'Author 2',
                'downloadUrl' => 'https://e.test/scorm2.zip',
            ],
        ];

        $apicontents2 = [
            'content3' => [
                'id' => 'content3',
                'name' => 'Content 3',
                'description' => 'Description 3',
                'url' => 'https://e.test/content3',
                'language' => 'en',
                'type' => 'article',
                'difficulty' => 'beginner',
                'duration' => 'PT1H',
                'author' => 'Author 3',
                'downloadUrl' => 'https://e.test/scorm3.zip',
            ],
        ];

        $apicontents3 = [
            'content5' => [
                'id' => 'content5',
                'name' => 'Content 5',
                'description' => 'Description 5',
                'url' => 'https://e.test/content5',
                'language' => 'en',
                'type' => 'article',
                'difficulty' => 'beginner',
                'duration' => 'PT1H',
                'author' => 'Author 5',
                'downloadUrl' => 'https://e.test/scorm5.zip',
            ],
        ];

        $mockclient = $this->getMockBuilder(client::class)
            ->disableOriginalConstructor()
            ->getMock();

        $contentsgenerator1 = function () use ($apicontents1) {
            foreach ($apicontents1 as $id => $content) {
                yield $id => $content;
            }
        };

        $contentsgenerator2 = function () use ($apicontents2) {
            foreach ($apicontents2 as $id => $content) {
                yield $id => $content;
            }
        };

        $contentsgenerator3 = function () use ($apicontents3) {
            foreach ($apicontents3 as $id => $content) {
                yield $id => $content;
            }
        };

        $mockclient->expects($this->exactly(3))
            ->method('get_contents_by_ids')
            ->willReturnOnConsecutiveCalls(
                $contentsgenerator1(),
                $contentsgenerator2(),
                $contentsgenerator3()
            );

        $mockactivitymanager = $this->getMockBuilder(activity_manager::class)
            ->setConstructorArgs([$mockclient])
            ->onlyMethods([
                'get_outdated_edflex_contentids_in_chunks',
                'update_imported_activities_from_contents',
                'delete_scorms_by_contentids',
            ])
            ->getMock();

        $chunksgenerator = function () use ($chunk1, $chunk2, $chunk3) {
            yield $chunk1;
            yield $chunk2;
            yield $chunk3;
        };

        $mockactivitymanager->expects($this->once())
            ->method('get_outdated_edflex_contentids_in_chunks')
            ->willReturn($chunksgenerator());

        $mockactivitymanager->expects($this->exactly(3))
            ->method('update_imported_activities_from_contents');

        $mockactivitymanager->expects($this->once())
            ->method('delete_scorms_by_contentids')
            ->with(['content4']);

        $synchronizer = new content_synchronizer($mockclient);
        $reflection = new \ReflectionClass($synchronizer);
        $property = $reflection->getProperty('activitymanager');
        $property->setAccessible(true);
        $property->setValue($synchronizer, $mockactivitymanager);

        $synchronizer->synchronize_all_contents(time() - 3600);
    }

    /**
     * Test synchronize_all_contents with all contents deleted
     */
    public function test_synchronize_all_contents_all_deleted(): void {
        $contentids = ['content1', 'content2', 'content3'];

        $mockclient = $this->getMockBuilder(client::class)
            ->disableOriginalConstructor()
            ->getMock();

        $emptygenerator = function () {
            return;
            yield;
        };

        $mockclient->expects($this->once())
            ->method('get_contents_by_ids')
            ->with($contentids)
            ->willReturn($emptygenerator());

        $mockactivitymanager = $this->getMockBuilder(activity_manager::class)
            ->setConstructorArgs([$mockclient])
            ->onlyMethods([
                'get_outdated_edflex_contentids_in_chunks',
                'update_imported_activities_from_contents',
                'delete_scorms_by_contentids',
            ])
            ->getMock();

        $chunksgenerator = function () use ($contentids) {
            yield $contentids;
        };

        $mockactivitymanager->expects($this->once())
            ->method('get_outdated_edflex_contentids_in_chunks')
            ->willReturn($chunksgenerator());

        $mockactivitymanager->expects($this->once())
            ->method('update_imported_activities_from_contents')
            ->with([]);

        $mockactivitymanager->expects($this->once())
            ->method('delete_scorms_by_contentids')
            ->with($contentids);

        $synchronizer = new content_synchronizer($mockclient);
        $reflection = new \ReflectionClass($synchronizer);
        $property = $reflection->getProperty('activitymanager');
        $property->setAccessible(true);
        $property->setValue($synchronizer, $mockactivitymanager);

        $synchronizer->synchronize_all_contents(time() - 3600);
    }

    /**
     * Test synchronize_all_contents with maxrecordstoxync limit
     */
    public function test_synchronize_all_contents_with_limit(): void {
        $contentids = ['content1', 'content2'];

        $apicontents = [
            'content1' => [
                'id' => 'content1',
                'name' => 'Content 1',
                'description' => 'Description 1',
                'url' => 'https://e.test/content1',
                'language' => 'en',
                'type' => 'article',
                'difficulty' => 'beginner',
                'duration' => 'PT1H',
                'author' => 'Author 1',
                'downloadUrl' => 'https://e.test/scorm1.zip',
            ],
            'content2' => [
                'id' => 'content2',
                'name' => 'Content 2',
                'description' => 'Description 2',
                'url' => 'https://e.test/content2',
                'language' => 'en',
                'type' => 'article',
                'difficulty' => 'beginner',
                'duration' => 'PT1H',
                'author' => 'Author 2',
                'downloadUrl' => 'https://e.test/scorm2.zip',
            ],
        ];

        $mockclient = $this->getMockBuilder(client::class)
            ->disableOriginalConstructor()
            ->getMock();

        $contentsgenerator = function () use ($apicontents) {
            foreach ($apicontents as $id => $content) {
                yield $id => $content;
            }
        };

        $mockclient->expects($this->once())
            ->method('get_contents_by_ids')
            ->with($contentids)
            ->willReturn($contentsgenerator());

        $mockactivitymanager = $this->getMockBuilder(activity_manager::class)
            ->setConstructorArgs([$mockclient])
            ->onlyMethods(['get_outdated_edflex_contentids_in_chunks', 'update_imported_activities_from_contents'])
            ->getMock();

        $chunksgenerator = function () use ($contentids) {
            yield $contentids;
        };

        $mockactivitymanager->expects($this->once())
            ->method('get_outdated_edflex_contentids_in_chunks')
            ->with(time() - 7200, 50, 200)
            ->willReturn($chunksgenerator());

        $mockactivitymanager->expects($this->once())
            ->method('update_imported_activities_from_contents');

        $synchronizer = new content_synchronizer($mockclient);
        $reflection = new \ReflectionClass($synchronizer);
        $property = $reflection->getProperty('activitymanager');
        $property->setAccessible(true);
        $property->setValue($synchronizer, $mockactivitymanager);

        $synchronizer->synchronize_all_contents(time() - 7200, 50);
    }
}
