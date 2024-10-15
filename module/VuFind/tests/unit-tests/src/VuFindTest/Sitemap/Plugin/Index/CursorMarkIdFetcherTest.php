<?php

/**
 * CursorMarkIdFetcher Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2021, 2022.
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Sitemap\Command;

use PHPUnit\Framework\MockObject\MockObject;
use VuFind\Sitemap\Plugin\Index\CursorMarkIdFetcher;
use VuFindSearch\Backend\Solr\Response\Json\RecordCollection;
use VuFindSearch\Command\GetIdsCommand;
use VuFindSearch\Command\GetUniqueKeyCommand;
use VuFindSearch\Command\SetRecordCollectionFactoryCommand;
use VuFindSearch\Service;

/**
 * CursorMarkIdFetcher Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class CursorMarkIdFetcherTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\WithConsecutiveTrait;

    /**
     * Backend ID to use in tests
     *
     * @var string
     */
    protected $backendId = 'foo';

    /**
     * Unique key field to use in tests
     *
     * @var string
     */
    protected $uniqueKey = 'id';

    /**
     * Page size to use in tests
     *
     * @var int
     */
    protected $countPerPage = 100;

    /**
     * Get a mock search service
     *
     * @return MockObject&Service
     */
    protected function getMockService(): MockObject&Service
    {
        return $this->createMock(Service::class);
    }

    /**
     * Add mock records to a collection and return an array of the generated IDs.
     *
     * @param RecordCollection $records Collection to add to
     * @param int              $offset  Offset for records
     * @param ?string          $lastMod Last modification date (optional)
     *
     * @return int[]
     */
    protected function addRecordsToCollection(
        RecordCollection $records,
        int $offset = 0,
        ?string $lastMod = null
    ): array {
        $expectedIds = [];
        for ($i = 0; $i < $this->countPerPage; $i++) {
            $driver = $this->createMock(\VuFindSearch\Response\SimpleRecord::class);
            $map = [
                [$this->uniqueKey, $i + $offset],
                ['last_indexed', $lastMod],
            ];
            $driver->method('get')->willReturnMap($map);
            $expectedIds[] = $i + $offset;
            $records->add($driver);
        }
        return $expectedIds;
    }

    /**
     * Get a mock "GetUniqueKeyCommand" for testing purposes.
     *
     * @return MockObject&GetUniqueKeyCommand
     */
    protected function getMockKeyCommand(): MockObject&GetUniqueKeyCommand
    {
        $command = $this->createMock(GetUniqueKeyCommand::class);
        $command->expects($this->once())->method('getResult')
            ->willReturn($this->uniqueKey);
        return $command;
    }

    /**
     * Test that calling the setupBackend method runs the
     * SetRecordCollectionFactoryCommand.
     *
     * @return void
     */
    public function testSetupBackend(): void
    {
        $service = $this->getMockService();
        $service->expects($this->once())->method('invoke')
            ->with($this->isInstanceOf(SetRecordCollectionFactoryCommand::class));

        $fetcher = new CursorMarkIdFetcher($service);
        $fetcher->setupBackend($this->backendId);
    }

    /**
     * Get a function to test that a GetIdsCommand is as expected.
     *
     * @param string   $expectedCursorMark Expected cursor mark
     * @param string[] $expectedFq         Expected filter query
     *
     * @return callable
     */
    protected function getIdsExpectation(
        string $expectedCursorMark,
        array $expectedFq = []
    ): callable {
        return function ($command) use ($expectedCursorMark, $expectedFq) {
            $expectedParams = [
                'q' => '*:*',
                'rows' => $this->countPerPage,
                'start' => 0,
                'wt' => 'json',
                'sort' => $this->uniqueKey . ' asc',
                'timeAllowed' => -1,
                'cursorMark' => $expectedCursorMark,
            ];
            if (!empty($expectedFq)) {
                $expectedParams['fq'] = $expectedFq;
            }
            $expectedParams['fl'] = 'last_indexed';
            $this->assertEquals(
                new \VuFindSearch\ParamBag($expectedParams),
                $command->getSearchParameters()
            );
            $this->assertInstanceOf(GetIdsCommand::class, $command);
            return true;
        };
    }

    /**
     * Test the cursor-mark ID retrieval process.
     *
     * @return void
     */
    public function testFetching(): void
    {
        $records1 = new RecordCollection(['nextCursorMark' => 'nextCursor']);
        $expectedIds1 = $this->addRecordsToCollection($records1);
        $nullify = function () {
            return null;
        };
        $expectedMods1 = array_map($nullify, $expectedIds1);
        $records2 = new RecordCollection(['nextCursorMark' => 'nextCursor']);
        $expectedIds2 = $this->addRecordsToCollection($records2, $this->countPerPage);
        $expectedMods2 = array_map($nullify, $expectedIds2);
        $service = $this->getMockService();
        $commandObj = $this->getMockBuilder(\VuFindSearch\Command\AbstractBase::class)
            ->disableOriginalConstructor()
            ->getMock();
        $commandObj->expects($this->exactly(2))->method('getResult')
            ->willReturnOnConsecutiveCalls($records1, $records2);

        // Set up all the expected commands...
        $this->expectConsecutiveCalls(
            $service,
            'invoke',
            [
                [$this->isInstanceOf(GetUniqueKeyCommand::class)],
                [$this->callback($this->getIdsExpectation('*'))],
                [$this->isInstanceOf(GetUniqueKeyCommand::class)],
                [$this->callback($this->getIdsExpectation('nextCursor'))],
            ],
            [
                $this->getMockKeyCommand(),
                $commandObj,
                $this->getMockKeyCommand(),
                $commandObj,
            ]
        );

        $fetcher = new CursorMarkIdFetcher($service);
        // Initial iteration
        $this->assertEquals(
            ['ids' => $expectedIds1, 'nextOffset' => 'nextCursor', 'lastmods' => $expectedMods1],
            $fetcher->getIdsFromBackend(
                $this->backendId,
                $fetcher->getInitialOffset(),
                $this->countPerPage,
                []
            )
        );
        // Second iteration
        $this->assertEquals(
            ['ids' => $expectedIds2, 'nextOffset' => 'nextCursor', 'lastmods' => $expectedMods2],
            $fetcher->getIdsFromBackend(
                $this->backendId,
                'nextCursor',
                $this->countPerPage,
                []
            )
        );
        // If we send the same cursor mark a second time, we should get no results...
        $this->assertEquals(
            ['ids' => []],
            $fetcher->getIdsFromBackend(
                $this->backendId,
                'nextCursor',
                $this->countPerPage,
                []
            )
        );
    }

    /**
     * Test passing filters.
     *
     * @return void
     */
    public function testWithFilters(): void
    {
        $records = new RecordCollection(['nextCursorMark' => 'nextCursor']);
        $lastMod = 'fake-date';
        $expectedIds = $this->addRecordsToCollection($records, lastMod: $lastMod);
        $injectDate = function () use ($lastMod) {
            return $lastMod;
        };
        $expectedMods = array_map($injectDate, $expectedIds);
        $service = $this->getMockService();
        $fq = ['format:Book'];
        $commandObj = $this->createMock(\VuFindSearch\Command\AbstractBase::class);
        $commandObj->expects($this->once())->method('getResult')->willReturn($records);
        // Set up all the expected commands...
        $this->expectConsecutiveCalls(
            $service,
            'invoke',
            [
                [$this->isInstanceOf(GetUniqueKeyCommand::class)],
                [$this->callback($this->getIdsExpectation('*', $fq))],
            ],
            [
                $this->getMockKeyCommand(),
                $commandObj,
            ]
        );

        $fetcher = new CursorMarkIdFetcher($service);
        // Initial iteration
        $this->assertEquals(
            ['ids' => $expectedIds, 'nextOffset' => 'nextCursor', 'lastmods' => $expectedMods],
            $fetcher->getIdsFromBackend(
                $this->backendId,
                $fetcher->getInitialOffset(),
                $this->countPerPage,
                $fq
            )
        );
    }
}
