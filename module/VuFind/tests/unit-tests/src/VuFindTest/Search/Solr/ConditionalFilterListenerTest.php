<?php

/**
 * Unit tests for Conditional Filter listener.
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
namespace VuFindTest\Search\Solr;

use VuFindSearch\ParamBag;
use VuFindSearch\Backend\Solr\Backend;
use VuFindSearch\Backend\Solr\Connector;
use VuFindSearch\Backend\Solr\HandlerMap;

use VuFind\Search\Solr\InjectConditionalFilterListener;
use VuFindTest\Unit\TestCase;
use Zend\EventManager\Event;

use ZfcRbac\Service\AuthorizationServiceAwareInterface,
    ZfcRbac\Service\AuthorizationServiceAwareTrait;

/**
 * Unit tests for Conditional Filter listener.
 *
 * @category VuFind2
 * @package  Search
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class ConditionalFilterListenerTest extends TestCase
{
    /**
     * Sample configuration for ConditionalFilters.
     *
     * @var array
     */
    protected static $searchConfig = [
        '0' => '-conditionalFilter.sample|(NOT institution:"MyInst")',
        '1' => 'conditionalFilter.sample|institution:"MyInst"'
    ];

    /**
     * Sample configuration for empty ConditionalFilters config.
     *
     * @var array
     */
    protected static $emptySearchConfig = [ ];

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
        $handlermap     = new HandlerMap(['select' => ['fallback' => true]]);
        $connector      = new Connector('http://example.org/', $handlermap);
        $this->backend  = new Backend($connector);
    }

    /**
     * Test attaching listener.
     *
     * @return void
     */
    public function testAttach()
    {
        $listener = new InjectConditionalFilterListener(self::$emptySearchConfig);
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
     * This should return an empty array.
     *
     * @return void
     */
    public function testConditionalFilterWithoutAuthorizationService()
    {
        $params   = new ParamBag([ ]);
        $listener = new InjectConditionalFilterListener(self::$searchConfig);

        $event    = new Event('pre', $this->backend, [ 'params' => $params]);
        $listener->onSearchPre($event);

        $fq   = $params->get('fq');
        $this->assertEquals([ ], $fq);
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
        $params   = new ParamBag(
            [
                'fq' => ['fulltext:Vufind', 'field2:novalue'],
            ]
        );
        $listener = new InjectConditionalFilterListener(self::$searchConfig);

        $event    = new Event('pre', $this->backend, [ 'params' => $params]);
        $listener->onSearchPre($event);

        $fq   = $params->get('fq');
        $this->assertEquals(
            [0 => 'fulltext:Vufind',
            1 => 'field2:novalue'], $fq
        );
    }

    /**
     * Test the listener with an empty conditional filter config.
     *
     * @return void
     */
    public function testConditionalFilterEmptyConfig()
    {
        $params   = new ParamBag([ ]);
        $listener = new InjectConditionalFilterListener(self::$emptySearchConfig);
        $mockAuth = $this->getMockBuilder('ZfcRbac\Service\AuthorizationService')
            ->disableOriginalConstructor()
            ->getMock();
        $listener->setAuthorizationService($mockAuth);

        $event    = new Event('pre', $this->backend, [ 'params' => $params]);
        $listener->onSearchPre($event);

        $fq   = $params->get('fq');
        $this->assertEquals([ ], $fq);
    }

    /**
     * Test the listener with an empty conditional filter config,
     * but with given fq parameters
     *
     * @return void
     */
    public function testConditionalFilterEmptyConfigWithFQ()
    {
        $params   = new ParamBag(
            [
                'fq' => ['fulltext:Vufind', 'field2:novalue'],
            ]
        );
        $listener = new InjectConditionalFilterListener(self::$emptySearchConfig);
        $mockAuth = $this->getMockBuilder('ZfcRbac\Service\AuthorizationService')
            ->disableOriginalConstructor()
            ->getMock();
        $listener->setAuthorizationService($mockAuth);

        $event    = new Event('pre', $this->backend, [ 'params' => $params]);
        $listener->onSearchPre($event);

        $fq   = $params->get('fq');
        $this->assertEquals(
            [0 => 'fulltext:Vufind',
            1 => 'field2:novalue'], $fq
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
        $params   = new ParamBag([ ]);
        $listener = new InjectConditionalFilterListener(self::$searchConfig);
        $mockAuth = $this->getMockBuilder('ZfcRbac\Service\AuthorizationService')
            ->disableOriginalConstructor()
            ->getMock();
        $mockAuth->expects($this->any())->method('isGranted')
            ->with($this->equalTo('conditionalFilter.sample'))
            ->will($this->returnValue(true));
        $listener->setAuthorizationService($mockAuth);

        $event    = new Event('pre', $this->backend, [ 'params' => $params]);
        $listener->onSearchPre($event);

        $fq   = $params->get('fq');
        $this->assertEquals(
            [0 => 'institution:"MyInst"'], $fq
        );
    }

    /**
     * Test the listener without preset fq parameters
     * if the conditional filter is not granted
     *
     * @return void
     */
    public function testNegativeConditionalFilter()
    {
        $params   = new ParamBag([ ]);

        $listener = new InjectConditionalFilterListener(self::$searchConfig);
        $mockAuth = $this->getMockBuilder('ZfcRbac\Service\AuthorizationService')
            ->disableOriginalConstructor()
            ->getMock();
        $mockAuth->expects($this->any())->method('isGranted')
            ->with($this->equalTo('conditionalFilter.sample'))
            ->will($this->returnValue(false));
        $listener->setAuthorizationService($mockAuth);
        $event    = new Event('pre', $this->backend, [ 'params' => $params ]);
        $listener->onSearchPre($event);

        $fq   = $params->get('fq');
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
        $params   = new ParamBag(
            [
                'fq' => ['fulltext:Vufind', 'field2:novalue'],
            ]
        );

        $listener = new InjectConditionalFilterListener(self::$searchConfig);
        $mockAuth = $this->getMockBuilder('ZfcRbac\Service\AuthorizationService')
            ->disableOriginalConstructor()
            ->getMock();
        $mockAuth->expects($this->any())->method('isGranted')
            ->with($this->equalTo('conditionalFilter.sample'))
            ->will($this->returnValue(false));
        $listener->setAuthorizationService($mockAuth);
        $event    = new Event('pre', $this->backend, ['params' => $params]);
        $listener->onSearchPre($event);

        $fq   = $params->get('fq');
        $this->assertEquals(
            [0 => 'fulltext:Vufind',
            1 => 'field2:novalue',
            2 => '(NOT institution:"MyInst")'
            ], $fq
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
        $params   = new ParamBag(
            [
                'fq' => ['fulltext:Vufind', 'field2:novalue'],
            ]
        );

        $listener = new InjectConditionalFilterListener(self::$searchConfig);
        $mockAuth = $this->getMockBuilder('ZfcRbac\Service\AuthorizationService')
            ->disableOriginalConstructor()
            ->getMock();
        $mockAuth->expects($this->any())->method('isGranted')
            ->with($this->equalTo('conditionalFilter.sample'))
            ->will($this->returnValue(true));
        $listener->setAuthorizationService($mockAuth);
        $event    = new Event('pre', $this->backend, ['params' => $params]);
        $listener->onSearchPre($event);

        $fq   = $params->get('fq');
        $this->assertEquals(
            [0 => 'fulltext:Vufind',
            1 => 'field2:novalue',
            2 => 'institution:"MyInst"'
            ], $fq
        );
    }
}
