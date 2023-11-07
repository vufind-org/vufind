<?php

/**
 * Session view helper Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023.
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

namespace VuFindTest\View\Helper\Root;

use VuFind\View\Helper\Root\Session;
use VuFind\View\Helper\Root\SessionFactory;

/**
 * Session view helper Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SessionTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test the helper
     *
     * @return void
     */
    public function testSession()
    {
        // Set up a real session manager so that we can test the factory behavior:
        $container = new \VuFindTest\Container\MockContainer($this);
        $container->set(\Laminas\Session\SessionManager::class, \Laminas\Session\Container::getDefaultManager());
        $factory = new SessionFactory();

        // Now build the test subject
        $session = $factory($container, Session::class);
        // Values default to null
        $this->assertNull($session->get('foo'));
        // Put returns last assigned value
        $this->assertNull($session->put('foo', 'bar'));
        $this->assertEquals('bar', $session->put('foo', 'baz'));
        // Getter works after setter
        $this->assertEquals('baz', $session->get('foo'));
        // Invoke works
        $this->assertEquals($session, $session());
    }
}
