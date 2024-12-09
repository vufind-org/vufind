<?php

/**
 * Unit tests for Pazpar2 backend.
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
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindTest\Backend\Pazpar2;

use InvalidArgumentException;
use VuFindSearch\Backend\Pazpar2\Backend;
use VuFindSearch\Query\Query;

/**
 * Unit tests for Pazpar2 backend.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class BackendTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;
    use \VuFindTest\Feature\ReflectionTrait;

    /**
     * Test that getConnector works.
     *
     * @return void
     */
    public function testGetConnector()
    {
        $connector = $this->getMockBuilder(\VuFindSearch\Backend\Pazpar2\Connector::class)
            ->setConstructorArgs(['http://fake', $this->createMock(\Laminas\Http\Client::class)])
            ->getMock();
        $back = new Backend(
            $connector,
            $this->createMock(\VuFindSearch\Response\RecordCollectionFactoryInterface::class)
        );
        $this->assertEquals($connector, $back->getConnector());
    }

    /**
     * Test performing a search.
     *
     * @return void
     */
    public function testSearch()
    {
        $conn = $this->getConnectorMock(['search', 'show', 'stat']);
        $conn->expects($this->once())
            ->method('search')
            ->will($this->returnValue($this->loadResponse('pp2search')));
        $conn->expects($this->once())
            ->method('show')
            ->will($this->returnValue($this->loadResponse('pp2show')));
        $conn->expects($this->exactly(2))
            ->method('stat')
            ->willReturnOnConsecutiveCalls(
                simplexml_load_string($this->getStatXml(0.5)),
                simplexml_load_string($this->getStatXml(1.0))
            );

        $back = new Backend($conn);
        $back->setIdentifier('test');
        $coll = $back->search(new Query('foobar'), 0, 20);
        $this->assertCount(20, $coll);
        $this->assertEquals('test', $coll->getSourceIdentifier());
        $rec  = $coll->first();
        $this->assertEquals('test', $rec->getSourceIdentifier());
        $this->assertEquals('content: author test title test medium book', (string)$rec->getXML()->recid);
        $recs = $coll->getRecords();
        $this->assertEquals('test', $recs[19]->getSourceIdentifier());
        $this->assertEquals(
            'content: author navalani k author gidwani n n title a practical guide to colon classification medium book',
            (string)$recs[19]->getXML()->recid
        );
        $this->assertEquals(54, $coll->getTotal());
    }

    /**
     * Test setter.
     *
     * @return void
     */
    public function testSetSearchProgressTarget()
    {
        $back = new Backend($this->getConnectorMock());
        $back->setSearchProgressTarget(0.75);
        $this->assertEquals(0.75, $this->getProperty($back, 'progressTarget'));
    }

    /**
     * Test setter.
     *
     * @return void
     */
    public function testSetMaxQueryTime()
    {
        $back = new Backend($this->getConnectorMock());
        $back->setMaxQueryTime(3);
        $this->assertEquals(3, $this->getProperty($back, 'maxQueryTime'));
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
        $xml = $this->getFixture("pazpar2/response/$fixture", 'VuFindSearch');
        return simplexml_load_string($xml);
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
        return $this->getMockBuilder(\VuFindSearch\Backend\Pazpar2\Connector::class)
            ->onlyMethods($mock)
            ->setConstructorArgs(['fake', $client])
            ->getMock();
    }

    /**
     * Get a fake stat response.
     *
     * @param float $progress How far?
     *
     * @return string
     */
    protected function getStatXml($progress)
    {
        return '<?xml version="1.0" encoding="UTF-8"?><stat><progress>'
            . $progress . '</progress></stat>';
    }
}
