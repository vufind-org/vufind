<?php

/**
 * Solr Collection Search Object Params Test
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace FinnaTest\Search\SolrCollection;

use Finna\Search\SolrCollection\Options;
use Finna\Search\SolrCollection\Params;
use Laminas\Stdlib\Parameters;
use VuFind\Config\PluginManager;

/**
 * Solr Collection Search Object Params Test
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ParamsTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ConfigRelatedServicesTrait;

    /**
     * Get Params object
     *
     * @param ?Options       $options    Options object (null to create)
     * @param ?PluginManager $mockConfig Mock config plugin manager (null to create)
     *
     * @return Params
     */
    protected function getParams(
        ?Options $options = null,
        ?PluginManager $mockConfig = null
    ): Params {
        $mockConfig ??= $this->getMockConfigPluginManager([]);
        return new Params(
            $options ?? new Options($mockConfig),
            $mockConfig,
        );
    }

    /**
     * Get Options object
     *
     * @param ?PluginManager $configManager Config manager for Options object (null
     * for new mock)
     *
     * @return Options
     */
    protected function getOptions(?PluginManager $configManager = null): Options
    {
        return new Options($configManager ?? $this->getMockConfigPluginManager([]));
    }

    /**
     * Test that filters work as expected.
     *
     * @return void
     */
    public function testFilters(): void
    {
        $options = $this->getOptions(
            $this->getMockConfigPluginManager(
                ['Collection' => [
                    'SpecialFacets' => [
                        'dateRangeVis' => 'search_daterange_mv',
                    ],
                ]]
            )
        );
        $params = $this->getParams($options);

        // No filters:
        $this->assertEquals(null, $params->getBackendParameters()->get('fq'));

        // Add filters:
        $params->addFilter('~hierarchical:abc');
        $params->addFilter('~hierarchical:dfg:hij');
        $this->assertEquals(
            [
                '{!tag=hierarchical_filter}hierarchical:(hierarchical:"abc" OR hierarchical:"dfg:hij")',
            ],
            $params->getBackendParameters()->get('fq')
        );

        // Remove filter:
        $params->removeFilter('~hierarchical:dfg:hij');
        $this->assertEquals(
            [
                '{!tag=hierarchical_filter}hierarchical:(hierarchical:"abc")',
            ],
            $params->getBackendParameters()->get('fq')
        );
        $params->removeFilter('~hierarchical:abc');
        $this->assertEquals('', $params->getBackendParameters()->get('fq'));

        // Is specified as date range filter and not added through method
        $params->addFilter('search_daterange_mv:"[0503 TO 1061]');
        $this->assertEquals('', $params->getBackendParameters()->get('fq'));

        // Date range filter added
        $query = [
            'filter' => ['search_daterange_mv:"[1000 TO 2000]"'],
        ];
        $params->initSpatialDateRangeFilter(new Parameters($query));
        $this->assertEquals(
            [
                '{!field f=search_daterange_mv op=Intersects}[1000 TO 2000]',
            ],
            $params->getBackendParameters()->get('fq')
        );

        // Date range not defined as special facet
        $options = $this->getOptions(
            $this->getMockConfigPluginManager(['Collection' => ['SpecialFacets' => [],]])
        );
        $params = $this->getParams($options);
        $params->initSpatialDateRangeFilter(new Parameters($query));
        $this->assertEquals(
            null,
            $params->getBackendParameters()->get('fq')
        );
    }

    /**
     * Test parseDateRangeFilter
     *
     * @return void
     */
    public function testParseDateRangeFilter(): void
    {
        $params = $this->getParams();
        $filter = $params->parseDateRangeFilter('search_daterange_mv:"[0001 TO *]"');
        $this->assertEquals(
            [
                'from' => '0001',
                'to' => '*',
                'type' => 'overlap',
            ],
            $filter
        );
        $filter = $params->parseDateRangeFilter('search_daterange_mv:"[01.234 TO 5555]"');
        $this->assertEquals(false, $filter);
    }
}
