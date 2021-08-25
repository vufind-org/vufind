<?php
/**
 * GetIdsWithTermsCommand Test Class
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

use VuFind\Sitemap\Command\GetIdsWithTermsCommand;
use VuFindSearch\Backend\Solr\Backend;
use VuFindSearch\Backend\Solr\Response\Json\Terms;
use VuFindSearch\Service;

/**
 * GetIdsWithTermsCommand Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class GetIdsWithTermsCommandTest extends \PHPUnit\Framework\TestCase
{
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
     * Get a terms response
     *
     * @param int[] $expectedIds IDs to return in response
     *
     * @return Terms
     */
    protected function getTermsResponse(array $expectedIds): Terms
    {
        $ids = [];
        foreach ($expectedIds as $id) {
            $ids[] = [$id, $id];
        }
        return new Terms(
            [
                'terms' => [
                    $this->uniqueKey => $ids
                ]
            ]
        );
    }

    /**
     * Get a mock search backend
     *
     * @param int[]  $expectedIds    IDs to return in response
     * @param string $expectedOffset Expected offset
     *
     * @return Backend
     */
    protected function getMockBackend(
        array $expectedIds,
        $expectedOffset = ''
    ): Backend {
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
        $backend->expects($this->once())->method('terms')
            ->with(
                $this->equalTo($this->uniqueKey),
                $this->equalTo($expectedOffset),
                $this->equalTo($this->countPerPage)
            )->will($this->returnValue($this->getTermsResponse($expectedIds)));
        return $backend;
    }

    /**
     * Get mock search service
     *
     * @return Service
     */
    protected function getMockService(): Service
    {
        return $this->getMockBuilder(Service::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * Test the first iteration of a terms retrieval process.
     *
     * @return void
     */
    public function testFirstIteration(): void
    {
        $context = ['offset' => null, 'countPerPage' => $this->countPerPage];
        $expectedIds = range(0, $this->countPerPage - 1);
        $backend = $this->getMockBackend($expectedIds);
        $command = new GetIdsWithTermsCommand(
            'foo',
            $context,
            $this->getMockService()
        );
        $this->assertEquals($command, $command->execute($backend));
        $this->assertEquals(
            ['ids' => $expectedIds, 'nextOffset' => 99],
            $command->getResult()
        );
    }

    /**
     * Test last iteration of process.
     *
     * @return void
     */
    public function testFinalIteration(): void
    {
        $context = ['offset' => 99, 'countPerPage' => $this->countPerPage];
        $expectedIds = [];
        $backend = $this->getMockBackend($expectedIds, 99);
        $command = new GetIdsWithTermsCommand(
            'foo',
            $context,
            $this->getMockService()
        );
        $this->assertEquals($command, $command->execute($backend));
        $this->assertEquals(
            ['ids' => $expectedIds, 'nextOffset' => null],
            $command->getResult()
        );
    }
}
