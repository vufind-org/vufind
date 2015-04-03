<?php

/**
 * Unit tests for WorldCat backend.
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
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
namespace VuFindTest\Backend\WorldCat;

use VuFindSearch\Backend\WorldCat\Backend;
use VuFindSearch\Query\Query;
use PHPUnit_Framework_TestCase;
use InvalidArgumentException;

/**
 * Unit tests for WorldCat backend.
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class BackendTest extends PHPUnit_Framework_TestCase
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
            ->will($this->returnValue($this->loadResponse('single-record')));

        $back = new Backend($conn);
        $back->setIdentifier('test');
        $coll = $back->retrieve('foobar');
        $this->assertCount(1, $coll);
        $this->assertEquals('test', $coll->getSourceIdentifier());
        $rec  = $coll->first();
        $this->assertEquals('test', $rec->getSourceIdentifier());
        $this->assertEquals('690250223', $rec->getMarc()->getField('001')->getData());
    }

    /**
     * Test performing a search.
     *
     * @return void
     */
    public function testSearch()
    {
        $conn = $this->getConnectorMock(['search']);
        $conn->expects($this->once())
            ->method('search')
            ->will($this->returnValue($this->loadResponse('search')));

        $back = new Backend($conn);
        $back->setIdentifier('test');
        $coll = $back->search(new Query('foobar'), 0, 3);
        $this->assertCount(3, $coll);
        $this->assertEquals('test', $coll->getSourceIdentifier());
        $rec  = $coll->first();
        $this->assertEquals('test', $rec->getSourceIdentifier());
        $this->assertEquals('793503125', $rec->getMarc()->getField('001')->getData());
        $recs = $coll->getRecords();
        $this->assertEquals('test', $recs[1]->getSourceIdentifier());
        $this->assertEquals('798169104', $recs[1]->getMarc()->getField('001')->getData());
        $this->assertEquals('test', $recs[2]->getSourceIdentifier());
        $this->assertEquals('44310183', $recs[2]->getMarc()->getField('001')->getData());
    }

    /**
     * Test setting a query builder.
     *
     * @return void
     */
    public function testSetQueryBuilder()
    {
        $qb = new \VuFindSearch\Backend\WorldCat\QueryBuilder();
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
        $file = realpath(sprintf('%s/worldcat/response/%s', PHPUNIT_SEARCH_FIXTURES, $fixture));
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
            'VuFindSearch\Backend\WorldCat\Connector',
            $mock, ['fake', $client]
        );
    }
}
