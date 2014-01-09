<?php

/**
 * Record router tests.
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
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */

namespace VuFindTest\Record;

use VuFind\Record\Router;
use VuFind\RecordDriver\AbstractBase as RecordDriver;
use Zend\Config\Config;
use VuFindTest\Unit\TestCase as TestCase;

/**
 * Record router tests.
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class RouterTest extends TestCase
{
    /**
     * Test routing with driver object.
     *
     * @return void
     */
    public function testRoutingWithDriver()
    {
        $driver = $this->getDriver();
        $router = $this->getRouter($driver);
        $this->assertEquals(
            array('params' => array('id' => 'test'), 'route' => 'record'),
            $router->getRouteDetails($driver, '')
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
    protected function getDriver($id = 'test', $source = 'VuFind')
    {
        $driver = $this->getMock('VuFind\RecordDriver\AbstractBase');
        $driver->expects($this->any())->method('getUniqueId')
            ->will($this->returnValue($id));
        $driver->expects($this->any())->method('getResourceSource')
            ->will($this->returnValue($source));
        return $driver;
    }

    /**
     * Get test router object
     *
     * @param RecordDriver $record Record to return from loader.
     * @param array        $config Configuration.
     *
     * @return Router
     */
    protected function getRouter($record = null, $config = array())
    {
        if (null === $record) {
            $record = $this->getDriver();
        }
        $loader = $this->getMock(
            'VuFind\Record\Loader', array(),
            array(
                $this->getMock('VuFindSearch\Service'),
                $this->getMock('VuFind\RecordDriver\PluginManager')
            )
        );
        $loader->expects($this->any())->method('load')
            ->will($this->returnValue($record));

        return new Router($loader, new Config($config));
    }
}