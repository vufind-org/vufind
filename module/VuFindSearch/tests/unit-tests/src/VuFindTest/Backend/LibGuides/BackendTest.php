<?php

/**
 * Unit tests for LibGuides backend.
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

namespace VuFindTest\Backend\LibGuides;

use Laminas\Http\Client\Adapter\Test as TestAdapter;
use Laminas\Http\Client as HttpClient;
use VuFindSearch\Backend\LibGuides\Backend;
use VuFindSearch\Backend\LibGuides\Connector;
use VuFindSearch\Backend\LibGuides\QueryBuilder;
use VuFindSearch\Backend\LibGuides\Response\RecordCollectionFactory;
use VuFindSearch\ParamBag;
use VuFindSearch\Query\Query;

/**
 * Unit tests for LibGuides backend.
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
    use \VuFindTest\Feature\WithConsecutiveTrait;

    /**
     * Test retrieving a record (not supported).
     *
     * @return void
     */
    public function testRetrieve()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('retrieve() not supported by LibGuides.');

        $conn = $this->getConnector();
        $back = new Backend($conn, $this->getRCFactory());
        $back->retrieve('foo');
    }

    /**
     * Test performing a search.
     *
     * @return void
     */
    public function testSearch()
    {
        $conn = $this->getConnector('search');
        $back = new Backend($conn, $this->getRCFactory());
        $back->setIdentifier('test');

        $coll = $back->search(new Query('foobar'), 0, 3);
        $this->assertCount(3, $coll);
        $this->assertEquals('test', $coll->getSourceIdentifier());
        $rec  = $coll->first();
        $this->assertEquals('test', $rec->getSourceIdentifier());
        $this->assertEquals('https://guides.tricolib.brynmawr.edu/testprep', $rec->getUniqueID());
        $recs = $coll->getRecords();
        $this->assertEquals('test', $recs[1]->getSourceIdentifier());
        $this->assertEquals('https://guides.tricolib.brynmawr.edu/tests-measures', $recs[1]->getUniqueID());
        $this->assertEquals('test', $recs[2]->getSourceIdentifier());
        $this->assertEquals('https://guides.tricolib.brynmawr.edu/psyctests-measures', $recs[2]->getUniqueID());
        $this->assertEquals(53, $coll->getTotal());
        $this->assertEquals(0, $coll->getOffset());
    }

    /**
     * Test setting a query builder.
     *
     * @return void
     */
    public function testSetQueryBuilder()
    {
        $qb = new QueryBuilder();
        $back = new Backend($this->getConnector(), $this->getRCFactory());
        $back->setQueryBuilder($qb);
        $this->assertEquals($qb, $back->getQueryBuilder());
    }

    /**
     * Test generation of a default query builder.
     *
     * @return void
     */
    public function testDefaultQueryBuilder()
    {
        $back = new Backend($this->getConnector(), $this->getRCFactory());
        $this->assertTrue($back->getQueryBuilder() instanceof QueryBuilder);
    }

    /**
     * Test setting a custom record collection factory.
     *
     * @return void
     */
    public function testConstructorSetters()
    {
        $fact = $this->createMock(\VuFindSearch\Response\RecordCollectionFactoryInterface::class);
        $conn = $this->getConnector();
        $back = new Backend($conn, $fact);
        $this->assertEquals($fact, $back->getRecordCollectionFactory());
        $this->assertEquals($conn, $back->getConnector());
    }

    /**
     * Test construction of default record collection factory.
     *
     * @return void
     */
    public function testDefaultRecordCollectionFactory()
    {
        $back = new Backend($this->getConnector());
        $this->assertTrue($back->getRecordCollectionFactory() instanceof RecordCollectionFactory);
    }

    /**
     * Test search exception handling.
     *
     * @return void
     */
    public function testSearchWrapsLibGuidesException()
    {
        $this->expectException(\VuFindSearch\Backend\Exception\BackendException::class);

        $conn = $this->getConnectorMock(['query']);
        $conn->expects($this->once())
            ->method('query')
            ->will($this->throwException(new \Exception()));
        $back = new Backend($conn);
        $back->search(new Query(), 1, 1);
    }

    /**
     * Test merged param bag.
     *
     * @return void
     */
    public function testMergedParamBag()
    {
        $myParams = new ParamBag(['foo' => 'bar']);
        $expectedParams = ['foo' => 'bar', 'search' => 'baz', 'widget_type' => '1'];
        $conn = $this->getConnectorMock(['query']);
        $conn->expects($this->once())
            ->method('query')
            ->with($this->equalTo($expectedParams), $this->equalTo(0), $this->equalTo(10))
            ->will($this->returnValue(['recordCount' => 0, 'documents' => []]));
        $back = new Backend($conn);
        $back->search(new Query('baz'), 0, 10, $myParams);
    }

    /**
     * Test default search failover.
     *
     * @return void
     */
    public function testSearchFallback()
    {
        $conn = $this->getConnectorMock(['query']);
        $expectedParams0 = ['search' => 'baz', 'widget_type' => '1'];
        $expectedParams1 = ['search' => 'fallback', 'widget_type' => '1'];
        $this->expectConsecutiveCalls(
            $conn,
            'query',
            [[$expectedParams0, 0, 10], [$expectedParams1, 0, 10]],
            [
                ['recordCount' => 0, 'documents' => []],
                ['recordCount' => 0, 'documents' => []],
            ]
        );
        $back = new Backend($conn, null, 'fallback');
        $back->search(new Query('baz'), 0, 10);
    }

    /// Internal API

    /**
     * Return connector.
     *
     * @param string $fixture HTTP response fixture to load (optional)
     *
     * @return Connector
     */
    protected function getConnector($fixture = null)
    {
        $adapter = new TestAdapter();
        if ($fixture) {
            $adapter->setResponse(
                $this->getFixture("libguides/response/$fixture", 'VuFindSearch')
            );
        }
        $client = new HttpClient();
        $client->setAdapter($adapter);
        return new Connector('fakeid', $client);
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
        return $this->getMockBuilder(\VuFindSearch\Backend\LibGuides\Connector::class)
            ->onlyMethods($mock)
            ->setConstructorArgs(['fakeid', $client])
            ->getMock();
    }

    /**
     * Build a real record collection factory
     *
     * @return RecordCollectionFactory
     */
    protected function getRCFactory()
    {
        $callback = function ($data) {
            $driver = new \VuFind\RecordDriver\LibGuides();
            $driver->setRawData($data);
            return $driver;
        };
        return new RecordCollectionFactory($callback);
    }
}
