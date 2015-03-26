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
use VuFind\ILS\Driver\Symphony;

/**
 * ILS driver test
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class SymphonyTest extends \VuFindTest\Unit\TestCase
{
    protected $driver;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->driver = new Symphony();
    }

    /**
     * Test that driver complains about invalid base URL configuration.
     *
     * @return void
     */
    public function testBadBaseUrl()
    {
        if (!version_compare(\PHP_VERSION, '5.3.4', '>=')) {
            $this->markTestSkipped('Test requires PHP >= 5.3.4 (see VUFIND-660)');
        }

        // Without SOAP functionality, we can't proceed:
        if (!class_exists('SoapClient')) {
            $this->markTestSkipped('SoapClient not installed');
        }

        $this->driver->setConfig(
            ['WebServices' => ['baseURL' => 'invalid']]
        );
        $this->driver->init();
        $pickup = @$this->driver->getPickUpLocations();
        $this->assertTrue(empty($pickup));
    }
}