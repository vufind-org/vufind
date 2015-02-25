<?php
/**
 * ILS driver test
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2011.
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
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFindTest\ILS\Driver;
use VuFind\ILS\Driver\NoILS;

/**
 * ILS driver test
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class NoILSTest extends \VuFindTest\Unit\TestCase
{
    /**
     * Mock record loader
     *
     * @param \VuFind\Record\Loader
     */
    protected $loader;

    /**
     * Driver object
     *
     * @var NoILS
     */
    protected $driver;

    /**
     * Standard setup method.
     *
     * @return void
     */
    public function setUp()
    {
        $this->loader = $this->getMock(
            'VuFind\Record\Loader', [],
            [
                $this->getMock('VuFindSearch\Service'),
                $this->getMock('VuFind\RecordDriver\PluginManager')
            ]
        );
        $this->driver = new NoILS($this->loader);
        $this->driver->init();
    }

    /**
     * Test that driver defaults to 'ils-offline' mode when no config is provided.
     *
     * @return void
     */
    public function testDefaultOfflineMode()
    {
        $this->assertEquals('ils-offline', $this->driver->getOfflineMode());
    }

    /**
     * Test that driver defaults to visible login mode when no config is provided.
     *
     * @return void
     */
    public function testDefaultLoginVisibility()
    {
        $this->assertFalse($this->driver->loginIsHidden());
    }

    /**
     * Test that driver defaults to hidden holdings mode when no config is provided.
     *
     * @return void
     */
    public function testDefaultHoldingsVisibility()
    {
        $this->assertFalse($this->driver->hasHoldings('foo'));
    }

    /**
     * Test that driver makes holdings visible when in MARC mode.
     *
     * @return void
     */
    public function testMarcHoldingsVisibility()
    {
        $this->driver
            ->setConfig(['settings' => ['useHoldings' => 'marc']]);
        $this->assertTrue($this->driver->hasHoldings('foo'));
    }

    /**
     * Test various methods that aren't expected to actually do anything.
     *
     * @return void
     */
    public function testUnsupportedFunctions()
    {
        $this->assertEquals([], $this->driver->getPurchaseHistory('foo'));
        $this->assertEquals(null, $this->driver->patronLogin('foo', 'bar'));
        $this->assertEquals([], $this->driver->getNewItems(1, 20, 30));
        $this->assertFalse($this->driver->getConfig('Holds'));
    }
}