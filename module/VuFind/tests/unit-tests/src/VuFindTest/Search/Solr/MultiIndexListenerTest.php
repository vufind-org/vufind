<?php

/**
 * Unit tests for multiindex listener.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2013.
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
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFindTest\Search\Solr;

use Laminas\EventManager\Event;
use VuFind\Search\Solr\MultiIndexListener;
use VuFindSearch\Backend\Solr\Backend;
use VuFindSearch\Backend\Solr\Connector;
use VuFindSearch\Backend\Solr\HandlerMap;
use VuFindSearch\ParamBag;
use VuFindSearch\Service;

/**
 * Unit tests for multiindex listener.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class MultiIndexListenerTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\MockSearchCommandTrait;
    use \VuFindTest\Feature\ReflectionTrait;

    /**
     * Specs used for stripping tests.
     *
     * @var array
     */
    protected static $specs = [
        'test' => [
            'QueryFields' => [
                'A' => [
                    ['onephrase', 500],
                    ['and', 200],
                ],
                'B' => [
                    ['and', 100],
                    ['or', 50],
                ],
                0 => [
                    0 => ['AND', 50],
                    'C' => [
                        ['onephrase', 200],
                    ],
                    'D' => [
                        ['onephrase', 300],
                    ],
                    '-E' => [
                        ['or', '~'],
                    ],
                ],
            ],
            'FilterQuery' => 'format:Book',
        ],
    ];

    /**
     * Available shards used for stripping tests.
     *
     * @var array
     */
    protected static $shards = [
        'a' => 'example.org/a',
        'b' => 'example.org/b',
        'c' => 'example.org/c',
    ];

    /**
     * Shard fields used for stripping tests.
     *
     * @var array
     */
    protected static $fields = [
        'a' => ['field_1', 'field_3'],
        'b' => ['field_3'],
    ];

    /**
     * Backend.
     *
     * @var BackendInterface
     */
    protected $backend;

    /**
     * Prepare listener.
     *
     * @var MultiIndexListener
     */
    protected $listener;

    /**
     * Setup.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $handlermap     = new HandlerMap(['select' => ['fallback' => true]]);
        $connector      = new Connector(
            'http://localhost/',
            $handlermap,
            function () {
                return new \Laminas\Http\Client();
            }
        );
        $this->backend  = new Backend($connector);
        $this->backend->setIdentifier('foo');
        $this->listener = new MultiIndexListener($this->backend, self::$shards, self::$fields, self::$specs);
    }

    /**
     * Strip fields from a field facet.
     *
     * @return void
     */
    public function testStripFacetFields()
    {
        $params = new ParamBag(
            [
                'facet.field' => ['field_1', 'field_2', 'field_3'],
                'shards' => [self::$shards['b'], self::$shards['c']],
            ]
        );
        $command = $this->getMockSearchCommand($params);
        $event = new Event(
            Service::EVENT_PRE,
            $this->backend,
            compact('params', 'command')
        );
        $this->listener->onSearchPre($event);

        $facets   = $params->get('facet.field');
        sort($facets);
        $this->assertEquals(['field_1', 'field_2'], $facets);
    }

    /**
     * Test that loading a record overrides the shard settings.
     *
     * @return void
     */
    public function testAllShardsUsedForRecordRetrieval()
    {
        $params   = new ParamBag(
            [
                'shards' => [self::$shards['b'], self::$shards['c']],
            ]
        );
        $command  = $this->getMockSearchCommand($params, 'retrieve');
        $event    = new Event(
            Service::EVENT_PRE,
            $this->backend,
            ['params' => $params, 'context' => 'retrieve', 'command' => $command]
        );
        $this->listener->onSearchPre($event);

        $shards = $params->get('shards');
        $this->assertEquals(
            [implode(',', [self::$shards['a'], self::$shards['b'], self::$shards['c']])],
            $shards
        );
    }

    /**
     * Test attaching listener.
     *
     * @return void
     */
    public function testAttach()
    {
        $mock = $this->createMock(\Laminas\EventManager\SharedEventManagerInterface::class);
        $mock->expects($this->once())->method('attach')->with(
            $this->equalTo(\VuFindSearch\Service::class),
            $this->equalTo('pre'),
            $this->equalTo([$this->listener, 'onSearchPre'])
        );
        $this->listener->attach($mock);
    }

    /**
     * Apply strip to empty specs.
     *
     * @return void
     */
    public function testStripSpecsEmptySpecs()
    {
        $this->setProperty($this->listener, 'specs', []);
        $specs = $this->callMethod($this->listener, 'getSearchSpecs', [['A', 'B', 'E']]);
        $this->assertEmpty($specs);
    }

    /**
     * Don't strip anything.
     *
     * @return void
     */
    public function testStripSpecsNoFieldsToStrip()
    {
        $specs = $this->callMethod($this->listener, 'getSearchSpecs', [['F', 'G', 'H']]);
        $this->assertEquals($specs, self::$specs);
    }

    /**
     * Strip specs.
     *
     * @return void
     */
    public function testStripSpecsStrip()
    {
        $specs = $this->callMethod($this->listener, 'getSearchSpecs', [['A', 'B', 'E']]);
        $this->assertEquals(
            ['test' => [
                      'QueryFields' => [
                          0 => [
                              0 => ['AND', 50],
                              'C' => [
                                  ['onephrase', 200],
                              ],
                              'D' => [
                                  ['onephrase', 300],
                              ],
                          ],
                      ],
                      'FilterQuery' => 'format:Book',
                  ],
            ],
            $specs
        );
    }

    /**
     * Strip an entire QueryFields section.
     *
     * @return void
     */
    public function testStripSpecsAllQueryFields()
    {
        $specs = $this->callMethod($this->listener, 'getSearchSpecs', [['A', 'B', 'C', 'D', 'E']]);
        $this->assertEquals(
            ['test' => ['QueryFields' => [], 'FilterQuery' => 'format:Book']],
            $specs
        );
    }
}
