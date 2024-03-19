<?php

/**
 * Unit tests for Hide Facet Value Listener.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFindTest\Search\Base;

use Laminas\EventManager\Event;
use VuFind\Search\Base\HideFacetValueListener;
use VuFindSearch\Backend\Solr\Backend;
use VuFindSearch\Backend\Solr\Response\Json\RecordCollection;

/**
 * Unit tests for Hide Facet Value Listener.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class HideFacetValueListenerTest extends \PHPUnit\Framework\TestCase
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
     * Get a facet array for testing.
     *
     * @return array
     */
    protected function getFacets(): array
    {
        return [
            'format' => [
                'Book' => 124,
                'Unknown' => 16,
                'Fake' => 3,
            ],
        ];
    }

    /**
     * Construct a mock Solr result object.
     *
     * @return RecordCollection
     */
    protected function getMockResult(): RecordCollection
    {
        $facets = $this->getFacets();
        $result = $this->getMockBuilder(RecordCollection::class)
            ->disableOriginalConstructor()->getMock();
        $result->expects($this->any())->method('getFacets')
            ->will(
                $this->returnCallback(
                    function () use (&$facets) {
                        return $facets;
                    }
                )
            );
        $result->expects($this->any())->method('setFacets')
            ->will(
                $this->returnCallback(
                    function ($new) use (&$facets) {
                        $facets = $new;
                    }
                )
            );
        $result->expects($this->any())->method('getQueryFacets')
            ->will($this->returnValue([]));
        $result->expects($this->any())->method('getPivotFacets')
            ->will($this->returnValue([]));
        return $result;
    }

    /**
     * Construct a listener for testing.
     *
     * @param array $hideFacetValues Assoc. array of field name => values
     * to exclude from display (see also next param).
     * @param array $showFacetValues Assoc. array of field name => values
     * to exclusively show in display (see also previous param).
     *
     * @return HideFacetValueListener
     */
    protected function getListener(
        array $hideFacetValues = [],
        array $showFacetValues = []
    ): HideFacetValueListener {
        return new HideFacetValueListener(
            $this->getMockBackend(),
            $hideFacetValues,
            $showFacetValues
        );
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
            $this->equalTo('post'),
            $this->equalTo([$listener, 'onSearchPost'])
        );
        $listener->attach($mock);
    }

    /**
     * Test actual functionality of listener, with "hide facet" setting.
     *
     * @return void
     */
    public function testHideFacet(): void
    {
        $listener = $this->getListener(['format' => ['Unknown']]);
        $result = $this->getMockResult();
        $facets = $result->getFacets();
        $command = $this->getMockSearchCommand(null, 'search', 'Solr', $result);
        $params = ['backend' => 'Solr', 'context' => 'search', 'command' => $command];
        $event = new Event(null, $result, $params);
        $this->assertEquals(
            ['Book' => 124, 'Unknown' => 16, 'Fake' => 3],
            $facets['format']
        );
        $listener->onSearchPost($event);
        $facets = $result->getFacets();
        $this->assertEquals(
            ['Book' => 124, 'Fake' => 3],
            $facets['format']
        );
    }

    /**
     * Test actual functionality of listener, with "show facets" setting.
     *
     * @return void
     */
    public function testShowFacets(): void
    {
        $listener = $this->getListener([], ['format' => ['Book', 'Fake']]);
        $result = $this->getMockResult();
        $facets = $result->getFacets();
        $command = $this->getMockSearchCommand(null, 'search', 'Solr', $result);
        $params = ['backend' => 'Solr', 'context' => 'search', 'command' => $command];
        $event = new Event(null, $result, $params);
        $this->assertEquals(
            ['Book' => 124, 'Unknown' => 16, 'Fake' => 3],
            $facets['format']
        );
        $listener->onSearchPost($event);
        $facets = $result->getFacets();
        $this->assertEquals(
            ['Book' => 124, 'Fake' => 3],
            $facets['format']
        );
    }

    /**
     * Test actual functionality of listener, with "hide facets" and "show facets"
     * settings, demonstrating that both can be applied together (though doing so in
     * a real-world scenario would not really make sense).
     *
     * @return void
     */
    public function testHideFacetsAndShowFacets(): void
    {
        $listener = $this->getListener(
            ['format' => ['Fake']],
            ['format' => ['Book', 'Fake']]
        );
        $result = $this->getMockResult();
        $facets = $result->getFacets();
        $command = $this->getMockSearchCommand(null, 'search', 'Solr', $result);
        $params = ['backend' => 'Solr', 'context' => 'search', 'command' => $command];
        $event = new Event(null, $result, $params);
        $this->assertEquals(
            ['Book' => 124, 'Unknown' => 16, 'Fake' => 3],
            $facets['format']
        );
        $listener->onSearchPost($event);
        $facets = $result->getFacets();
        $this->assertEquals(
            ['Book' => 124],
            $facets['format']
        );
    }
}
