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
use VuFind\ILS\Driver\Demo;

/**
 * ILS driver test
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class DemoTest extends \VuFindTest\Unit\TestCase
{
    /**
     * Driver object
     *
     * @var Demo
     */
    protected $driver;

    /**
     * Standard setup method.
     *
     * @return void
     */
    public function setUp()
    {
        $this->driver = new Demo(
            new \VuFind\Date\Converter(), $this->getMock('VuFindSearch\Service')
        );
        $this->driver->init();
    }

    /**
     * Test that patron login method always returns a fake user.
     *
     * @return void
     */
    public function testPatronLogin()
    {
        $patron = $this->driver->patronLogin('foo', 'bar');
        $this->assertTrue(isset($patron['id']));
    }
}