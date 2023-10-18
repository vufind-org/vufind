<?php

/**
 * BackendManager unit tests.
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

namespace VuFindTest\Search;

use Laminas\EventManager\SharedEventManager;
use VuFind\Search\BackendManager;

/**
 * BackendManager unit tests.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class BackendManagerTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ReflectionTrait;

    /**
     * Test that get() throws on a non-object.
     *
     * @return void
     */
    public function testGetThrowsOnNonObject()
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Expected backend registry to return object');

        $registry = $this->getMockForAbstractClass(\Laminas\ServiceManager\ServiceLocatorInterface::class);
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
     */
    public function testGetThrowsOnNonBackend()
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('does not implement the expected interface');

        $registry = $this->getMockForAbstractClass(\Laminas\ServiceManager\ServiceLocatorInterface::class);
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
        $registry = $this->getMockForAbstractClass(\Laminas\ServiceManager\ServiceLocatorInterface::class);
        $events   = new SharedEventManager();
        $manager  = new BackendManager($registry);
        $manager->attachShared($events);

        $listeners = $this->getProperty($manager, 'listeners');
        $this->assertTrue($listeners->offsetExists($events));

        $manager->detachShared($events);
        $this->assertFalse($listeners->offsetExists($events));
    }
}
