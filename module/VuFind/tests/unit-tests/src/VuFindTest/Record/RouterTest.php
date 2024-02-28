<?php

/**
 * Record router tests.
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
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Record;

use Laminas\Config\Config;
use VuFind\Record\Router;
use VuFind\RecordDriver\AbstractBase as RecordDriver;

/**
 * Record router tests.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class RouterTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test routing with driver object.
     *
     * @return void
     */
    public function testRoutingWithDriver()
    {
        $driver = $this->getDriver();
        $router = $this->getRouter();
        $this->assertEquals(
            [
                'params' => ['id' => 'test'],
                'route' => 'record',
                'options' => [
                    'normalize_path' => false,
                ],
            ],
            $router->getRouteDetails($driver)
        );
    }

    /**
     * Test routing with source|id string.
     *
     * @return void
     */
    public function testRoutingWithString()
    {
        $router = $this->getRouter();
        $this->assertEquals(
            [
                'params' => ['id' => 'test'],
                'route' => 'summonrecord',
                'options' => [
                    'normalize_path' => false,
                ],
            ],
            $router->getRouteDetails('Summon|test')
        );
    }

    /**
     * Test tab routing with source|id string.
     *
     * @return void
     */
    public function testTabRoutingWithString()
    {
        $router = $this->getRouter();
        $this->assertEquals(
            [
                'params' => ['id' => 'test', 'tab' => 'foo'],
                'route' => 'summonrecord',
                'options' => [
                    'normalize_path' => false,
                ],
            ],
            $router->getTabRouteDetails('Summon|test', 'foo')
        );
    }

    /**
     * Test collection special case with source|id string.
     *
     * @return void
     */
    public function testCollectionSpecialCaseWithString()
    {
        $router = $this->getRouter(['Collections' => ['collections' => true]]);
        $this->assertEquals(
            [
                'params' => ['id' => 'test', 'tab' => 'foo'],
                'route' => 'record',
                'options' => [
                    'normalize_path' => false,
                    'query' => ['checkRoute' => 1],
                ],
            ],
            $router->getTabRouteDetails('Solr|test', 'foo')
        );
    }

    /**
     * Test routing with source|id string including percent signs.
     *
     * @return void
     */
    public function testRoutingWithIDContainingPercent()
    {
        $router = $this->getRouter();
        $this->assertEquals(
            [
                'params' => ['id' => 'test%2Fsub'],
                'route' => 'record',
                'options' => [
                    'normalize_path' => false,
                ],
            ],
            $router->getRouteDetails('Solr|test%2Fsub')
        );
    }

    /**
     * Test collection special case with id string having no source prefix.
     *
     * @return void
     */
    public function testCollectionSpecialCaseWithStringMissingSource()
    {
        $router = $this->getRouter(['Collections' => ['collections' => true]]);
        $this->assertEquals(
            [
                'params' => ['id' => 'test', 'tab' => 'foo'],
                'route' => 'record',
                'options' => [
                    'normalize_path' => false,
                    'query' => ['checkRoute' => 1],
                ],
            ],
            $router->getTabRouteDetails('test', 'foo')
        );
    }

    /**
     * Test collection special case with driver.
     *
     * @return void
     */
    public function testCollectionSpecialCaseWithDriver()
    {
        $driver = $this->getDriver();
        $driver->expects($this->once())
            ->method('tryMethod')
            ->with($this->equalTo('isCollection'))
            ->will($this->returnValue(true));
        $router = $this->getRouter(['Collections' => ['collections' => true]]);
        $this->assertEquals(
            [
                'params' => ['id' => 'test', 'tab' => 'foo'],
                'route' => 'collection',
                'options' => [
                    'normalize_path' => false,
                ],
            ],
            $router->getTabRouteDetails($driver, 'foo')
        );
    }

    /**
     * Test routing with id string having no source prefix.
     *
     * @return void
     */
    public function testRoutingWithStringMissingSource()
    {
        $router = $this->getRouter();
        $this->assertEquals(
            [
                'params' => ['id' => 'test'],
                'route' => 'record',
                'options' => [
                    'normalize_path' => false,
                ],
            ],
            $router->getRouteDetails('test')
        );
    }

    /**
     * Test action routing with driver object.
     *
     * @return void
     */
    public function testActionRoutingWithDriver()
    {
        $driver = $this->getDriver();
        $router = $this->getRouter();
        $this->assertEquals(
            [
                'params' => ['id' => 'test'],
                'route' => 'record-sms',
                'options' => [
                    'normalize_path' => false,
                ],
            ],
            $router->getActionRouteDetails($driver, 'SMS')
        );
    }

    /**
     * Get test record driver object
     *
     * @param string $id     Record ID
     * @param string $source Record source
     *
     * @return RecordDriver
     */
    protected function getDriver($id = 'test', $source = 'Solr')
    {
        $driver = $this->createMock(\VuFind\RecordDriver\AbstractBase::class);
        $driver->expects($this->any())->method('getUniqueId')
            ->will($this->returnValue($id));
        $driver->expects($this->any())->method('getSourceIdentifier')
            ->will($this->returnValue($source));
        return $driver;
    }

    /**
     * Get test router object
     *
     * @param array $config Configuration.
     *
     * @return Router
     */
    protected function getRouter($config = [])
    {
        return new Router(new Config($config));
    }
}
