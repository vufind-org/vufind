<?php

/**
 * Resource populator tests.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Record;

use VuFind\Db\Entity\ResourceEntityInterface;
use VuFind\Db\Service\ResourceServiceInterface;
use VuFind\Record\Loader;
use VuFind\Record\ResourcePopulator;
use VuFindTest\RecordDriver\TestHarness;

/**
 * Resource populator tests.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ResourcePopulatorTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Data provider for testCreateAndPersistResourceForRecordId
     *
     * @return array
     */
    public static function createAndPersistResourceForRecordIdProvider(): array
    {
        return [
            [['1999'], 1999],
            [['1999?'], 1999],
            [['2025-10-10'], 2025],
            [['-100'], -100],
            [['0'], 0],
            [['foo', '1999'], 1999],
            [['©1999'], 1999],
            [['Ⓟ1999'], 1999],
            [['copyright 1999'], 1999],
            [['2020-2025'], 2025],
            [['2020 - 2025'], 2025],
            [['copyright 2020-2025'], 2025],
            [['copyright 2020 -2025'], 2025],
            [['copyright 2020?-2025?'], 2025],
        ];
    }

    /**
     * Test creating and persisting a resource from a record ID; this will in turn
     * test most of the other functionality of the class.
     *
     * @param array $pubDates     Publication dates
     * @param int   $expectedYear Expected parsed year
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('createAndPersistResourceForRecordIdProvider')]
    public function testCreateAndPersistResourceForRecordId(array $pubDates, int $expectedYear): void
    {
        $id = 'fake-id';
        $source = 'fake-source';
        $driver = new TestHarness();
        $driver->setRawData(
            [
                'Breadcrumb' => 'Fake Title',
                'Title' => 'Fake Full Title',
                'PrimaryAuthor' => 'Fake Author',
                'PublicationDates' => $pubDates,
                'UniqueID' => $id,
                'SourceIdentifier' => $source,
            ]
        );
        $resource = $this->createMock(ResourceEntityInterface::class);
        $resource->expects($this->once())->method('setRecordId')->with($id)->willReturn($resource);
        $resource->expects($this->once())->method('setSource')->with($source)->willReturn($resource);
        $resource->expects($this->once())->method('setTitle')->with('fake title')->willReturn($resource);
        $resource->expects($this->once())->method('setDisplayTitle')->with('Fake Full Title')->willReturn($resource);
        $resource->expects($this->once())->method('setAuthor')->with('Fake Author')->willReturn($resource);
        $resource->expects($this->once())->method('setYear')->with($expectedYear)->willReturn($resource);
        $service = $this->createMock(ResourceServiceInterface::class);
        $service->expects($this->once())->method('createEntity')->willReturn($resource);
        $service->expects($this->once())->method('persistEntity')->with($resource);
        $loader = $this->createMock(Loader::class);
        $loader->expects($this->once())->method('load')->with($id, $source)->willReturn($driver);
        $populator = new ResourcePopulator($service, $loader);
        $this->assertEquals(
            $resource,
            $populator->createAndPersistResourceForRecordId($id, $source)
        );
    }
}
