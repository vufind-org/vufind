<?php

/**
 * Unit tests for OnCampus listener.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2015.
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
 * @package  Search
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFindTest\Search\Primo;

use Laminas\EventManager\Event;
use VuFind\Search\Primo\InjectOnCampusListener;
use VuFindSearch\Backend\Primo\Backend;
use VuFindSearch\Backend\Primo\Connector;
use VuFindSearch\ParamBag;
use VuFindSearch\Service;

/**
 * Unit tests for OnCampus listener.
 *
 * @category VuFind
 * @package  Search
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class OnCampusListenerTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\MockSearchCommandTrait;

    /**
     * Backend.
     *
     * @var BackendInterface
     */
    protected $backend;

    /**
     * Construct a mock search backend pre event.
     *
     * @param ParamBag $params Search backend parameters
     *
     * @return Event
     */
    protected function getMockPreEvent(ParamBag $params): Event
    {
        $command = $this->getMockSearchCommand($params);
        return new Event(
            Service::EVENT_PRE,
            $this->backend,
            compact('params', 'command')
        );
    }

    /**
     * Setup.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $connector      = new Connector('http://example.org/', 'sample', 'none');
        $this->backend  = new Backend($connector);
    }

    /**
     * Test attaching listener.
     *
     * @return void
     */
    public function testAttach()
    {
        $listener = new InjectOnCampusListener();
        $mock = $this->createMock(\Laminas\EventManager\SharedEventManagerInterface::class);
        $mock->expects($this->once())->method('attach')->with(
            $this->equalTo(\VuFindSearch\Service::class),
            $this->equalTo('pre'),
            $this->equalTo([$listener, 'onSearchPre'])
        );
        $listener->attach($mock);
    }

    /**
     * Test attaching listener with parameter.
     *
     * @return void
     */
    public function testAttachWithParameter()
    {
        $mockPermController = $this->getMockBuilder(\VuFind\Search\Primo\PrimoPermissionHandler::class)
            ->disableOriginalConstructor()
            ->getMock();
        $listener = new InjectOnCampusListener($mockPermController);
        $mock = $this->createMock(\Laminas\EventManager\SharedEventManagerInterface::class);
        $mock->expects($this->once())->method('attach')->with(
            $this->equalTo(\VuFindSearch\Service::class),
            $this->equalTo('pre'),
            $this->equalTo([$listener, 'onSearchPre'])
        );
        $listener->attach($mock);
    }

    /**
     * Test the listener without setting an authorization service.
     * This should return false.
     *
     * @return void
     */
    public function testOnCampusWithoutAuthorizationService()
    {
        $params   = new ParamBag([ ]);
        $listener = new InjectOnCampusListener();

        $event    = $this->getMockPreEvent($params);
        $listener->onSearchPre($event);

        $onCampus   = $params->get('onCampus');
        $this->assertEquals([ 0 => false ], $onCampus);
    }

    /**
     * Test the listener if default permission rule applies
     *
     * @return void
     */
    public function testOnCampusDefaultSuccessfull()
    {
        $params   = new ParamBag([ ]);
        $mockPermController = $this
            ->getMockBuilder(\VuFind\Search\Primo\PrimoPermissionHandler::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockPermController->expects($this->any())->method('hasPermission')
            ->will($this->returnValue(true));

        $listener = new InjectOnCampusListener($mockPermController);

        $event    = $this->getMockPreEvent($params);
        $listener->onSearchPre($event);

        $onCampus   = $params->get('onCampus');
        $this->assertEquals(
            [0 => true],
            $onCampus
        );
    }

    /**
     * Test the listener if default permission rule applies and default permission
     * is not enough to get Primo results
     *
     * @return void
     */
    public function testOnCampusDefaultNotSuccessfull()
    {
        $params   = new ParamBag([ ]);
        $mockPermController
            = $this->getMockBuilder(\VuFind\Search\Primo\PrimoPermissionHandler::class)
            ->disableOriginalConstructor()
            ->getMock();

        $listener = new InjectOnCampusListener($mockPermController);

        $event    = $this->getMockPreEvent($params);
        $listener->onSearchPre($event);

        $onCampus   = $params->get('onCampus');
        $this->assertEquals([0 => false], $onCampus);
    }

    /**
     * Test the listener if certain rule applies (user is inside a configured
     * network)
     *
     * @return void
     */
    public function testOnCampusInsideNetwork()
    {
        $params   = new ParamBag([ ]);
        $mockPermController
            = $this->getMockBuilder(\VuFind\Search\Primo\PrimoPermissionHandler::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockPermController->expects($this->any())->method('hasPermission')
            ->will($this->returnValue(true));

        $listener = new InjectOnCampusListener($mockPermController);

        $event    = $this->getMockPreEvent($params);
        $listener->onSearchPre($event);

        $onCampus   = $params->get('onCampus');
        $this->assertEquals([0 => true], $onCampus);
    }

    /**
     * Test the listener if certain rule applies (user is outside of any configured
     * network)
     *
     * @return void
     */
    public function testOnCampusOutsideNetwork()
    {
        $params   = new ParamBag([ ]);
        $mockPermController = $this
            ->getMockBuilder(\VuFind\Search\Primo\PrimoPermissionHandler::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockPermController->expects($this->any())->method('hasPermission')
            ->will($this->returnValue(false));

        $listener = new InjectOnCampusListener($mockPermController);

        $event    = $this->getMockPreEvent($params);
        $listener->onSearchPre($event);

        $onCampus   = $params->get('onCampus');
        $this->assertEquals([0 => false], $onCampus);
    }

    /**
     * Test the listener if no permission controller exists
     *
     * @return void
     */
    public function testOnCampusNoPermissionController()
    {
        $params   = new ParamBag([ ]);

        $listener = new InjectOnCampusListener();

        $event    = $this->getMockPreEvent($params);
        $listener->onSearchPre($event);

        $onCampus   = $params->get('onCampus');
        $this->assertEquals([0 => false], $onCampus);
    }
}
