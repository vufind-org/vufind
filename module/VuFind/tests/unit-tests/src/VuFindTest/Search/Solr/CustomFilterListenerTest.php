<?php

/**
 * Unit tests for Custom Filter Listener.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2022.
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
use VuFind\Search\Solr\CustomFilterListener;
use VuFindSearch\Backend\Solr\Backend;
use VuFindSearch\ParamBag;

/**
 * Unit tests for Custom Filter Listener.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class CustomFilterListenerTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\MockSearchCommandTrait;

    /**
     * Get a mock backend
     *
     * @param string $id ID of fake backend.
     *
     * @return Backend
     */
    protected function getMockBackend(string $id = 'Solr'): Backend
    {
        $backend = $this->getMockBuilder(Backend::class)
            ->disableOriginalConstructor()->getMock();
        $backend->expects($this->any())->method('getIdentifier')->will(
            $this->returnValue($id)
        );
        return $backend;
    }

    /**
     * Construct a listener for testing.
     *
     * @param array $normal   Normal custom filters
     * @param array $inverted Inverted custom filters
     *
     * @return CustomFilterListener
     */
    protected function getListener(
        array $normal = [],
        array $inverted = []
    ): CustomFilterListener {
        return new CustomFilterListener($this->getMockBackend(), $normal, $inverted);
    }

    /**
     * Test attaching listener.
     *
     * @return void
     */
    public function testAttach(): void
    {
        $listener = $this->getListener();
        $mock = $this->createMock(\Laminas\EventManager\SharedEventManagerInterface::class);
        $mock->expects($this->once())->method('attach')->with(
            $this->equalTo(\VuFindSearch\Service::class),
            $this->equalTo('pre'),
            $this->equalTo([$listener, 'onSearchPre'])
        );
        $listener->attach($mock);
    }

    /**
     * Test remapping of custom filters.
     *
     * @return void
     */
    public function testRemapping(): void
    {
        $normal = [
            'vufind:"normal"' => 'field1:normal OR field2:alsoNormal',
        ];
        $listener = $this->getListener($normal);
        $params = new ParamBag(['fq' => ['foo:"bar"', 'vufind:"normal"']]);
        $command = $this->getMockSearchCommand($params, 'search', 'Solr');
        $event = new Event(null, null, compact('command'));
        $listener->onSearchPre($event);
        $this->assertEquals(
            ['foo:"bar"', 'field1:normal OR field2:alsoNormal'],
            $params->get('fq')
        );
    }

    /**
     * Test that we don't apply changes to the wrong backend.
     *
     * @return void
     */
    public function testMismatchedBackendIsIgnored(): void
    {
        $normal = [
            'vufind:"normal"' => 'field1:normal OR field2:alsoNormal',
        ];
        $listener = $this->getListener($normal);
        $params = new ParamBag(['fq' => ['foo:"bar"', 'vufind:"normal"']]);
        $command = $this->getMockSearchCommand($params, 'search', 'Search2');
        $event = new Event(null, null, compact('command'));
        $listener->onSearchPre($event);
        $this->assertEquals(
            ['foo:"bar"', 'vufind:"normal"'],
            $params->get('fq')
        );
    }

    /**
     * Test that we don't apply changes to the wrong context.
     *
     * @return void
     */
    public function testWrongContextIsIgnored(): void
    {
        $normal = [
            'vufind:"normal"' => 'field1:normal OR field2:alsoNormal',
        ];
        $listener = $this->getListener($normal);
        $params = new ParamBag(['fq' => ['foo:"bar"', 'vufind:"normal"']]);
        $command = $this->getMockSearchCommand($params, 'weird', 'Solr');
        $event = new Event(null, null, compact('command'));
        $listener->onSearchPre($event);
        $this->assertEquals(
            ['foo:"bar"', 'vufind:"normal"'],
            $params->get('fq')
        );
    }

    /**
     * Test inverted filter functionality (part 1) -- if the inverted filter is
     * not set, the filter should be applied.
     *
     * @return void
     */
    public function testMissingInvertedFilterAddsContent(): void
    {
        $inverted = [
            'vufind:"inverted"' => 'field3:invertedFilter',
        ];
        $listener = $this->getListener([], $inverted);
        $params = new ParamBag(['fq' => ['foo:"bar"']]);
        $command = $this->getMockSearchCommand($params, 'search', 'Solr');
        $event = new Event(null, null, compact('command'));
        $listener->onSearchPre($event);
        $this->assertEquals(
            ['foo:"bar"', 'field3:invertedFilter'],
            $params->get('fq')
        );
    }

    /**
     * Test inverted filter functionality (part 2) -- if the inverted filter is
     * set, the filter should not be applied.
     *
     * @return void
     */
    public function testInvertedFilterPreventsAdditionOfContent(): void
    {
        $inverted = [
            'vufind:"inverted"' => 'field3:invertedFilter',
        ];
        $listener = $this->getListener([], $inverted);
        $params = new ParamBag(['fq' => ['foo:"bar"', 'vufind:"inverted"']]);
        $command = $this->getMockSearchCommand($params, 'search', 'Solr');
        $event = new Event(null, null, compact('command'));
        $listener->onSearchPre($event);
        $this->assertEquals(['foo:"bar"'], $params->get('fq'));
    }
}
