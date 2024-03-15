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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
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
     * Test attaching listener.
     *
     * @return void
     */
    public function testAttach()
    {
        $backend = $this->getMockBuilder(\VuFindSearch\Backend\Solr\Backend::class)
            ->disableOriginalConstructor()->getMock();
        $listener = new DefaultParametersListener($backend, ['foo' => 'bar']);
        $mock = $this->createMock(\Laminas\EventManager\SharedEventManagerInterface::class);
        $mock->expects($this->once())->method('attach')->with(
            $this->equalTo(\VuFindSearch\Service::class),
            $this->equalTo('pre'),
            $this->equalTo([$listener, 'onSearchPre'])
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

        $backend = $this->getMockBuilder(\VuFindSearch\Backend\Solr\Backend::class)
            ->disableOriginalConstructor()->getMock();
        $listener = new DefaultParametersListener(
            $backend,
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
            ['params' => $params, 'context' => 'search']
        );
        $listener->onSearchPre($event);

        $this->assertEquals(null, $params->get('foo'));
        $this->assertEquals(null, $params->get('bar'));

        $event = new Event(
            'pre',
            $backend,
            ['params' => $params, 'context' => 'search']
        );
        $listener->onSearchPre($event);

        $this->assertEquals(['1', '2'], $params->get('foo'));
        $this->assertEquals(null, $params->get('bar'));

        $event = new Event(
            'pre',
            $backend,
            ['params' => $params, 'context' => 'retrieve']
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

        $backend = $this->getMockBuilder(\VuFindSearch\Backend\Solr\Backend::class)
            ->disableOriginalConstructor()->getMock();
        $listener = new DefaultParametersListener(
            $backend,
            [
                'search' => 'foo=1&foo=2',
            ]
        );

        $event = new Event(
            'pre',
            $backend,
            ['params' => $params, 'context' => 'search']
        );
        $listener->onSearchPre($event);

        $this->assertEquals(['1', '2'], $params->get('foo'));
        $this->assertEquals(null, $params->get('bar'));

        $event = new Event(
            'pre',
            $backend,
            ['params' => $params, 'context' => 'retrieve']
        );
        $listener->onSearchPre($event);

        $this->assertEquals(null, $params->get('bar'));
    }
}
