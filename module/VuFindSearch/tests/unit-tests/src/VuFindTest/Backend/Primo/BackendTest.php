<?php

/**
 * Unit tests for Primo backend.
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
namespace VuFindTest\Backend\Primo;

use VuFindSearch\Backend\Primo\Backend;
use VuFindSearch\ParamBag;
use VuFindSearch\Query\Query;
use InvalidArgumentException;

/**
 * Unit tests for Primo backend.
 *
 * @category VuFind2
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class BackendTest extends \VuFindTest\Unit\TestCase
{
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
        $this->assertEquals(9, count($facets));
        $this->assertEquals(19, count($facets['jtitle']));
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
        $fact = $this->getMock('VuFindSearch\Response\RecordCollectionFactoryInterface');
        $conn = $this->getConnectorMock();
        $back = new Backend($conn, $fact);
        $this->assertEquals($fact, $back->getRecordCollectionFactory());
        $this->assertEquals($conn, $back->getConnector());
    }

    /**
     * Test search exception handling.
     *
     * @return void
     *
     * @expectedException VuFindSearch\Backend\Exception\BackendException
     */
    public function testSearchWrapsPrimoException()
    {
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
     *
     * @expectedException VuFindSearch\Backend\Exception\BackendException
     */
    public function testRetrieveWrapsPrimoException()
    {
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
        $expectedParams = ['foo' => 'bar', 'limit' => 10, 'pageNumber' => 1.0, 'query' => [['index' => null, 'lookfor' => 'baz']]];
        $conn = $this->getConnectorMock(['query']);
        $conn->expects($this->once())
            ->method('query')
            ->with($this->equalTo('inst-id'), $this->equalTo($expectedParams['query']), $this->equalTo($expectedParams))
            ->will($this->returnValue(['recordCount' => 0, 'documents' => []]));
        $back = new Backend($conn);
        $back->search(new Query('baz'), 0, 10, $myParams);
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
        $file = realpath(sprintf('%s/primo/response/%s', PHPUNIT_SEARCH_FIXTURES, $fixture));
        if (!is_string($file) || !file_exists($file) || !is_readable($file)) {
            throw new InvalidArgumentException(sprintf('Unable to load fixture file: %s', $fixture));
        }
        return unserialize(file_get_contents($file));
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
        $client = $this->getMock('Zend\Http\Client');
        return $this->getMock(
            'VuFindSearch\Backend\Primo\Connector', $mock,
            ['api-id', 'inst-id', $client]
        );
    }
}
