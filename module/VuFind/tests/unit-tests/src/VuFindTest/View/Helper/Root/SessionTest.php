<?php

/**
 * Session view helper Test Class.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\View\Helper\Root;

use Laminas\Session\Container;
use VuFind\View\Helper\Root\Session;

/**
 * Session view helper Test Class.
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
     * Test the helper.
     *
     * @return void
     */
    public function testSession()
    {
        $sessionManager = Container::getDefaultManager();

        $session = new Session(new Container('SessionTest', $sessionManager));
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
