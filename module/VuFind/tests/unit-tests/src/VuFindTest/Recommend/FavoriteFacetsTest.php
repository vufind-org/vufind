<?php
/**
 * FavoriteFacets recommendation module Test Class
 *
 * PHP version 7
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

use VuFind\Recommend\FavoriteFacets;

/**
 * FavoriteFacets recommendation module Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class FavoriteFacetsTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test facet initialization with disabled tags.
     *
     * @return void
     */
    public function testFacetInitWithDisabledTags()
    {
        $results = $this->getMockResults();
        $params = $results->getParams();
        $params->expects($this->exactly(0))->method('addFacet'); // no facets are expected in this case
        $this->getFavoriteFacets($results, 'disabled');
    }

    /**
     * Test facet initialization with enabled tags.
     *
     * @return void
     */
    public function testFacetInitWithEnabledTags()
    {
        $results = $this->getMockResults();
        $params = $results->getParams();
        $params->expects($this->once())->method('addFacet')->with($this->equalTo('tags'), $this->equalTo('Your Tags'), $this->equalTo(false));
        $this->getFavoriteFacets($results);
    }

    /**
     * Get a fully configured module
     *
     * @param \VuFind\Search\Solr\Results                 $results      results object
     * @param string                                      $tagSetting   Are tags enabled?
     * @param string                                      $settings     settings
     * @param \Laminas\Stdlib\Parameters                     $request      request
     * @param \VuFind\Search\Solr\HierarchicalFacetHelper $facetHelper  hierarchical facet helper (true to build default, null to omit)
     * @param \VuFind\Config\PluginManager                $configLoader config loader
     *
     * @return FavoriteFacets
     */
    protected function getFavoriteFacets($results = null, $tagSetting = 'enabled', $settings = '', $request = null, $facetHelper = null, $configLoader = null)
    {
        if (null === $configLoader) {
            $configLoader = $this->getMockConfigLoader();
        }
        if (null === $results) {
            $results = $this->getMockResults();
        }
        if (true === $facetHelper) {
            $facetHelper = new \VuFind\Search\Solr\HierarchicalFacetHelper();
        }
        if (null === $request) {
            $request = new \Laminas\Stdlib\Parameters([]);
        }
        $sf = new FavoriteFacets($configLoader, $facetHelper, $tagSetting);
        $sf->setConfig($settings);
        $sf->init($results->getParams(), $request);
        $sf->process($results);
        return $sf;
    }

    /**
     * Get a mock config loader.
     *
     * @param array  $config Configuration to return
     * @param string $key    Key to store configuration under
     *
     * @return \VuFind\Config\PluginManager
     */
    protected function getMockConfigLoader($config = [], $key = 'config')
    {
        $loader = $this->getMockBuilder(\VuFind\Config\PluginManager::class)
            ->disableOriginalConstructor()->getMock();
        $loader->expects($this->any())->method('get')->with($this->equalTo($key))
            ->will($this->returnValue(new \Laminas\Config\Config($config)));
        return $loader;
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
