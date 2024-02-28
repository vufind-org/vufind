<?php

/**
 * Unit tests for Primo backend.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindTest\Backend\Primo;

use InvalidArgumentException;
use VuFindSearch\Backend\Primo\Backend;
use VuFindSearch\ParamBag;
use VuFindSearch\Query\Query;

/**
 * Unit tests for Primo backend.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class BackendTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    /**
     * Test retrieving a record.
     *
     * @return void
     */
    public function testRetrieve()
    {
        $conn = $this->getConnectorMock(['getRecord']);
        $conn->expects($this->once())
            ->method('getRecord')
            ->will($this->returnValue($this->loadResponse('retrieve')));

        $back = new Backend($conn);
        $back->setIdentifier('test');

        $coll = $back->retrieve('crossref10.5755/j01.ss.71.1.377');
        $this->assertCount(1, $coll);
        $this->assertEquals('test', $coll->getSourceIdentifier());
        $rec  = $coll->first();
        $this->assertEquals('test', $rec->getSourceIdentifier());
        $this->assertEquals('crossref10.5755/j01.ss.71.1.377', $rec->recordid);
    }

    /**
     * Test performing a search.
     *
     * @return void
     */
    public function testSearch()
    {
        $conn = $this->getConnectorMock(['query']);
        $conn->expects($this->once())
            ->method('query')
            ->will($this->returnValue($this->loadResponse('search')));

        $back = new Backend($conn);
        $back->setIdentifier('test');

        $coll = $back->search(new Query('Test, Test', 'Author'), 0, 3);
        $this->assertCount(3, $coll);
        $this->assertEquals('test', $coll->getSourceIdentifier());
        $rec  = $coll->first();
        $this->assertEquals('test', $rec->getSourceIdentifier());
        $this->assertEquals('crossref10.5755/j01.ss.71.1.377', $rec->recordid);
        $recs = $coll->getRecords();
        $this->assertEquals('test', $recs[1]->getSourceIdentifier());
        $this->assertEquals('crossref10.5755/j01.ss.71.2.533', $recs[1]->recordid);
        $this->assertEquals('test', $recs[2]->getSourceIdentifier());
        $this->assertEquals('crossref10.5755/j01.ss.71.2.544', $recs[2]->recordid);
        $this->assertEquals(5706, $coll->getTotal());
        $facets = $coll->getFacets();
        $this->assertCount(9, $facets);
        $this->assertCount(19, $facets['jtitle']);
        $this->assertEquals(16, $facets['jtitle']['Remedial and Special Education']);
        $this->assertEquals(0, $coll->getOffset());
    }

    /**
     * Test setting a query builder.
     *
     * @return void
     */
    public function testSetQueryBuilder()
    {
        $qb = new \VuFindSearch\Backend\Primo\QueryBuilder();
        $back = new Backend($this->getConnectorMock());
        $back->setQueryBuilder($qb);
        $this->assertEquals($qb, $back->getQueryBuilder());
    }

    /**
     * Test setting a custom record collection factory.
     *
     * @return void
     */
    public function testConstructorSetters()
    {
        $fact = $this->createMock(
            \VuFindSearch\Response\RecordCollectionFactoryInterface::class
        );
        $conn = $this->getConnectorMock();
        $back = new Backend($conn, $fact);
        $this->assertEquals($fact, $back->getRecordCollectionFactory());
        $this->assertEquals($conn, $back->getConnector());
    }

    /**
     * Test search exception handling.
     *
     * @return void
     */
    public function testSearchWrapsPrimoException()
    {
        $this->expectException(
            \VuFindSearch\Backend\Exception\BackendException::class
        );

        $conn = $this->getConnectorMock(['query']);
        $conn->expects($this->once())
            ->method('query')
            ->will($this->throwException(new \Exception()));
        $back = new Backend($conn);
        $back->search(new Query(), 1, 1);
    }

    /**
     * Test retrieve exception handling.
     *
     * @return void
     */
    public function testRetrieveWrapsPrimoException()
    {
        $this->expectException(
            \VuFindSearch\Backend\Exception\BackendException::class
        );

        $conn = $this->getConnectorMock(['getRecord']);
        $conn->expects($this->once())
            ->method('getRecord')
            ->will($this->throwException(new \Exception()));
        $back = new Backend($conn);
        $back->retrieve('1234');
    }

    /**
     * Test merged param bag.
     *
     * @return void
     */
    public function testMergedParamBag()
    {
        $myParams = new ParamBag(['foo' => 'bar']);
        $expectedParams = [
            'foo' => 'bar',
            'limit' => 10,
            'pageNumber' => 1.0,
            'query' => [
                [
                    'index' => null,
                    'lookfor' => 'baz',
                ],
            ],
        ];
        $conn = $this->getConnectorMock(['query']);
        $conn->expects($this->once())
            ->method('query')
            ->with(
                $this->equalTo('inst-id'),
                $this->equalTo($expectedParams['query']),
                $this->equalTo($expectedParams)
            )->will($this->returnValue(['recordCount' => 0, 'documents' => []]));
        $back = new Backend($conn);
        $back->search(new Query('baz'), 0, 10, $myParams);
    }

    /**
     * Data provider for testPcAvailabilityFilter
     *
     * @return array
     */
    public static function getPcAvailabilityData(): array
    {
        return [
            [
                '',
                true,
            ],
            [
                true,
                true,
            ],
            [
                1,
                true,
            ],
            [
                '1',
                true,
            ],
            [
                'true',
                true,
            ],
            [
                false,
                false,
            ],
            [
                0,
                false,
            ],
            [
                '0',
                false,
            ],
            [
                'false',
                false,
            ],
        ];
    }

    /**
     * Test pcAvailability filter.
     *
     * @param string $value    Input value of filter
     * @param string $expected Expected output value of filter
     *
     * @dataProvider getPcAvailabilityData
     *
     * @return void
     */
    public function testPcAvailabilityFilter($value, $expected): void
    {
        $params = new ParamBag(
            [
                'filterList' => [
                    [
                        'field' => 'pcAvailability',
                        'values' => [
                            $value,
                        ],
                    ],
                ],
            ]
        );
        $expectedParams = [
            'limit' => 10,
            'pageNumber' => 1,
            'filterList' => [],
            'pcAvailability' => $expected,
            'query' => [
                [
                    'index' => null,
                    'lookfor' => 'foo',
                ],
            ],
        ];
        $conn = $this->getConnectorMock(['query']);
        $conn->expects($this->once())
            ->method('query')
            ->with(
                $this->equalTo('inst-id'),
                $this->equalTo($expectedParams['query']),
                $this->equalTo($expectedParams)
            )->will($this->returnValue(['recordCount' => 0, 'documents' => []]));
        $back = new Backend($conn);
        $back->search(new Query('foo'), 0, 10, $params);
    }

    /// Internal API

    /**
     * Load a WorldCat response as fixture.
     *
     * @param string $fixture Fixture file
     *
     * @return mixed
     *
     * @throws InvalidArgumentException Fixture files does not exist
     */
    protected function loadResponse($fixture)
    {
        return unserialize(
            $this->getFixture("primo/response/$fixture", 'VuFindSearch')
        );
    }

    /**
     * Return connector mock.
     *
     * @param array $mock Functions to mock
     *
     * @return array
     */
    protected function getConnectorMock(array $mock = [])
    {
        $client = $this->createMock(\Laminas\Http\Client::class);
        return $this->getMockBuilder(\VuFindSearch\Backend\Primo\Connector::class)
            ->onlyMethods($mock)
            ->setConstructorArgs(['http://fakeaddress.none', 'inst-id', $client])
            ->getMock();
    }
}
