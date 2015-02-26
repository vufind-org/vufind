<?php

/**
 * Followup controller plugin tests.
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
namespace VuFindTest\Controller\Plugin;

use VuFind\Controller\Plugin\Followup;
use VuFindTest\Unit\TestCase as TestCase;

/**
 * Followup controller plugin tests.
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class FollowupTest extends TestCase
{
    /**
     * Test clear behavior
     *
     * @return void
     */
    public function testClear()
    {
        $f = new Followup();
        $f->setController($this->getMockController());
        $this->assertFalse($f->clear('url'));  // nothing to clear yet
        $f->store();
        $this->assertTrue($f->clear('url'));   // clear the url set by store
        $this->assertFalse($f->clear('url'));  // already cleared
    }

    /**
     * Test retrieve
     *
     * @return void
     */
    public function testRetrieve()
    {
        $f = new Followup();
        $f->setController($this->getMockController());
        $f->store();
        // standard controller-provided URL retrieval:
        $this->assertEquals('http://localhost/default-url', $f->retrieve('url'));
        // no parameters retrieves session object:
        $this->assertEquals('Zend\Session\Container', get_class($f->retrieve()));
        // test defaulting behavior:
        $this->assertEquals('foo', $f->retrieve('bar', 'foo'));
    }

    /**
     * Test retrieve and clear
     *
     * @return void
     */
    public function testRetrieveAndClear()
    {
        $f = new Followup();
        $f->store(['foo' => 'bar'], 'baz');
        $this->assertEquals('bar', $f->retrieveAndClear('foo'));
        $this->assertEquals('baz', $f->retrieveAndClear('url'));
        $this->assertNull($f->retrieveAndClear('foo'));
        $this->assertNull($f->retrieveAndClear('url'));
    }

    /**
     * Get a mock controller
     *
     * @param string $url URL for controller to report.
     *
     * @return void
     */
    protected function getMockController($url = 'http://localhost/default-url')
    {
        $controller = $this->getMock('VuFind\Controller\AbstractBase');
        $controller->expects($this->any())->method('getServerUrl')->will($this->returnValue($url));
        return $controller;
    }
}