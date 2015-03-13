<?php

/**
 * BackendManager unit tests.
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
namespace VuFindTest\Search;

use VuFind\Search\BackendManager;
use VuFindTest\Unit\TestCase as TestCase;

use Zend\EventManager\SharedEventManager;

/**
 * BackendManager unit tests.
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class BackendManagerTest extends TestCase
{
    /**
     * Test that get() throws on a non-object.
     *
     * @return void
     *
     * @expectedException        UnexpectedValueException
     * @expectedExceptionMessage Expected backend registry to return object
     */
    public function testGetThrowsOnNonObject()
    {
        $registry = $this->getMockForAbstractClass('Zend\ServiceManager\ServiceLocatorInterface');
        $registry->expects($this->once())
            ->method('get')
            ->will($this->returnValue('not-an-object'));
        $manager = new BackendManager($registry);
        $manager->get('not-an-object');
    }

    /**
     * Test that get() throws on a non-backend.
     *
     * @return void
     *
     * @expectedException        UnexpectedValueException
     * @expectedExceptionMessage does not implement the expected interface
     */
    public function testGetThrowsOnNonBackend()
    {
        $registry = $this->getMockForAbstractClass('Zend\ServiceManager\ServiceLocatorInterface');
        $registry->expects($this->once())
            ->method('get')
            ->will($this->returnValue($this));
        $manager = new BackendManager($registry);
        $manager->get('not-a-backend');
    }

    /**
     * Test attaching to and detaching from shared event manager.
     *
     * @return void
     */
    public function testAttachDetachShared()
    {
        $registry = $this->getMockForAbstractClass('Zend\ServiceManager\ServiceLocatorInterface');
        $events   = new SharedEventManager();
        $manager  = new BackendManager($registry);
        $manager->attachShared($events);

        $listeners = $this->getProperty($manager, 'listeners');
        $this->assertTrue($listeners->offsetExists($events));

        $manager->detachShared($events);
        $this->assertFalse($listeners->offsetExists($events));
    }
}