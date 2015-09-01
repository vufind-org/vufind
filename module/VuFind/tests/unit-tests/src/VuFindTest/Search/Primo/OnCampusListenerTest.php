<?php

/**
 * Unit tests for OnCampus listener.
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Search
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFindTest\Search\Primo;

use VuFindSearch\ParamBag;
use VuFindSearch\Backend\Primo\Backend;
use VuFindSearch\Backend\Primo\Connector;

use VuFind\Search\Primo\InjectOnCampusListener;
use VuFindTest\Unit\TestCase;
use Zend\EventManager\Event;

use ZfcRbac\Service\AuthorizationServiceAwareInterface,
    ZfcRbac\Service\AuthorizationServiceAwareTrait;

/**
 * Unit tests for OnCampus listener.
 *
 * @category VuFind2
 * @package  Search
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class OnCampusListenerTest extends TestCase
{
    /**
     * Backend.
     *
     * @var BackendInterface
     */
    protected $backend;

    /**
     * Setup.
     *
     * @return void
     */
    protected function setup()
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
        $mock = $this->getMock('Zend\EventManager\SharedEventManagerInterface');
        $mock->expects($this->once())->method('attach')->with(
            $this->equalTo('VuFind\Search'),
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
        $listener = new InjectOnCampusListener('myUniversity.IPRANGE');
        $mock = $this->getMock('Zend\EventManager\SharedEventManagerInterface');
        $mock->expects($this->once())->method('attach')->with(
            $this->equalTo('VuFind\Search'),
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

        $event    = new Event('pre', $this->backend, [ 'params' => $params]);
        $listener->onSearchPre($event);

        $onCampus   = $params->get('onCampus');
        $this->assertEquals([ 0 => false ], $onCampus);
    }

    /**
     * Test the listener if onCampus permission matches
     *
     * @return void
     */
    public function testOnCampusAuthSuccessfull()
    {
        $params   = new ParamBag([ ]);
        $listener = new InjectOnCampusListener('myUniversity.IPRANGE');
        $mockAuth = $this->getMockBuilder('ZfcRbac\Service\AuthorizationService')
            ->disableOriginalConstructor()
            ->getMock();
        $mockAuth->expects($this->any())->method('isGranted')
            ->with($this->equalTo('myUniversity.IPRANGE'))
            ->will($this->returnValue(true));
        $listener->setAuthorizationService($mockAuth);

        $event    = new Event('pre', $this->backend, [ 'params' => $params]);
        $listener->onSearchPre($event);

        $onCampus   = $params->get('onCampus');
        $this->assertEquals(
            [0 => true], $onCampus
        );
    }

    /**
     * Test the listener if onCampus permission does not match
     *
     * @return void
     */
    public function testOnCampusAuthNotSuccessfull()
    {
        $params   = new ParamBag([ ]);

        $listener = new InjectOnCampusListener('myUniversity.IPRANGE');
        $mockAuth = $this->getMockBuilder('ZfcRbac\Service\AuthorizationService')
            ->disableOriginalConstructor()
            ->getMock();
        $mockAuth->expects($this->any())->method('isGranted')
            ->with($this->equalTo('myUniversity.IPRANGE'))
            ->will($this->returnValue(false));
        $listener->setAuthorizationService($mockAuth);
        $event    = new Event('pre', $this->backend, [ 'params' => $params ]);
        $listener->onSearchPre($event);

        $onCampus   = $params->get('onCampus');
        $this->assertEquals([0 => false], $onCampus);
    }

    /**
     * Test the listener if onCampus permission does not exist
     *
     * @return void
     */
    public function testOnCampusAuthNotExisting()
    {
        $params   = new ParamBag([ ]);

        $listener = new InjectOnCampusListener('myUniversity.IPRANGE');
        $mockAuth = $this->getMockBuilder('ZfcRbac\Service\AuthorizationService')
            ->disableOriginalConstructor()
            ->getMock();
        $mockAuth->expects($this->any())->method('isGranted')
            ->will($this->returnValue(false));
        $listener->setAuthorizationService($mockAuth);
        $event    = new Event('pre', $this->backend, [ 'params' => $params ]);
        $listener->onSearchPre($event);

        $onCampus   = $params->get('onCampus');
        $this->assertEquals([0 => false], $onCampus);
    }
}
