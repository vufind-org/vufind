<?php
/**
 * SideFacets recommendation module Test Class
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
use VuFind\Recommend\SideFacets;

/**
 * SideFacets recommendation module Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class SideFacetsTest extends \VuFindTest\Unit\TestCase
{
    /**
     * Test "getResults"
     *
     * @return void
     */
    public function testGetResults()
    {
        $results = $this->getMockResults();
        $sf = $this->getSideFacets(null, $results);
        $this->assertEquals($results, $sf->getResults());
    }

    /**
     * Test getHierarchicalFacets() and getHierarchicalFacetSortOptions()
     *
     * @return void
     */
    public function testHierarchicalGetters()
    {
        $configLoader = $this->getMockConfigLoader(
            array(
                'SpecialFacets' => array(
                    'hierarchical' => array('format'),
                    'hierarchicalFacetSortOptions' => array('a', 'b', 'c')
                )
            )
        );
        $sf = $this->getSideFacets($configLoader, null, '', null, null);
        $this->assertEquals(array('format'), $sf->getHierarchicalFacets());
        $this->assertEquals(array('a', 'b', 'c'), $sf->getHierarchicalFacetSortOptions());
    }

    /**
     * Test missing hierarchical facet helper
     *
     * @return void
     * @expectedException Exception
     * @expectedExceptionMessage VuFind\Recommend\SideFacets: hierarchical facet helper unavailable
     */
    public function testMissingHierarchicalFacetHelper()
    {
        $configLoader = $this->getMockConfigLoader(
            array(
                'Results' => array(
                    'format' => 'Format',
                ),
                'SpecialFacets' => array(
                    'hierarchical' => array('format')
                )
            )
        );
        $results = $this->getMockResults();
        $response = array('format' => array('dummy'));
        $results->expects($this->once())->method('getFacetList')
            ->with($this->equalTo(array('format' => 'Format')))
            ->will($this->returnValue($response));
        $sf = $this->getSideFacets($configLoader, $results, '', null, null);
        $sf->getFacetSet();
    }

    /**
     * Test facet initialization.
     *
     * @return void
     */
    public function testFacetInit()
    {
        $configLoader = $this->getMockConfigLoader(
            array(
                'Results' => array(
                    'format' => 'Format',
                ),
                'Results_Settings' => array(
                    'orFacets' => '*',  // test or facet support
                ),
                'Checkboxes' => array(
                    'description' => 'filter',
                )
            )
        );
        $results = $this->getMockResults();
        $params = $results->getParams();
        $params->expects($this->once())->method('addFacet')->with($this->equalTo('format'), $this->equalTo('Format'), $this->equalTo(true));
        $params->expects($this->once())->method('addCheckboxFacet')->with($this->equalTo('filter'), $this->equalTo('description'));
        $this->getSideFacets($configLoader, $results, ':~Checkboxes');  // test ~ checkbox flip function
    }

    /**
     * Test getFacetOperator
     *
     * @return void
     */
    public function testGetFacetOperator()
    {
        $this->assertEquals('AND', $this->getSideFacets()->getFacetOperator('format')); // default
        $configLoader = $this->getMockConfigLoader(
            array(
                'Results' => array(
                    'format' => 'Format',
                ),
                'Results_Settings' => array(
                    'orFacets' => '*',  // test or facet support
                ),
            )
        );
        $sf = $this->getSideFacets($configLoader);
        $this->assertEquals('OR', $sf->getFacetOperator('format'));
    }

    /**
     * Test excludeAllowed
     *
     * @return void
     */
    public function testExcludeAllowed()
    {
        $this->assertFalse($this->getSideFacets()->excludeAllowed('format')); // default
        $configLoader = $this->getMockConfigLoader(
            array(
                'Results' => array(
                    'format' => 'Format',
                ),
                'Results_Settings' => array(
                    'exclude' => '*',  // test or facet support
                ),
            )
        );
        $sf = $this->getSideFacets($configLoader);
        $this->assertTrue($sf->excludeAllowed('format'));
    }

    /**
     * Test getVisibleFilters
     *
     * @return void
     */
    public function testGetVisibleFilters()
    {
        $filters = array(
            'format' => array(
                array('value' => 'foo'),
                array('value' => 'bar', 'suppressDisplay' => true),
            ),
        );
        $results = $this->getMockResults();
        $results->getParams()->expects($this->once())->method('getFilterList')
            ->with($this->equalTo(true))->will($this->returnValue($filters));
        $sf = $this->getSideFacets(null, $results);
        $this->assertEquals(
            array(
                'format' => array(array('value' => 'foo')),
                'extra' => array(array('value' => 'baz')),
            ),
            $sf->getVisibleFilters(array('extra' => array(array('value' => 'baz'))))
        );
    }

    /**
     * Test getAllRangeFacets()
     *
     * @return void
     */
    public function testGetAllRangeFacets()
    {
        $config = array(
            'SpecialFacets' => array(
                'dateRange' => array('date'),
                'fullDateRange' => array('fullDate'),
                'genericRange' => array('generic'),
                'numericRange' => array('numeric'),
            )
        );
        $filters = array(
            'date' => array('[1900 TO 1905]'),
            'fullDate' => array('[1900-01-01 TO 1905-12-31]'),
            'generic' => array('[A TO Z]'),
            'numeric' => array('[1 TO 9]'),
        );
        $results = $this->getMockResults();
        $results->getParams()->expects($this->any())->method('getFilters')
            ->will($this->returnValue($filters));
        $sf = $this->getSideFacets($this->getMockConfigLoader($config), $results);
        $expected = array(
            'date' => array('type' => 'date', 'values' => array('1900', '1905')),
            'fullDate' => array('type' => 'fulldate', 'values' => array('1900-01-01', '1905-12-31')),
            'generic' => array('type' => 'generic', 'values' => array('A', 'Z')),
            'numeric' => array('type' => 'numeric', 'values' => array('1', '9')),
        );
        $this->assertEquals($expected, $sf->getAllRangeFacets());
    }

    /**
     * Test default getCollapsedFacets behavior.
     *
     * @return void
     */
    public function testGetCollapsedFacetsDefault()
    {
        $this->assertEquals(array(), $this->getSideFacets()->getCollapsedFacets());
    }

    /**
     * Test asterisk support in getCollapsedFacets
     *
     * @return void
     */
    public function testGetCollapsedFacetsDelimitedList()
    {
        $config = array(
            'Results_Settings' => array('collapsedFacets' => '   foo, bar,baz   '),
        );
        $sf = $this->getSideFacets($this->getMockConfigLoader($config));
        $this->assertEquals(array('foo', 'bar', 'baz'), $sf->getCollapsedFacets());
    }

    /**
     * Test delimited list support in getCollapsedFacets
     *
     * @return void
     */
    public function testGetCollapsedFacetsWildcard()
    {
        $config = array(
            'Results' => array(
                'format' => 'Format',
            ),
           'Results_Settings' => array('collapsedFacets' => '*'),
        );
        $filters = array(
            'format' => array(
                array('value' => 'foo'),
                array('value' => 'bar', 'suppressDisplay' => true),
            ),
        );
        $results = $this->getMockResults();
        $response = array('format' => array('dummy'));
        $results->expects($this->once())->method('getFacetList')
            ->with($this->equalTo(array('format' => 'Format')))
            ->will($this->returnValue($response));
        $sf = $this->getSideFacets($this->getMockConfigLoader($config), $results);
        $this->assertEquals(array('format'), $sf->getCollapsedFacets());
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
            $request = new \Zend\StdLib\Parameters(array());
        }
        $sf = new SideFacets($configLoader, $facetHelper);
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
    protected function getMockConfigLoader($config = array(), $key = 'facets')
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