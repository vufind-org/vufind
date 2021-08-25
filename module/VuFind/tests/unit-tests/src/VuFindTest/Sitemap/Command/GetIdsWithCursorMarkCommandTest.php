<?php
/**
 * GetIdsWithCursorMarkCommand Test Class
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2021.
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

use VuFind\Sitemap\Command\GetIdsWithCursorMarkCommand;
use VuFindSearch\Backend\Solr\Backend;
use VuFindSearch\Backend\Solr\Response\Json\RecordCollection;
use VuFindSearch\Service;

/**
 * GetIdsWithCursorMarkCommand Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class GetIdsWithCursorMarkCommandTest extends \PHPUnit\Framework\TestCase
{
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
     * Get a mock search backend
     *
     * @param int $factoryCalls The number of times we expect custom factory setup to
     * occur
     *
     * @return Backend
     */
    protected function getMockBackend(int $factoryCalls = 1): Backend
    {
        $connector = $this
            ->getMockBuilder(\VuFindSearch\Backend\Solr\Connector::class)
            ->disableOriginalConstructor()
            ->getMock();
        $connector->expects($this->once())->method('getUniqueKey')
            ->will($this->returnValue($this->uniqueKey));
        $backend = $this->getMockBuilder(Backend::class)
            ->disableOriginalConstructor()
            ->getMock();
        $backend->expects($this->once())->method('getConnector')
            ->will($this->returnValue($connector));
        $backend->expects($this->once())->method('getIdentifier')
            ->will($this->returnValue($this->backendId));
        // We expect the command to set up a custom factory:
        $backend->expects($this->exactly($factoryCalls))
            ->method('setRecordCollectionFactory');
        return $backend;
    }

    /**
     * Get a mock search service
     *
     * @param RecordCollection $records            Record set to return
     * @param string           $expectedCursorMark Expected cursor mark
     *
     * @return Service
     */
    protected function getMockService(
        RecordCollection $records,
        string $expectedCursorMark = '*'
    ): Service {
        $expectedQuery = new \VuFindSearch\Query\Query('*:*');
        $expectedParams = new \VuFindSearch\ParamBag(
            [
                'q' => '*:*',
                'rows' => $this->countPerPage,
                'start' => 0,
                'wt' => 'json',
                'sort' => $this->uniqueKey . ' asc',
                'timeAllowed' => -1,
                'cursorMark' => $expectedCursorMark,
            ]
        );
        $service = $this->getMockBuilder(Service::class)
            ->disableOriginalConstructor()
            ->getMock();
        $service->expects($this->once())->method('getIds')
            ->with(
                $this->equalTo($this->backendId),
                $this->equalTo($expectedQuery),
                $this->equalTo(0),
                $this->equalTo($this->countPerPage),
                $this->equalTo($expectedParams)
            )->will($this->returnValue($records));
        return $service;
    }

    /**
     * Add mock records to a collection and return an array of the generated IDs.
     *
     * @param RecordCollection $records Collection to add to
     *
     * @return int[]
     */
    protected function addRecordsToCollection(RecordCollection $records): array
    {
        $expectedIds = [];
        for ($i = 0; $i < $this->countPerPage; $i++) {
            $driver = $this
                ->getMockBuilder(\VuFindSearch\Response\SimpleRecord::class)
                ->disableOriginalConstructor()->getMock();
            $driver->expects($this->once())->method('get')
                ->with($this->equalTo($this->uniqueKey))
                ->will($this->returnValue($i));
            $expectedIds[] = $i;
            $records->add($driver);
        }
        return $expectedIds;
    }

    /**
     * Test the first iteration of a cursor-mark ID retrieval process.
     *
     * @return void
     */
    public function testFirstIteration(): void
    {
        $context = ['offset' => null, 'countPerPage' => $this->countPerPage];
        $records = new RecordCollection(['nextCursorMark' => 'nextCursor']);
        $expectedIds = $this->addRecordsToCollection($records);
        $service = $this->getMockService($records);
        $backend = $this->getMockBackend();
        $command = new GetIdsWithCursorMarkCommand($this->backendId, $context, $service);
        $this->assertEquals($command, $command->execute($backend));
        $this->assertEquals(
            ['ids' => $expectedIds, 'nextOffset' => 'nextCursor'],
            $command->getResult()
        );
    }

    /**
     * Test next and last iterations of process.
     *
     * @return void
     */
    public function testSecondAndFinalIterations(): void
    {
        $context = ['offset' => 'nextCursor', 'countPerPage' => $this->countPerPage];
        $records = new RecordCollection(['nextCursorMark' => 'nextCursor']);
        $expectedIds = $this->addRecordsToCollection($records);
        $service = $this->getMockService($records, 'nextCursor');
        $backend = $this->getMockBackend(2);
        $command = new GetIdsWithCursorMarkCommand($this->backendId, $context, $service);
        $this->assertEquals($command, $command->execute($backend));
        $this->assertEquals(
            ['ids' => $expectedIds, 'nextOffset' => 'nextCursor'],
            $command->getResult()
        );
        // If we send the same cursor mark a second time, we should get no results...
        $command2 = new GetIdsWithCursorMarkCommand($this->backendId, $context, $service);
        $this->assertEquals($command2, $command2->execute($backend));
        $this->assertEquals(
            ['ids' => [], 'nextOffset' => 'nextCursor'],
            $command2->getResult()
        );
    }
}
