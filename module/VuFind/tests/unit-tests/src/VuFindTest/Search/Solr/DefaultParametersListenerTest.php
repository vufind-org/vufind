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
     * Data provider for testSearch().
     *
     * @return \Iterator
     */
    public static function searchProvider(): \Iterator
    {
        yield 'catch all, search, wrong backend' => [true, 'search', 'secondary', null, null];
        yield 'catch all, search, matching backend' => [true, 'search', 'primary', ['1', '2'], null];
        yield 'catch all, retrieve, matching backend' => [true, 'retrieve', 'primary', null, ['3']];
        yield 'no catch all, search, wrong backend' => [false, 'search', 'secondary', null, null];
        yield 'no catch all, search, matching backend' => [false, 'search', 'primary', ['1', '2'], null];
        yield 'no catch all, retrieve, matching backend' => [false, 'retrieve', 'primary', null, null];
    }

    /**
     * Test that search behaves as expected.
     *
     * @param bool   $catchAllConfig  Whether the config should include the * search context
     * @param string $searchContext   'search', 'retrieve', etc.
     * @param string $searchBackendId 'primary' or 'secondary' as defined above
     * @param ?array $expectFoo       Expected 'foo' params
     * @param ?array $expectBar       Expected 'bar' params
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('searchProvider')]
    public function testSearch(
        bool $catchAllConfig,
        string $searchContext,
        string $searchBackendId,
        ?array $expectFoo,
        ?array $expectBar
    ): void {
        // Set up search
        $params =  new ParamBag(
            [
                'fq' => [
                    'foo:value',
                ],
            ]
        );
        $searchBackend = $this->backends[$searchBackendId];
        $command = $this->getMockSearchCommand(
            $params,
            $searchContext,
            $searchBackend->getIdentifier()
        );

        // Set up listener
        $listenerConfig = [
            'search' => 'foo=1&foo=2',
        ];
        if ($catchAllConfig) {
            $listenerConfig['*'] = 'bar=3&bar';
        }
        $listener = new DefaultParametersListener($this->backends['primary'], $listenerConfig);

        // Check that nothing fails if params element is missing:
        $event = new Event(
            'pre',
            $searchBackend,
            compact('command')
        );
        $listener->onSearchPre($event);

        // Check with params element
        $event = new Event(
            'pre',
            $searchBackend,
            compact('params', 'command')
        );
        $listener->onSearchPre($event);

        $this->assertEquals($expectFoo, $params->get('foo'));
        $this->assertEquals($expectBar, $params->get('bar'));
    }
}
