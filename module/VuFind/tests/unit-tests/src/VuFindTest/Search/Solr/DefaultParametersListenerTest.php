<?php

/**
 * Unit tests for DefaultParametersListener.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2015.
 * Copyright (C) The National Library of Finland 2021.
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
 * @package  Search
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFindTest\Search\Solr;

use Laminas\EventManager\Event;
use PHPUnit\Framework\MockObject\MockObject;
use VuFind\Search\Solr\DefaultParametersListener;
use VuFindSearch\Backend\BackendInterface;
use VuFindSearch\ParamBag;
use VuFindSearch\Service;

/**
 * Unit tests for DefaultParametersListener.
 *
 * @category VuFind
 * @package  Search
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class DefaultParametersListenerTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\MockSearchCommandTrait;

    /**
     * Backends.
     *
     * @var BackendInterface[]|MockObject[]
     */
    protected $backends;

    /**
     * Setup.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->backends = [];
        foreach (['primary', 'secondary'] as $name) {
            $this->backends[$name] = $this->createMock(\VuFindSearch\Backend\Solr\Backend::class);
            $this->backends[$name]->method('getIdentifier')->willReturn($name);
        }
    }

    /**
     * Test attaching listener.
     *
     * @return void
     */
    public function testAttach()
    {
        $backend = $this->createMock(\VuFindSearch\Backend\Solr\Backend::class);
        $listener = new DefaultParametersListener($backend, ['foo' => 'bar']);
        $mock = $this->createMock(\Laminas\EventManager\SharedEventManagerInterface::class);
        $mock->expects($this->once())->method('attach')->with(
            Service::class,
            Service::EVENT_PRE,
            [$listener, 'onSearchPre']
        );
        $listener->attach($mock);
    }

    /**
     * Test the listener with a * catch-all.
     *
     * @return void
     */
    public function testDefaultParametersWithCatchAll()
    {
        $params = new ParamBag(
            [
                'fq' => [
                    'foo:value',
                ],
            ]
        );

        $command = $this->getMockSearchCommand(
            $params,
            'search',
            $this->backends['secondary']->getIdentifier()
        );
        $listener = new DefaultParametersListener(
            $this->backends['primary'],
            [
                'search' => 'foo=1&foo=2',
                '*' => 'bar=3&bar',
            ]
        );

        // Check that nothing fails if params element is missing:
        $event = new Event(
            'pre',
            $this->backends['secondary'],
            compact('command')
        );
        $listener->onSearchPre($event);

        $event = new Event(
            'pre',
            $this->backends['secondary'],
            compact('params', 'command')
        );
        $listener->onSearchPre($event);

        $this->assertEquals(null, $params->get('foo'));
        $this->assertEquals(null, $params->get('bar'));

        $command = $this->getMockSearchCommand(
            $params,
            'search',
            $this->backends['primary']->getIdentifier()
        );
        $event = new Event(
            'pre',
            $this->backends['primary'],
            compact('params', 'command')
        );
        $listener->onSearchPre($event);

        $this->assertEquals(['1', '2'], $params->get('foo'));
        $this->assertEquals(null, $params->get('bar'));

        $command = $this->getMockSearchCommand(
            $params,
            'retrieve',
            $this->backends['primary']->getIdentifier()
        );
        $event = new Event(
            'pre',
            $this->backends['primary'],
            compact('params', 'command')
        );
        $listener->onSearchPre($event);

        $this->assertEquals(['3'], $params->get('bar'));
    }

    /**
     * Test the listener without a * catch-all.
     *
     * @return void
     */
    public function testDefaultParametersWithoutCatchAll()
    {
        $params = new ParamBag(
            [
                'fq' => [
                    'foo:value',
                ],
            ]
        );

        $command = $this->getMockSearchCommand(
            $params,
            'search',
            $this->backends['primary']->getIdentifier()
        );
        $listener = new DefaultParametersListener(
            $this->backends['primary'],
            [
                'search' => 'foo=1&foo=2',
            ]
        );

        $event = new Event(
            'pre',
            $this->backends['primary'],
            compact('params', 'command')
        );
        $listener->onSearchPre($event);

        $this->assertEquals(['1', '2'], $params->get('foo'));
        $this->assertEquals(null, $params->get('bar'));

        $command = $this->getMockSearchCommand(
            $params,
            'retrieve',
            $this->backends['primary']->getIdentifier()
        );
        $event = new Event(
            'pre',
            $this->backends['primary'],
            compact('params', 'command')
        );
        $listener->onSearchPre($event);

        $this->assertEquals(null, $params->get('bar'));
    }
}
