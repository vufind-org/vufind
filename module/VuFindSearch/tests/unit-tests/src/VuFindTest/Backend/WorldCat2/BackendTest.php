<?php

/**
 * Unit tests for WorldCat2 backend.
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindTest\Backend\WorldCat2;

use PHPUnit\Framework\TestCase;
use VuFindSearch\Backend\WorldCat2\Backend;
use VuFindSearch\Backend\WorldCat2\Connector;
use VuFindSearch\Backend\WorldCat2\QueryBuilder;
use VuFindSearch\ParamBag;
use VuFindSearch\Query\Query;

/**
 * Unit tests for WorldCat2 backend.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class BackendTest extends TestCase
{
    /**
     * Test getting holdings.
     *
     * @return void
     */
    public function testGetHoldings(): void
    {
        $params = new ParamBag();
        $mockResponse = ['foo' => 'bar'];

        $conn = $this->createMock(Connector::class);
        $conn->expects($this->once())
            ->method('getHoldings')
            ->with($this->equalTo($params))
            ->willReturn($mockResponse);

        $back = new Backend($conn);
        $this->assertEquals($mockResponse, $back->getHoldings($params));
    }

    /**
     * Test retrieving a record.
     *
     * @return void
     */
    public function testRetrieve(): void
    {
        $fakeRecord = ['foo' => 'bar'];
        $mockResponse = [
            'docs' => [$fakeRecord],
            'offset' => 0,
            'total' => 1,
        ];
        $conn = $this->createMock(Connector::class);
        $conn->expects($this->once())
            ->method('getRecord')
            ->with($this->equalTo('foobar'))
            ->willReturn($mockResponse);

        $back = new Backend($conn);
        $back->setIdentifier('test');
        $coll = $back->retrieve('foobar');
        $this->assertCount(1, $coll);
        $this->assertEquals('test', $coll->getSourceIdentifier());
        $this->assertEquals([], $coll->getErrors());
        $rec  = $coll->first();
        $this->assertEquals('test', $rec->getSourceIdentifier());
        $this->assertEquals($fakeRecord, $rec->getRawData());
    }

    /**
     * Test performing a search.
     *
     * @return void
     */
    public function testSearch(): void
    {
        $rec1 = ['foo' => 'bar'];
        $rec2 = ['foo2' => 'bar2'];
        $rec3 = ['foo3' => 'bar3'];
        $fakeResponse = [
            'docs' => [$rec1, $rec2, $rec3],
            'offset' => 0,
            'total' => 3,
            'facets' => [['facetType' => 'foo', 'values' => [['value' => 'a', 'count' => 2]]]],
        ];
        $expectedParams = new ParamBag(['q' => 'kw:(foobar)']);
        $conn = $this->createMock(Connector::class);
        $conn->expects($this->once())
            ->method('search')
            ->with($expectedParams, 0, 3)
            ->willReturn($fakeResponse);

        $back = new Backend($conn);
        $back->setIdentifier('test');
        $coll = $back->search(new Query('foobar'), 0, 3);
        $this->assertCount(3, $coll);
        $this->assertEquals('test', $coll->getSourceIdentifier());
        $rec  = $coll->first();
        $this->assertEquals('test', $rec->getSourceIdentifier());
        $this->assertEquals($rec1, $rec->getRawData());
        $recs = $coll->getRecords();
        $this->assertEquals('test', $recs[1]->getSourceIdentifier());
        $this->assertEquals($rec2, $recs[1]->getRawData());
        $this->assertEquals('test', $recs[2]->getSourceIdentifier());
        $this->assertEquals($rec3, $recs[2]->getRawData());
        $this->assertEquals(['foo' => ['a' => 2]], $coll->getFacets());
        $this->assertEquals([], $coll->getErrors());
    }

    /**
     * Test setting a query builder.
     *
     * @return void
     */
    public function testSetQueryBuilder(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $back = new Backend($this->createMock(Connector::class));
        $this->assertNotEquals($qb, $back->getQueryBuilder());
        $back->setQueryBuilder($qb);
        $this->assertEquals($qb, $back->getQueryBuilder());
    }

    /**
     * Test setting a custom record collection factory.
     *
     * @return void
     */
    public function testConstructorSetters(): void
    {
        $fact = $this->createMock(\VuFindSearch\Response\RecordCollectionFactoryInterface::class);
        $conn = $this->createMock(Connector::class);
        $back = new Backend($conn, $fact);
        $this->assertEquals($fact, $back->getRecordCollectionFactory());
        $this->assertEquals($conn, $back->getConnector());
    }
}
