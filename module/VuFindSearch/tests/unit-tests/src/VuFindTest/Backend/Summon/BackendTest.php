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
     * Test retrieve exception handling.
     *
     * @return void
     * @expectedException VuFindSearch\Backend\Exception\BackendException
     */
    public function testRetrieveWrapsSummonException()
    {
        $fact = $this->getMock('VuFindSearch\Response\RecordCollectionFactoryInterface');
        $conn = $this->getMock('SerialsSolutions\Summon\Zend2', array('getRecord'), array('id', 'key'));
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
        $conn = $this->getMock('SerialsSolutions\Summon\Zend2', array('query'), array('id', 'key'));
        $conn->expects($this->once())
             ->method('query')
             ->will($this->throwException(new SummonException()));
        $back = new Backend($conn, $fact);
        $back->search(new Query(), 1, 1);
    }
}