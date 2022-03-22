<?php
/**
 * SideFacets recommendation module Test Class
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

use VuFind\Recommend\SideFacets;
use VuFind\Search\Solr\HierarchicalFacetHelper;
use VuFind\Search\Solr\Params;
use VuFind\Search\Solr\Results;

/**
 * SideFacets recommendation module Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SideFacetsTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test "getResults"
     *
     * @return void
     */
    public function testGetResults(): void
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
    public function testHierarchicalGetters(): void
    {
        $configLoader = $this->getMockConfigLoader(
            [
                'SpecialFacets' => [
                    'hierarchical' => ['format'],
                    'hierarchicalFacetSortOptions' => ['a', 'b', 'c']
                ]
            ]
        );
        $sf = $this->getSideFacets($configLoader, null, '', null, null);
        $this->assertEquals(['format'], $sf->getHierarchicalFacets());
        $this->assertEquals(['a', 'b', 'c'], $sf->getHierarchicalFacetSortOptions());
    }

    /**
     * Test missing hierarchical facet helper
     *
     * @return void
     */
    public function testMissingHierarchicalFacetHelper(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'VuFind\\Recommend\\SideFacets: hierarchical facet helper unavailable'
        );

        $configLoader = $this->getMockConfigLoader(
            [
                'Results' => [
                    'format' => 'Format',
                ],
                'SpecialFacets' => [
                    'hierarchical' => ['format']
                ]
            ]
        );
        $results = $this->getMockResults();
        $response = ['format' => ['dummy']];
        $results->expects($this->once())->method('getFacetList')
            ->with($this->equalTo(['format' => 'Format']))
            ->will($this->returnValue($response));
        $sf = $this->getSideFacets($configLoader, $results, '', null, null);
        $sf->getFacetSet();
    }

    /**
     * Test facet initialization.
     *
     * @return void
     */
    public function testFacetInit(): void
    {
        $configLoader = $this->getMockConfigLoader(
            [
                'Results' => [
                    'format' => 'Format',
                ],
                'Results_Settings' => [
                    'orFacets' => '*',  // test or facet support
                ],
                'Checkboxes' => [
                    'description' => 'filter',
                ]
            ]
        );
        $results = $this->getMockResults();
        $params = $results->getParams();
        $params->expects($this->once())->method('addFacet')
            ->with(
                $this->equalTo('format'),
                $this->equalTo('Format'),
                $this->equalTo(true)
            );
        $params->expects($this->once())
            ->method('addCheckboxFacet')
            ->with($this->equalTo('filter'), $this->equalTo('description'));
        // test ~ checkbox flip function:
        $this->getSideFacets($configLoader, $results, ':~Checkboxes');
    }

    /**
     * Test getFacetOperator
     *
     * @return void
     */
    public function testGetFacetOperator(): void
    {
        $this->assertEquals(
            'AND',
            $this->getSideFacets()->getFacetOperator('format')
        ); // default
        $configLoader = $this->getMockConfigLoader(
            [
                'Results' => [
                    'format' => 'Format',
                ],
                'Results_Settings' => [
                    'orFacets' => '*',  // test or facet support
                ],
            ]
        );
        $sf = $this->getSideFacets($configLoader);
        $this->assertEquals('OR', $sf->getFacetOperator('format'));
    }

    /**
     * Test excludeAllowed
     *
     * @return void
     */
    public function testExcludeAllowed(): void
    {
        $this->assertFalse(
            $this->getSideFacets()->excludeAllowed('format')
        ); // default
        $configLoader = $this->getMockConfigLoader(
            [
                'Results' => [
                    'format' => 'Format',
                ],
                'Results_Settings' => [
                    'exclude' => '*',  // test or facet support
                ],
            ]
        );
        $sf = $this->getSideFacets($configLoader);
        $this->assertTrue($sf->excludeAllowed('format'));
    }

    /**
     * Test getAllRangeFacets()
     *
     * @return void
     */
    public function testGetAllRangeFacets(): void
    {
        $config = [
            'SpecialFacets' => [
                'dateRange' => ['date'],
                'fullDateRange' => ['fullDate'],
                'genericRange' => ['generic'],
                'numericRange' => ['numeric'],
            ]
        ];
        $filters = [
            'date' => ['[1900 TO 1905]'],
            'fullDate' => ['[1900-01-01 TO 1905-12-31]'],
            'generic' => ['[A TO Z]'],
            'numeric' => ['[1 TO 9]'],
        ];
        $results = $this->getMockResults();
        $results->getParams()->expects($this->any())->method('getRawFilters')
            ->will($this->returnValue($filters));
        $sf = $this->getSideFacets($this->getMockConfigLoader($config), $results);
        $expected = [
            'date' => ['type' => 'date', 'values' => ['1900', '1905']],
            'fullDate' => [
                'type' => 'fulldate', 'values' => ['1900-01-01', '1905-12-31']
            ],
            'generic' => ['type' => 'generic', 'values' => ['A', 'Z']],
            'numeric' => ['type' => 'numeric', 'values' => ['1', '9']],
        ];
        $this->assertEquals($expected, $sf->getAllRangeFacets());
    }

    /**
     * Test default getCollapsedFacets behavior.
     *
     * @return void
     */
    public function testGetCollapsedFacetsDefault(): void
    {
        $this->assertEquals([], $this->getSideFacets()->getCollapsedFacets());
    }

    /**
     * Test asterisk support in getCollapsedFacets
     *
     * @return void
     */
    public function testGetCollapsedFacetsDelimitedList(): void
    {
        $config = [
            'Results_Settings' => ['collapsedFacets' => '   foo, bar,baz   '],
        ];
        $sf = $this->getSideFacets($this->getMockConfigLoader($config));
        $this->assertEquals(['foo', 'bar', 'baz'], $sf->getCollapsedFacets());
    }

    /**
     * Test delimited list support in getCollapsedFacets
     *
     * @return void
     */
    public function testGetCollapsedFacetsWildcard(): void
    {
        $config = [
            'Results' => [
                'format' => 'Format',
            ],
            'Results_Settings' => ['collapsedFacets' => '*'],
        ];
        $results = $this->getMockResults();
        $sf = $this->getSideFacets($this->getMockConfigLoader($config), $results);
        $this->assertEquals(['format'], $sf->getCollapsedFacets());
    }

    /**
     * Test that getCheckboxFacetSet returns an empty array when no checkboxes are
     * requested.
     *
     * @return void
     */
    public function testGetCheckboxFacetSetDefaultsToEmptyArray(): void
    {
        $results = $this->getMockResults();
        $params = $results->getParams();
        $params->expects($this->never())->method('getCheckboxFacets');
        $params->expects($this->never())->method('addCheckboxFacet');
        $sf = $this->getSideFacets(null, $results);
        $this->assertEquals([], $sf->getCheckboxFacetSet());
    }

    /**
     * Test that getCheckboxFacetSet returns values when expected.
     *
     * @return void
     */
    public function testGetCheckboxFacetSetReturnsValueWhenAppropriate(): void
    {
        $configLoader = $this->getMockConfigLoader(
            ['Checkboxes' => ['foo' => 'bar']]
        );
        $checkboxData = ['fake result'];
        $results = $this->getMockResults();
        $params = $results->getParams();
        $params->expects($this->once())->method('getCheckboxFacets')
            ->with($this->equalTo(['foo']))
            ->will($this->returnValue($checkboxData));
        $params->expects($this->once())->method('addCheckboxFacet')
            ->with($this->equalTo('foo'), $this->equalTo('bar'));
        $sf = $this->getSideFacets($configLoader, $results, ':Checkboxes');
        $this->assertEquals($checkboxData, $sf->getCheckboxFacetSet());
    }

    /**
     * Get a fully configured module
     *
     * @param \VuFind\Config\PluginManager $configLoader config loader
     * @param Results                      $results      results object
     * @param string                       $settings     settings
     * @param \Laminas\Stdlib\Parameters   $request      request
     * @param HierarchicalFacetHelper|bool $facetHelper  hierarchical facet helper
     * (true to build default, null to omit)
     *
     * @return SideFacets
     */
    protected function getSideFacets(
        \VuFind\Config\PluginManager $configLoader = null,
        Results $results = null,
        string $settings = '',
        \Laminas\Stdlib\Parameters $request = null,
        $facetHelper = true
    ): SideFacets {
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
    protected function getMockConfigLoader(
        array $config = [],
        string $key = 'facets'
    ): \VuFind\Config\PluginManager {
        $loader = $this->getMockBuilder(\VuFind\Config\PluginManager::class)
            ->disableOriginalConstructor()->getMock();
        $loader->expects($this->once())->method('get')->with($this->equalTo($key))
            ->will($this->returnValue(new \Laminas\Config\Config($config)));
        return $loader;
    }

    /**
     * Get a mock results object.
     *
     * @param Params $params Params to include in container.
     *
     * @return Results
     */
    protected function getMockResults(Params $params = null): Results
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
     * @return Params
     */
    protected function getMockParams(\VuFindSearch\Query\Query $query = null): Params
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
