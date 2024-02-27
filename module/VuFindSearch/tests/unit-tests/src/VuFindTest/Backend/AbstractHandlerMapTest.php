<?php

/**
 * Unit tests for handler map base class.
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

namespace VuFindTest\Backend;

use PHPUnit\Framework\TestCase;
use VuFindSearch\Backend\AbstractHandlerMap;
use VuFindSearch\ParamBag;

/**
 * Unit tests for handler map base class.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
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
        $map = $this->getMockForAbstractClass(AbstractHandlerMap::class);
        $map->expects($this->once())
            ->method('getDefaults')
            ->will(
                $this->returnValue(
                    new ParamBag(['p1' => ['default'], 'p2' => ['default']])
                )
            );
        $map->expects($this->once())
            ->method('getAppends')
            ->will($this->returnValue(new ParamBag()));
        $map->expects($this->once())
            ->method('getInvariants')
            ->will(
                $this->returnValue(new ParamBag())
            );

        $params = new ParamBag(['p2' => ['non-default']]);
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
        $map = $this->getMockForAbstractClass(AbstractHandlerMap::class);
        $map->expects($this->once())
            ->method('getDefaults')
            ->will($this->returnValue(new ParamBag()));
        $map->expects($this->once())
            ->method('getAppends')
            ->will($this->returnValue(new ParamBag(['p1' => 'append'])));
        $map->expects($this->once())
            ->method('getInvariants')
            ->will($this->returnValue(new ParamBag()));

        $params = new ParamBag(['p1' => ['something']]);
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
        $map = $this->getMockForAbstractClass(AbstractHandlerMap::class);
        $map->expects($this->once())
            ->method('getDefaults')
            ->will($this->returnValue(new ParamBag()));
        $map->expects($this->once())
            ->method('getAppends')
            ->will($this->returnValue(new ParamBag(['p1' => ['append']])));
        $map->expects($this->once())
            ->method('getInvariants')
            ->will($this->returnValue(new ParamBag(['p1' => ['invariant']])));

        $params = new ParamBag(['p1' => ['something']]);
        $map->prepare('f', $params);
        $this->assertFalse($params->contains('p1', 'something'));
        $this->assertFalse($params->contains('p1', 'append'));
        $this->assertTrue($params->contains('p1', 'invariant'));
    }
}
