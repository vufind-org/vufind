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
     * @expectedException        InvalidArgumentException
     * @expectedExceptionMessage Duplicate fallback
     */
    public function testSetHandlerMapThrowsOnDuplicateFallback()
    {
        $map = [
            'h1' => ['fallback' => true],
            'h2' => ['fallback' => true],
        ];
        new HandlerMap($map);
    }

    /**
     * Test exception on duplicate handler.
     *
     * @return void
     *
     * @expectedException        InvalidArgumentException
     * @expectedExceptionMessage Handler for function already defined
     */
    public function testSetHandlerMapThrowsOnDuplicateFunctionHandler()
    {
        $map = [
            'h1' => ['functions' => ['foo']],
            'h2' => ['functions' => ['foo']],
        ];
        new HandlerMap($map);
    }

    /**
     * Test exception on undefined handler.
     *
     * @return void
     *
     * @expectedException        RuntimeException
     * @expectedExceptionMessage Undefined function handler
     */
    public function testGetHandlerThrowsOnUndefinedFunctionHandler()
    {
        $map = new HandlerMap([]);
        $map->getHandler('search');
    }

    /**
     * Test exception on unexpected type.
     *
     * @return void
     *
     * @expectedException        InvalidArgumentException
     * @expectedExceptionMessage Invalid parameter key: bad
     */
    public function testGetParametersThrowsOnUndefinedType()
    {
        $map = new HandlerMap(['h1' => ['functions' => ['foo']]]);
        $map->getParameters('h1', 'bad');
    }

    /**
     * Test exception on unexpected type.
     *
     * @return void
     *
     * @expectedException        InvalidArgumentException
     * @expectedExceptionMessage Invalid parameter key: bad
     */
    public function testSetParametersThrowsOnUndefinedType()
    {
        $map = new HandlerMap(['h1' => ['functions' => ['foo']]]);
        $map->setParameters('h1', 'bad', []);
    }

    /**
     * Test retrieve defaults, appends, invariants.
     *
     * @return void
     */
    public function testGetDefaultsAppendsInvariants()
    {
        $map = new HandlerMap(
            [
                'search' => [
                    'functions' => ['search'],
                    'invariants' => ['p1' => 'v1'],
                    'defaults' => ['p2' => 'v2'],
                    'appends' => ['p3' => 'v3'],
                ]
            ]
        );
        $this->assertEquals(
            ['p1' => ['v1']],
            $map->getInvariants('search')->getArrayCopy()
        );
        $this->assertEquals(
            ['p2' => ['v2']],
            $map->getDefaults('search')->getArrayCopy()
        );
        $this->assertEquals(
            ['p3' => ['v3']],
            $map->getAppends('search')->getArrayCopy()
        );
    }

    /**
     * Test defaults, appends, invariants for pure fallback definitions.
     *
     * @return void
     *
     * @see http://vufind.org/jira/browse/VUFIND-820 VUFIND-820
     */
    public function testGetDefaultsAppendsInvariantsPureFallback()
    {
        $map = new HandlerMap(
            [
                'search' => [
                    'fallback' => true,
                    'invariants' => ['p1' => 'v1'],
                    'defaults' => ['p2' => 'v2'],
                    'appends' => ['p3' => 'v3'],
                ]
            ]
        );
        $this->assertEquals(
            ['p1' => ['v1']],
            $map->getInvariants('search')->getArrayCopy()
        );
        $this->assertEquals(
            ['p2' => ['v2']],
            $map->getDefaults('search')->getArrayCopy()
        );
        $this->assertEquals(
            ['p3' => ['v3']],
            $map->getAppends('search')->getArrayCopy()
        );
    }

    /**
     * Test addParameter
     *
     * @return void
     */
    public function testAddParameter()
    {
        $map = new HandlerMap(
            [
                'search' => [
                    'functions' => ['search'],
                    'invariants' => ['p1' => 'v1'],
                ]
            ]
        );
        $map->addParameter('search', 'invariants', 'p2', 'v2');
        $this->assertEquals(
            ['p1' => ['v1'], 'p2' => ['v2']],
            $map->getInvariants('search')->getArrayCopy()
        );
    }
}