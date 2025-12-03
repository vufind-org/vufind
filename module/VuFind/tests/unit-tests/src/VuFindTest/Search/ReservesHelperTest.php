<?php

/**
 * Unit tests for the ReservesHelper
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
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
 * @author   Sambhav Pokharel <sambhavpokharel@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Search;

use PHPUnit\Framework\TestCase;
use VuFind\ILS\Connection;
use VuFind\RecordDriver\SolrReserves;
use VuFind\Search\ReservesHelper;
use VuFindSearch\Command\RetrieveCommand;
use VuFindSearch\Response\RecordCollectionInterface;
use VuFindSearch\Service;

/**
 * Unit tests for the ReservesHelper
 *
 * @category VuFind
 * @package  Tests
 * @author   Sambhav Pokharel <sambhavpokharel@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class ReservesHelperTest extends TestCase
{
    /**
     * Test constructor throws exception when useIndex is true but searchService is null
     *
     * @return void
     */
    public function testConstructorThrowsExceptionWhenSearchServiceMissing(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Missing required search service');

        new ReservesHelper(true, null, $this->createMock(Connection::class));
    }

    /**
     * Test constructor succeeds when useIndex is true and searchService is provided
     *
     * @return void
     */
    public function testConstructorSucceedsWithSearchService(): void
    {
        $searchService = $this->createMock(Service::class);
        $catalog = $this->createMock(Connection::class);

        $helper = new ReservesHelper(true, $searchService, $catalog);

        $this->assertInstanceOf(ReservesHelper::class, $helper);
    }

    /**
     * Test constructor succeeds when useIndex is false and searchService is null
     *
     * @return void
     */
    public function testConstructorSucceedsWithoutSearchService(): void
    {
        $catalog = $this->createMock(Connection::class);

        $helper = new ReservesHelper(false, null, $catalog);

        $this->assertInstanceOf(ReservesHelper::class, $helper);
    }

    /**
     * Test useIndex returns correct value
     *
     * @return void
     */
    public function testUseIndexReturnsTrue(): void
    {
        $searchService = $this->createMock(Service::class);
        $catalog = $this->createMock(Connection::class);

        $helper = new ReservesHelper(true, $searchService, $catalog);

        $this->assertTrue($helper->useIndex());
    }

    /**
     * Test useIndex returns false
     *
     * @return void
     */
    public function testUseIndexReturnsFalse(): void
    {
        $catalog = $this->createMock(Connection::class);

        $helper = new ReservesHelper(false, null, $catalog);

        $this->assertFalse($helper->useIndex());
    }

    /**
     * Test findReserves using catalog (ILS driver)
     *
     * @return void
     */
    public function testFindReservesUsingCatalog(): void
    {
        $catalog = $this->createMock(Connection::class);

        $expectedReserves = [
            ['BIB_ID' => 'bib1', 'course' => 'CS101', 'instructor' => 'Dr. Smith'],
            ['BIB_ID' => 'bib2', 'course' => 'CS101', 'instructor' => 'Dr. Smith'],
        ];

        $catalog->expects($this->once())
            ->method('__call')
            ->with('findReserves', ['CS101', 'smith', 'Computer Science'])
            ->willReturn($expectedReserves);

        $helper = new ReservesHelper(false, null, $catalog);
        $result = $helper->findReserves('CS101', 'smith', 'Computer Science');

        $this->assertSame($expectedReserves, $result);
    }

    /**
     * Test findReserves using Solr index with results
     *
     * @return void
     */
    public function testFindReservesUsingSolrIndexWithResults(): void
    {
        $searchService = $this->createMock(Service::class);
        $catalog = $this->createMock(Connection::class);

        $reserveRecord = $this->createMock(SolrReserves::class);

        $reserveRecord->expects($this->once())
            ->method('getInstructor')
            ->willReturn('Dr. Johnson');

        $reserveRecord->expects($this->once())
            ->method('getCourse')
            ->willReturn('MATH201');

        $reserveRecord->expects($this->once())
            ->method('getItemIds')
            ->willReturn(['bib123', 'bib456', 'bib789']);

        $recordCollection = $this->createMock(RecordCollectionInterface::class);

        $recordCollection->expects($this->once())
            ->method('getTotal')
            ->willReturn(1);

        $recordCollection->expects($this->once())
            ->method('getRecords')
            ->willReturn([$reserveRecord]);

        $commandResponse = $this->createMock(\VuFindSearch\Command\RetrieveCommand::class);
        $commandResponse->expects($this->once())
            ->method('getResult')
            ->willReturn($recordCollection);

        $searchService->expects($this->once())
            ->method('invoke')
            ->with($this->callback(function ($command) {
                return $command instanceof RetrieveCommand
                && $command->getTargetIdentifier() === 'SolrReserves'
                && $command->getArguments()[0] === 'MATH201|johnson|Mathematics';
            }))
            ->willReturn($commandResponse);

        $helper = new ReservesHelper(true, $searchService, $catalog);
        $result = $helper->findReserves('MATH201', 'johnson', 'Mathematics');

        $expectedResult = [
            [
                'BIB_ID' => 'bib123',
                'bib_id' => 'bib123',
                'course' => 'MATH201',
                'instructor' => 'Dr. Johnson',
            ],
            [
                'BIB_ID' => 'bib456',
                'bib_id' => 'bib456',
                'course' => 'MATH201',
                'instructor' => 'Dr. Johnson',
            ],
            [
                'BIB_ID' => 'bib789',
                'bib_id' => 'bib789',
                'course' => 'MATH201',
                'instructor' => 'Dr. Johnson',
            ],
        ];

        $this->assertSame($expectedResult, $result);
    }

    /**
     * Test findReserves using Solr index with no results
     *
     * @return void
     */
    public function testFindReservesUsingSolrIndexWithNoResults(): void
    {
        $searchService = $this->createMock(Service::class);
        $catalog = $this->createMock(Connection::class);

        $recordCollection = $this->createMock(RecordCollectionInterface::class);

        $recordCollection->expects($this->once())
            ->method('getTotal')
            ->willReturn(0);

        $recordCollection->expects($this->never())
            ->method('getRecords');

        $commandResponse = $this->createMock(\VuFindSearch\Command\RetrieveCommand::class);
        $commandResponse->expects($this->once())
            ->method('getResult')
            ->willReturn($recordCollection);

        $searchService->expects($this->once())
            ->method('invoke')
            ->with($this->callback(function ($command) {
                return $command instanceof RetrieveCommand;
            }))
            ->willReturn($commandResponse);

        $helper = new ReservesHelper(true, $searchService, $catalog);
        $result = $helper->findReserves('PHYS301', 'doe', 'Physics');

        $this->assertSame([], $result);
    }

    /**
     * Test findReserves using catalog with null parameters
     *
     * @return void
     */
    public function testFindReservesUsingCatalogWithNullParameters(): void
    {
        $catalog = $this->createMock(Connection::class);

        $expectedReserves = [
            ['BIB_ID' => 'all1', 'course' => 'Various', 'instructor' => 'Various'],
        ];

        $catalog->expects($this->once())
            ->method('__call')
            ->with('findReserves', [null, null, null])
            ->willReturn($expectedReserves);

        $helper = new ReservesHelper(false, null, $catalog);
        $result = $helper->findReserves(null, null, null);

        $this->assertSame($expectedReserves, $result);
    }
}
