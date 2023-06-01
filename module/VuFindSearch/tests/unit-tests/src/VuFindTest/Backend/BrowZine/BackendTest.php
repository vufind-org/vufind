<?php

/**
 * Unit tests for BrowZine backend.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2017.
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

namespace VuFindTest\Backend\BrowZine;

use Laminas\Http\Client\Adapter\Test as TestAdapter;
use Laminas\Http\Client as HttpClient;
use VuFindSearch\Backend\BrowZine\Backend;
use VuFindSearch\Backend\BrowZine\Connector;
use VuFindSearch\Backend\BrowZine\QueryBuilder;
use VuFindSearch\Backend\BrowZine\Response\RecordCollectionFactory;
use VuFindSearch\Query\Query;

/**
 * Unit tests for BrowZine backend.
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
     * Test retrieving a record (not supported).
     *
     * @return void
     */
    public function testRetrieve()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('retrieve() not supported by BrowZine.');

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
        $this->assertEquals('3264', $rec->getUniqueID());
        $recs = $coll->getRecords();
        $this->assertEquals('test', $recs[1]->getSourceIdentifier());
        $this->assertEquals('845', $recs[1]->getUniqueID());
        $this->assertEquals('test', $recs[2]->getSourceIdentifier());
        $this->assertEquals('1398', $recs[2]->getUniqueID());
        $this->assertEquals(81, $coll->getTotal());
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
                $this->getFixture('browzine/response/' . $fixture, 'VuFindSearch')
            );
        }
        $client = new HttpClient();
        $client->setAdapter($adapter);
        return new Connector($client, 'faketoken', 'fakeid');
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
        return $this->getMockBuilder(\VuFindSearch\Backend\BrowZine\Connector::class)
            ->onlyMethods($mock)
            ->setConstructorArgs([$client, 'faketoken', 'fakeid'])
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
            $driver = new \VuFind\RecordDriver\BrowZine();
            $driver->setRawData($data);
            return $driver;
        };
        return new RecordCollectionFactory($callback);
    }
}
