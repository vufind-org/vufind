<?php

/**
 * ExpandFacets recommendation module Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Recommend;

use VuFind\Recommend\ExpandFacets;

/**
 * ExpandFacets recommendation module Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ExpandFacetsTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ConfigPluginManagerTrait;

    /**
     * Test getEmptyResults()
     *
     * @return void
     */
    public function testGetEmptyResults()
    {
        $results = $this->getMockResults();
        $ef = $this->getExpandFacets(null, null, $results);
        $this->assertEquals($results, $ef->getEmptyResults());
    }

    /**
     * Test facet initialization.
     *
     * @return void
     */
    public function testFacetInit()
    {
        $config = [
            'facets' => [
                'Results' => [
                    'format' => 'Format',
                ],
            ],
        ];
        $results = $this->getMockResults();
        $params = $results->getParams();
        $params->expects($this->once())
            ->method('addFacet')
            ->with($this->equalTo('format'), $this->equalTo('Format'));
        $results->expects($this->once())
            ->method('getFacetList')
            ->with($this->equalTo(['format' => 'Format']))
            ->will($this->returnValue(['foo']));
        $ef = $this->getExpandFacets(
            $this->getMockConfigPluginManager($config, [], $this->once()),
            $results
        );
        $this->assertEquals(['foo'], $ef->getExpandedSet());
    }

    /**
     * Get a fully configured module
     *
     * @param \VuFind\Config\PluginManager $configLoader config loader
     * @param \VuFind\Search\Solr\Results  $results      populated results object
     * @param \VuFind\Search\Solr\Results  $emptyResults empty results object
     * @param string                       $settings     settings
     * @param \Laminas\Stdlib\Parameters   $request      request
     *
     * @return ExpandFacets
     */
    protected function getExpandFacets(
        $configLoader = null,
        $results = null,
        $emptyResults = null,
        $settings = '',
        $request = null
    ) {
        if (null === $results) {
            $results = $this->getMockResults();
        }
        $sf = new ExpandFacets(
            $configLoader ?? $this->getMockConfigPluginManager([]),
            $emptyResults ?? $this->getMockResults()
        );
        $sf->setConfig($settings);
        $sf->init(
            $results->getParams(),
            $request ?? new \Laminas\Stdlib\Parameters([])
        );
        $sf->process($results);
        return $sf;
    }

    /**
     * Get a mock results object.
     *
     * @param \VuFind\Search\Solr\Params $params Params to include in container.
     *
     * @return \VuFind\Search\Solr\Results
     */
    protected function getMockResults($params = null)
    {
        if (null === $params) {
            $params = $this->getMockParams();
        }
        $results = $this->getMockBuilder(\VuFind\Search\Solr\Results::class)
            ->disableOriginalConstructor()->getMock();
        $results->expects($this->any())->method('getParams')
            ->will($this->returnValue($params));
        return $results;
    }

    /**
     * Get a mock params object.
     *
     * @param \VuFindSearch\Query\Query $query Query to include in container.
     *
     * @return \VuFind\Search\Solr\Params
     */
    protected function getMockParams($query = null)
    {
        if (null === $query) {
            $query = new \VuFindSearch\Query\Query('foo', 'bar');
        }
        $params = $this->getMockBuilder(\VuFind\Search\Solr\Params::class)
            ->disableOriginalConstructor()->getMock();
        $params->expects($this->any())->method('getQuery')
            ->will($this->returnValue($query));
        return $params;
    }
}
