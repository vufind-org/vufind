<?php

/**
 * Unit tests for SOLR HandlerMap.
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

namespace VuFindTest\Backend\Solr;

use VuFindSearch\Backend\Solr\HandlerMap;

use PHPUnit_Framework_TestCase as TestCase;

use InvalidArgumentException;
use RuntimeException;

/**
 * Unit tests for SOLR HandlerMap.
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class HandlerMapTest extends TestCase
{
    /**
     * Test exception on duplicate fallback handler.
     *
     * @return void
     *
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Duplicate fallback
     */
    public function testSetHandlerMapThrowsOnDuplicateFallback()
    {
        $map = array(
            'h1' => array('fallback' => true),
            'h2' => array('fallback' => true),
        );
        new HandlerMap($map);
    }

    /**
     * Test exception on duplicate handler.
     *
     * @return void
     *
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Handler for function already defined
     */
    public function testSetHandlerMapThrowsOnDuplicateFunctionHandler()
    {
        $map = array(
            'h1' => array('functions' => array('foo')),
            'h2' => array('functions' => array('foo')),
        );
        new HandlerMap($map);
    }

    /**
     * Test exception on undefined handler.
     *
     * @return void
     *
     * @expectedException RuntimeException
     * @expectedExceptionMessage Undefined function handler
     */
    public function testGetHandlerThrowsOnUndefinedFunctionHandler()
    {
        $map = new HandlerMap(array());
        $map->getHandler('search');
    }

    /**
     * Test retrieve defaults, appends, invariants.
     *
     * @return void
     */
    public function testGetDefaultsAppendsInvariants()
    {
        $map = new HandlerMap(
            array(
                'search' => array(
                    'functions' => array('search'),
                    'invariants' => array('p1' => 'v1'),
                    'defaults' => array('p2' => 'v2'),
                    'appends' => array('p3' => 'v3'),
                )
            )
        );
        $this->assertEquals(
            array('p1' => array('v1')),
            $map->getInvariants('search')->getArrayCopy()
        );
        $this->assertEquals(
            array('p2' => array('v2')),
            $map->getDefaults('search')->getArrayCopy()
        );
        $this->assertEquals(
            array('p3' => array('v3')),
            $map->getAppends('search')->getArrayCopy()
        );
    }
}