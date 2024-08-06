<?php

/**
 * Unit tests for Conditional Filter listener.
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

namespace VuFindTest\Search\Solr;

use Laminas\Config\Config;
use Laminas\EventManager\Event;
use VuFind\Search\Solr\InjectConditionalFilterListener;
use VuFindSearch\Backend\BackendInterface;
use VuFindSearch\Backend\Solr\Backend;
use VuFindSearch\Backend\Solr\Connector;
use VuFindSearch\Backend\Solr\HandlerMap;
use VuFindSearch\ParamBag;
use VuFindSearch\Service;

/**
 * Unit tests for Conditional Filter listener.
 *
 * @category VuFind
 * @package  Search
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ConditionalFilterListenerTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\MockSearchCommandTrait;

    /**
     * Sample configuration for ConditionalFilters.
     *
     * @var array
     */
    protected static $searchConfig = [
        '0' => '-conditionalFilter.sample|(NOT institution:"MyInst")',
        '1' => 'conditionalFilter.sample|institution:"MyInst"',
    ];

    /**
     * Sample configuration for empty ConditionalFilters config.
     *
     * @var array
     */
    protected static $emptySearchConfig = [];

    /**
     * Backend.
     *
     * @var BackendInterface
     */
    protected $backend;

    /**
     * Construct a mock search backend pre event.
     *
     * @param ParamBag $params    Search backend parameters
     * @param string   $backendId Backend identifier
     *
     * @return Event
     */
    protected function getMockPreEvent(ParamBag $params, string $backendId = 'Solr'): Event
    {
        $command = $this->getMockSearchCommand($params, null, $backendId);
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
        $handlermap = new HandlerMap(['select' => ['fallback' => true]]);
        $connector = new Connector(
            'http://localhost/',
            $handlermap,
            function () {
                return new \Laminas\Http\Client();
            }
        );
        $this->backend = new Backend($connector);
        $this->backend->setIdentifier('Solr');
    }

    /**
     * Test attaching listener.
     *
     * @return void
     */
    public function testAttach()
    {
        $listener = new InjectConditionalFilterListener($this->backend, self::$emptySearchConfig);
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
     * This should return an empty array.
     *
     * @return void
     */
    public function testConditionalFilterWithoutAuthorizationService()
    {
        $params = new ParamBag([]);
        $listener = new InjectConditionalFilterListener($this->backend, self::$searchConfig);

        $event = $this->getMockPreEvent($params);
        $listener->onSearchPre($event);

        $fq = $params->get('fq');
        $this->assertEquals([], $fq);
    }

    /**
     * Test the listener without setting an authorization service,
     * but with fq-parameters.
     * This should not touch the parameters.
     *
     * @return void
     */
    public function testConditionalFilterWithoutAuthorizationServiceWithParams()
    {
        $params = new ParamBag(
            [
                'fq' => ['fulltext:VuFind', 'field2:novalue'],
            ]
        );
        $listener = new InjectConditionalFilterListener($this->backend, self::$searchConfig);

        $event = $this->getMockPreEvent($params);
        $listener->onSearchPre($event);

        $fq = $params->get('fq');
        $this->assertEquals(
            [0 => 'fulltext:VuFind',
            1 => 'field2:novalue'],
            $fq
        );
    }

    /**
     * Test the listener with an empty conditional filter config.
     *
     * @return void
     */
    public function testConditionalFilterEmptyConfig()
    {
        $params = new ParamBag([]);
        $listener = new InjectConditionalFilterListener($this->backend, self::$emptySearchConfig);
        $mockAuth = $this->getMockBuilder(\LmcRbacMvc\Service\AuthorizationService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $listener->setAuthorizationService($mockAuth);

        $event = $this->getMockPreEvent($params);
        $listener->onSearchPre($event);

        $fq = $params->get('fq');
        $this->assertEquals([], $fq);
    }

    /**
     * Test the listener with an empty conditional filter config,
     * but with given fq parameters
     *
     * @return void
     */
    public function testConditionalFilterEmptyConfigWithFQ()
    {
        $params = new ParamBag(
            [
                'fq' => ['fulltext:VuFind', 'field2:novalue'],
            ]
        );
        $listener = new InjectConditionalFilterListener($this->backend, self::$emptySearchConfig);
        $mockAuth = $this->getMockBuilder(\LmcRbacMvc\Service\AuthorizationService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $listener->setAuthorizationService($mockAuth);

        $event = $this->getMockPreEvent($params);
        $listener->onSearchPre($event);

        $fq = $params->get('fq');
        $this->assertEquals(
            [0 => 'fulltext:VuFind',
            1 => 'field2:novalue'],
            $fq
        );
    }

    /**
     * Test the listener without preset fq parameters
     * if the conditional filter is granted
     *
     * @return void
     */
    public function testConditionalFilter()
    {
        $params = new ParamBag([]);
        $listener = new InjectConditionalFilterListener($this->backend, self::$searchConfig);
        $mockAuth = $this->getMockBuilder(\LmcRbacMvc\Service\AuthorizationService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockAuth->expects($this->any())->method('isGranted')
            ->with($this->equalTo('conditionalFilter.sample'))
            ->will($this->returnValue(true));
        $listener->setAuthorizationService($mockAuth);

        $event = $this->getMockPreEvent($params);
        $listener->onSearchPre($event);

        $fq = $params->get('fq');
        $this->assertEquals(
            [0 => 'institution:"MyInst"'],
            $fq
        );

        // Check that a filter is not added for wrong backend:
        $params = new ParamBag([]);
        $event = $this->getMockPreEvent($params, 'Other');
        $listener->onSearchPre($event);
        $this->assertEmpty($params->get('fq'));
    }

    /**
     * Test the listener without preset fq parameters
     * if the conditional filter is not granted
     *
     * @return void
     */
    public function testNegativeConditionalFilter()
    {
        $params = new ParamBag([]);

        $listener = new InjectConditionalFilterListener($this->backend, self::$searchConfig);
        $mockAuth = $this->getMockBuilder(\LmcRbacMvc\Service\AuthorizationService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockAuth->expects($this->any())->method('isGranted')
            ->with($this->equalTo('conditionalFilter.sample'))
            ->will($this->returnValue(false));
        $listener->setAuthorizationService($mockAuth);
        $event = $this->getMockPreEvent($params);
        $listener->onSearchPre($event);

        $fq = $params->get('fq');
        $this->assertEquals([0 => '(NOT institution:"MyInst")'], $fq);
    }

    /**
     * Test the listener with preset fq-parameters
     * if the conditional filter is not granted
     *
     * @return void
     */
    public function testNegativeConditionalFilterWithFQ()
    {
        $params = new ParamBag(
            [
                'fq' => ['fulltext:VuFind', 'field2:novalue'],
            ]
        );

        $listener = new InjectConditionalFilterListener($this->backend, self::$searchConfig);
        $mockAuth = $this->getMockBuilder(\LmcRbacMvc\Service\AuthorizationService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockAuth->expects($this->any())->method('isGranted')
            ->with($this->equalTo('conditionalFilter.sample'))
            ->will($this->returnValue(false));
        $listener->setAuthorizationService($mockAuth);
        $event = $this->getMockPreEvent($params);
        $listener->onSearchPre($event);

        $fq = $params->get('fq');
        $this->assertEquals(
            [0 => 'fulltext:VuFind',
            1 => 'field2:novalue',
            2 => '(NOT institution:"MyInst")',
            ],
            $fq
        );
    }

    /**
     * Test the listener with preset fq-parameters
     * if the conditional filter is granted
     *
     * @return void
     */
    public function testConditionalFilterWithFQ()
    {
        $params = new ParamBag(
            [
                'fq' => ['fulltext:VuFind', 'field2:novalue'],
            ]
        );

        $listener = new InjectConditionalFilterListener($this->backend, self::$searchConfig);
        $mockAuth = $this->getMockBuilder(\LmcRbacMvc\Service\AuthorizationService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockAuth->expects($this->any())->method('isGranted')
            ->with($this->equalTo('conditionalFilter.sample'))
            ->will($this->returnValue(true));
        $listener->setAuthorizationService($mockAuth);
        $event = $this->getMockPreEvent($params);
        $listener->onSearchPre($event);

        $fq = $params->get('fq');
        $this->assertEquals(
            [0 => 'fulltext:VuFind',
            1 => 'field2:novalue',
            2 => 'institution:"MyInst"',
            ],
            $fq
        );
    }
}
