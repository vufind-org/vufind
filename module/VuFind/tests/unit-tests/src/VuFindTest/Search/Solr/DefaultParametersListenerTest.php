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
use VuFind\Search\Solr\DefaultParametersListener;
use VuFindSearch\Backend\Solr\Backend;
use VuFindSearch\ParamBag;

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
    /**
     * Backend stub
     *
     * @var Backend
     */
    protected Backend $backend;

    /**
     * Params
     *
     * @var ParamBag
     */
    protected ParamBag $params;

    /**
     * Standard setup method.
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->backend = $this->createMock(\VuFindSearch\Backend\Solr\Backend::class);
        $this->params = new ParamBag(
            [
                'fq' => [
                    'foo:value',
                ],
            ]
        );
    }

    /**
     * Test attaching listener.
     *
     * @return void
     */
    public function testAttach()
    {
        $listener = new DefaultParametersListener($this->backend, ['foo' => 'bar']);
        $mock = $this->createMock(\Laminas\EventManager\SharedEventManagerInterface::class);
        $mock->expects($this->once())->method('attach')->with(
            \VuFindSearch\Service::class,
            'pre',
            [$listener, 'onSearchPre']
        );
        $listener->attach($mock);
    }

    /**
     * Test the listener with a * catch-all, no backend.
     *
     * @return void
     */
    public function testDefaultParametersWithCatchAllNoBackend()
    {
        $listener = new DefaultParametersListener(
            $this->backend,
            [
                'search' => 'foo=1&foo=2',
                '*' => 'bar=3&bar',
            ]
        );

        // Check that nothing fails if params element is missing:
        $event = new Event(
            'pre',
            null,
            ['context' => 'search']
        );
        $listener->onSearchPre($event);

        $event = new Event(
            'pre',
            null,
            ['params' => $this->params, 'context' => 'search']
        );
        $listener->onSearchPre($event);

        $this->assertEquals(null, $this->params->get('foo'));
        $this->assertEquals(null, $this->params->get('bar'));
    }

    /**
     * Test the listener with a * catch-all, for a particular backend.
     *
     * @return void
     */
    public function testDefaultParametersWithCatchAll()
    {
        $listener = new DefaultParametersListener(
            $this->backend,
            [
                'search' => 'foo=1&foo=2',
                '*' => 'bar=3&bar',
            ]
        );

        $event = new Event(
            'pre',
            $this->backend,
            ['params' => $this->params, 'context' => 'search']
        );
        $listener->onSearchPre($event);

        $this->assertEquals(['1', '2'], $this->params->get('foo'));
        $this->assertEquals(null, $this->params->get('bar'));

        $event = new Event(
            'pre',
            $this->backend,
            ['params' => $this->params, 'context' => 'retrieve']
        );
        $listener->onSearchPre($event);

        $this->assertEquals(['3'], $this->params->get('bar'));
    }

    /**
     * Test the listener without a * catch-all.
     *
     * @return void
     */
    public function testDefaultParametersWithoutCatchAll()
    {
        $backend = $this->createMock(\VuFindSearch\Backend\Solr\Backend::class);
        $listener = new DefaultParametersListener(
            $backend,
            [
                'search' => 'foo=1&foo=2',
            ]
        );

        $event = new Event(
            'pre',
            $backend,
            ['params' => $this->params, 'context' => 'search']
        );
        $listener->onSearchPre($event);

        $this->assertEquals(['1', '2'], $this->params->get('foo'));
        $this->assertEquals(null, $this->params->get('bar'));

        $event = new Event(
            'pre',
            $backend,
            ['params' => $this->params, 'context' => 'retrieve']
        );
        $listener->onSearchPre($event);

        $this->assertEquals(null, $this->params->get('bar'));
    }
}
