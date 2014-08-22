<?php

/**
 * Unit tests for LibGuides backend.
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

namespace VuFindTest\Backend\LibGuides;

use VuFindSearch\Backend\LibGuides\Backend;
use VuFindSearch\Backend\LibGuides\Connector;
use VuFindSearch\Backend\LibGuides\QueryBuilder;
use VuFindSearch\Backend\LibGuides\Response\RecordCollectionFactory;
use Zend\Http\Client\Adapter\Test as TestAdapter;
use Zend\Http\Client as HttpClient;
use VuFindSearch\ParamBag;
use VuFindSearch\Query\Query;
use PHPUnit_Framework_TestCase;
use InvalidArgumentException;

/**
 * Unit tests for LibGuides backend.
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
     * Test retrieving a record (not supported).
     *
     * @return void
     * @expectedException        \Exception
     * @expectedExceptionMessage retrieve() not supported by LibGuides.
     */
    public function testRetrieve()
    {
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
        $this->assertEquals('http://libguides.brynmawr.edu/tests-measures?hs=a', $rec->getUniqueID());
        $recs = $coll->getRecords();
        $this->assertEquals('test', $recs[1]->getSourceIdentifier());
        $this->assertEquals('http://libguides.brynmawr.edu/psyctests-measures?hs=a', $recs[1]->getUniqueID());
        $this->assertEquals('test', $recs[2]->getSourceIdentifier());
        $this->assertEquals('http://libguides.brynmawr.edu/social-work?hs=a', $recs[2]->getUniqueID());
        $this->assertEquals(40, $coll->getTotal());
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
        $fact = $this->getMock('VuFindSearch\Response\RecordCollectionFactoryInterface');
        $conn = $this->getConnector();
        $back = new Backend($conn, $fact);
        $this->assertEquals($fact, $back->getRecordCollectionFactory());
        $this->assertEquals($conn, $back->getConnector());
    }

    /// Internal API

    /**
     * Load a response as fixture.
     *
     * @param string $fixture Fixture file
     *
     * @return mixed
     *
     * @throws InvalidArgumentException Fixture files does not exist
     */
    protected function loadResponse($fixture)
    {
        $file = realpath(sprintf('%s/libguides/response/%s', PHPUNIT_SEARCH_FIXTURES, $fixture));
        if (!is_string($file) || !file_exists($file) || !is_readable($file)) {
            throw new InvalidArgumentException(sprintf('Unable to load fixture file: %s', $fixture));
        }
        return file_get_contents($file);
    }

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
            $adapter->setResponse($this->loadResponse($fixture));
        }
        $client = new HttpClient();
        $client->setAdapter($adapter);
        return new Connector('fakeid', $client);
    }

    /**
     * Build a real record collection factory
     *
     * @return \VuFindSearch\Backend\LibGuides\Response\XML\RecordCollectionFactory
     */
    protected function getRCFactory()
    {
        $callback = function ($data) {
            $driver = new \VuFind\RecordDriver\LibGuides();
            $driver->setRawData($data);
            return $driver;
        };
        return new \VuFindSearch\Backend\LibGuides\Response\RecordCollectionFactory($callback);
    }
}
