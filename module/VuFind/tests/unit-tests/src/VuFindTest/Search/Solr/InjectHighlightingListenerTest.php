<?php

/**
 * Unit tests for inject highlighting listener.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFindTest\Search\Solr;

use Laminas\EventManager\Event;
use VuFind\Search\Solr\InjectHighlightingListener;
use VuFindSearch\Backend\Solr\QueryBuilder;
use VuFindSearch\ParamBag;
use VuFindSearch\Service;

/**
 * Unit tests for inject highlighting listener.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class InjectHighlightingListenerTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\MockSearchCommandTrait;
    use \VuFindTest\Feature\WithConsecutiveTrait;

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
        $this->backend = $this->createMock(\VuFindSearch\Backend\Solr\Backend::class);
        $this->backend->expects($this->any())->method('getIdentifier')->will($this->returnValue('foo'));
        $this->listener = new InjectHighlightingListener($this->backend, 'bar,baz', ['xyzzy' => 'true']);
    }

    /**
     * Test attaching listener.
     *
     * @return void
     */
    public function testAttach()
    {
        $mock = $this->createMock(\Laminas\EventManager\SharedEventManagerInterface::class);
        $mock->expects($this->once())
            ->method('attach')
            ->with(\VuFindSearch\Service::class, 'pre', [$this->listener, 'onSearchPre']);
        $this->listener->attach($mock);
    }

    /**
     * Test that appropriate parameters are sent to connector.
     *
     * @return void
     */
    public function testParameters()
    {
        $params = new ParamBag(
            [
                'hl' => 'true',
            ]
        );
        $command = $this->getMockSearchCommand(
            $params,
            'search',
            $this->backend->getIdentifier()
        );
        $mockQueryBuilder = $this->createMock(QueryBuilder::class);
        $this->backend->expects($this->once())->method('getQueryBuilder')
            ->will($this->returnValue($mockQueryBuilder));
        $mockQueryBuilder->expects($this->once())->method('setFieldsToHighlight')
            ->with($this->equalTo('bar,baz'));
        $event = new Event(
            Service::EVENT_PRE,
            $this->backend,
            compact('params', 'command')
        );
        $this->listener->onSearchPre($event);
        $this->assertEquals(
            [
                'hl' => ['true'],
                'xyzzy' => ['true'],
                'hl.simple.pre' => ['{{{{START_HILITE}}}}'],
                'hl.simple.post' => ['{{{{END_HILITE}}}}'],
            ],
            $params->getArrayCopy()
        );
    }
}
