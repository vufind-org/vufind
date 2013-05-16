<?php

/**
 * Unit tests for handler map base class.
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

namespace VuFindTest\Backend;

use VuFindSearch\Backend\AbstractHandlerMap;
use VuFindSearch\ParamBag;

use PHPUnit_Framework_TestCase as TestCase;

/**
 * Unit tests for handler map base class.
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class AbstractHandlerMapTest extends TestCase
{
    /**
     * Test parameter preparation, defaults.
     *
     * @return void
     */
    public function testPrepareDefaults()
    {
        $map = $this->getMockForAbstractClass('VuFindSearch\Backend\AbstractHandlerMap');
        $map->expects($this->once())
            ->method('getDefaults')
            ->will($this->returnValue(array('p1' => array('default'), 'p2' => array('default'))));
        $map->expects($this->once())
            ->method('getAppends')
            ->will($this->returnValue(array()));
        $map->expects($this->once())
            ->method('getInvariants')
            ->will($this->returnValue(array()));

        $params = new ParamBag(array('p2' => array('non-default')));
        $map->prepare('f', $params);
        $this->assertTrue($params->contains('p1', 'default'));
        $this->assertFalse($params->contains('p2', 'default'));
        $this->assertTrue($params->contains('p2', 'non-default'));
    }

    /**
     * Test parameter preparation, appends.
     *
     * @return void
     */
    public function testPrepareAppends()
    {
        $map = $this->getMockForAbstractClass('VuFindSearch\Backend\AbstractHandlerMap');
        $map->expects($this->once())
            ->method('getDefaults')
            ->will($this->returnValue(array()));
        $map->expects($this->once())
            ->method('getAppends')
            ->will($this->returnValue(array('p1' => 'append')));
        $map->expects($this->once())
            ->method('getInvariants')
            ->will($this->returnValue(array()));

        $params = new ParamBag(array('p1' => array('something')));
        $map->prepare('f', $params);
        $this->assertTrue($params->contains('p1', 'something'));
        $this->assertTrue($params->contains('p1', 'append'));
    }

    /**
     * Test parameter preparation, invariants.
     *
     * @return void
     */
    public function testPrepareInvariants()
    {
        $map = $this->getMockForAbstractClass('VuFindSearch\Backend\AbstractHandlerMap');
        $map->expects($this->once())
            ->method('getDefaults')
            ->will($this->returnValue(array()));
        $map->expects($this->once())
            ->method('getAppends')
            ->will($this->returnValue(array('p1' => array('append'))));
        $map->expects($this->once())
            ->method('getInvariants')
            ->will($this->returnValue(array('p1' => array('invariant'))));

        $params = new ParamBag(array('p1' => array('something')));
        $map->prepare('f', $params);
        $this->assertFalse($params->contains('p1', 'something'));
        $this->assertFalse($params->contains('p1', 'append'));
        $this->assertTrue($params->contains('p1', 'invariant'));
    }
}