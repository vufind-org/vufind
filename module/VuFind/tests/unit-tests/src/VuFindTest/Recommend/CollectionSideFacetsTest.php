<?php
/**
 * CollectionSideFacets recommendation module Test Class
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
namespace VuFindTest\Recommend;
use VuFind\Recommend\CollectionSideFacets;

/**
 * CollectionSideFacets recommendation module Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class CollectionSideFacetsTest extends \VuFindTest\Unit\TestCase
{
    /**
     * Test "getResults"
     *
     * @return void
     */
    public function testKeywordFilter()
    {
        $results = $this->getMockResults();
        $results->getParams()->expects($this->once())->method('getDisplayQuery')->will($this->returnValue('foo'));
        $csf = $this->getSideFacets(null, $results, '::facets:true');
        $this->assertEquals('foo', $csf->getKeywordFilter());
        $this->assertTrue($csf->keywordFilterEnabled());
    }

    /**
     * Get a fully configured module
     *
     * @param \VuFind\Config\PluginManager                $configLoader config loader
     * @param \VuFind\Search\Solr\Results                 $results      results object
     * @param string                                      $settings     settings
     * @param \Zend\StdLib\Parameters                     $request      request
     * @param \VuFind\Search\Solr\HierarchicalFacetHelper $facetHelper  hierarchical facet helper (true to build default, null to omit)
     *
     * @return SideFacets
     */
    protected function getSideFacets($configLoader = null, $results = null, $settings = '', $request = null, $facetHelper = true)
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
            $request = new \Zend\StdLib\Parameters([]);
        }
        $sf = new CollectionSideFacets($configLoader, $facetHelper);
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
    protected function getMockConfigLoader($config = [], $key = 'facets')
    {
        $loader = $this->getMockBuilder('VuFind\Config\PluginManager')
            ->disableOriginalConstructor()->getMock();
        $loader->expects($this->once())->method('get')->with($this->equalTo($key))
            ->will($this->returnValue(new \Zend\Config\Config($config)));
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
        $results = $this->getMockBuilder('VuFind\Search\Solr\Results')
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
        $params = $this->getMockBuilder('VuFind\Search\Solr\Params')
            ->disableOriginalConstructor()->getMock();
        $params->expects($this->any())->method('getQuery')
            ->will($this->returnValue($query));
        return $params;
    }
}