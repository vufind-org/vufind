<?php

/**
 * Unit tests for Summon Backend class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2013.
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
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindSearch\Backend\Summon;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SerialsSolutions_Summon_Exception as SummonException;
use SerialsSolutions_Summon_Query as SummonQuery;
use VuFindSearch\ParamBag;
use VuFindSearch\Query\Query;

/**
 * Unit tests for Summon Backend class.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class BackendTest extends TestCase
{
    use \VuFindTest\Feature\FixtureTrait;
    use \VuFindTest\Feature\WithConsecutiveTrait;

    /**
     * Setup method.
     *
     * @return void
     */
    protected function setUp(): void
    {
        if (!class_exists('SerialsSolutions_Summon_Exception', true)) {
            $this->markTestIncomplete('Unable to autoload class: SerialsSolutions\Summon\Exception');
        }
    }

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
            ->will($this->returnValue($this->loadResponse('single-record')));

        $back = new Backend($conn);
        $back->setIdentifier('test');
        $coll = $back->retrieve('FETCH-gale_primary_3281657081');
        $this->assertCount(1, $coll);
        $this->assertEquals('test', $coll->getSourceIdentifier());
        $rec  = $coll->first();
        $this->assertEquals('test', $rec->getSourceIdentifier());
        $this->assertEquals('FETCH-gale_primary_3281657081', $rec->ID[0]);
    }

    /**
     * Test retrieving multiple records.
     *
     * @return void
     */
    public function testRetrieveBatch()
    {
        $conn = $this->getConnectorMock(['query']);
        $expected1 = new SummonQuery(null, ['idsToFetch' => range(1, 50), 'pageNumber' => 1, 'pageSize' => 50]);
        $expected2 = new SummonQuery(null, ['idsToFetch' => range(51, 60), 'pageNumber' => 1, 'pageSize' => 50]);
        $this->expectConsecutiveCalls(
            $conn,
            'query',
            [[$expected1], [$expected2]],
            [$this->loadResponse('retrieve1'), $this->loadResponse('retrieve2')]
        );

        $back = new Backend($conn);
        $back->setIdentifier('test');
        $coll = $back->retrieveBatch(range(1, 60)); // not using real IDs here
        $this->assertCount(60, $coll);
        $this->assertEquals('test', $coll->getSourceIdentifier());
        $rec  = $coll->first();
        $this->assertEquals('test', $rec->getSourceIdentifier());
        $this->assertEquals('FETCH-gale_primary_3281657083', $rec->ID[0]);
        $recs = $coll->getRecords();
        $rec  = $recs[59];
        $this->assertEquals('test', $rec->getSourceIdentifier());
        $this->assertEquals('FETCH-proquest_dll_1469780613', $rec->ID[0]);
    }

    /**
     * Test retrieve exception handling.
     *
     * @return void
     */
    public function testRetrieveWrapsSummonException()
    {
        $this->expectException(\VuFindSearch\Backend\Exception\BackendException::class);

        $fact = $this->createMock(\VuFindSearch\Response\RecordCollectionFactoryInterface::class);
        $conn = $this->getConnectorMock(['getRecord']);
        $conn->expects($this->once())
            ->method('getRecord')
            ->will($this->throwException(new SummonException()));
        $back = new Backend($conn, $fact);
        $back->retrieve('id');
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
        $coll = $back->search(new Query('foobar'), 0, 3);
        $this->assertCount(3, $coll);
        $this->assertEquals('test', $coll->getSourceIdentifier());
        $rec  = $coll->first();
        $this->assertEquals('test', $rec->getSourceIdentifier());
        $this->assertEquals('FETCH-proquest_dll_23240310011', $rec->ID[0]);
        $recs = $coll->getRecords();
        $this->assertEquals('test', $recs[1]->getSourceIdentifier());
        $this->assertEquals('FETCH-proquest_dll_19947616111', $recs[1]->ID[0]);
        $this->assertEquals('test', $recs[2]->getSourceIdentifier());
        $this->assertEquals('FETCH-britannica_eb_129163132475image1', $recs[2]->ID[0]);
        $this->assertEquals(545, $coll->getTotal());
        $facets = $coll->getFacets();
        $this->assertEquals('Language', $facets[0]['displayName']);
        $this->assertEquals(0, $coll->getOffset());
        $this->assertEquals([], $coll->getSpellcheck());
        $this->assertEquals(false, $coll->getBestBets());
        $this->assertEquals(false, $coll->getDatabaseRecommendations());
    }

    /**
     * Test search exception handling.
     *
     * @return void
     */
    public function testSearchWrapsSummonException()
    {
        $this->expectException(\VuFindSearch\Backend\Exception\BackendException::class);

        $fact = $this->createMock(\VuFindSearch\Response\RecordCollectionFactoryInterface::class);
        $conn = $this->getConnectorMock(['query']);
        $conn->expects($this->once())
            ->method('query')
            ->will($this->throwException(new SummonException()));
        $back = new Backend($conn, $fact);
        $back->search(new Query(), 1, 1);
    }

    /**
     * Test merged param bag.
     *
     * @return void
     */
    public function testMergedParamBag()
    {
        $myParams = new ParamBag(['maxTopics' => 32]);
        $expectedParams = new SummonQuery('boo:(baz)', ['pageSize' => 10, 'pageNumber' => 1.0, 'maxTopics' => 32]);
        $conn = $this->getConnectorMock(['query']);
        $conn->expects($this->once())
            ->method('query')
            ->with($this->equalTo($expectedParams))
            ->will($this->returnValue(['recordCount' => 0, 'documents' => []]));
        $back = new Backend($conn);
        $back->search(new Query('baz', 'boo'), 0, 10, $myParams);
    }

    /**
     * Test setting a custom record collection factory.
     *
     * @return void
     */
    public function testConstructorSetters()
    {
        $fact = $this->createMock(\VuFindSearch\Response\RecordCollectionFactoryInterface::class);
        $conn = $this->getConnectorMock();
        $back = new Backend($conn, $fact);
        $this->assertEquals($fact, $back->getRecordCollectionFactory());
        $this->assertEquals($conn, $back->getConnector());
    }

    /**
     * Test setting a query builder.
     *
     * @return void
     */
    public function testSetQueryBuilder()
    {
        $qb = new QueryBuilder();
        $back = new Backend($this->getConnectorMock());
        $back->setQueryBuilder($qb);
        $this->assertEquals($qb, $back->getQueryBuilder());
    }

    /// Internal API

    /**
     * Load a Summon response as fixture.
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
            $this->getFixture("summon/response/$fixture", 'VuFindSearch')
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
        return $this->getMockBuilder(\SerialsSolutions\Summon\Laminas::class)
            ->onlyMethods($mock)
            ->setConstructorArgs(['id', 'key'])
            ->getMock();
    }
}
