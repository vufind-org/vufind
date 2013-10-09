<?php

/**
 * Unit tests for Summon Backend class.
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */

namespace VuFindSearch\Backend\Summon;

use VuFindSearch\Query\Query;

use SerialsSolutions_Summon_Exception as SummonException;

use PHPUnit_Framework_TestCase as TestCase;

/**
 * Unit tests for Summon Backend class.
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class BackendTest extends TestCase
{
    /**
     * Setup method.
     *
     * @return void
     */
    protected function setup()
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
        $conn = $this->getConnectorMock(array('getRecord'));
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
     * Test retrieve exception handling.
     *
     * @return void
     * @expectedException VuFindSearch\Backend\Exception\BackendException
     */
    public function testRetrieveWrapsSummonException()
    {
        $fact = $this->getMock('VuFindSearch\Response\RecordCollectionFactoryInterface');
        $conn = $this->getConnectorMock(array('getRecord'));
        $conn->expects($this->once())
             ->method('getRecord')
             ->will($this->throwException(new SummonException()));
        $back = new Backend($conn, $fact);
        $back->retrieve('id');
    }

    /**
     * Test search exception handling.
     *
     * @return void
     * @expectedException VuFindSearch\Backend\Exception\BackendException
     */
    public function testSearchWrapsSummonException()
    {
        $fact = $this->getMock('VuFindSearch\Response\RecordCollectionFactoryInterface');
        $conn = $this->getConnectorMock(array('query'));
        $conn->expects($this->once())
             ->method('query')
             ->will($this->throwException(new SummonException()));
        $back = new Backend($conn, $fact);
        $back->search(new Query(), 1, 1);
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
        $file = realpath(sprintf('%s/summon/response/%s', PHPUNIT_SEARCH_FIXTURES, $fixture));
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
    protected function getConnectorMock(array $mock = array())
    {
        return $this->getMock(
            'SerialsSolutions\Summon\Zend2', $mock, array('id', 'key')
        );
    }}