<?php

/**
 * Test functionality of the home page facets.
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
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Mink;

use VuFindTest\Feature\CacheManagementTrait;
use VuFindTest\Feature\SearchFacetFilterTrait;

/**
 * Test functionality of the home page facets.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class HomePageFacetsTest extends \VuFindTest\Integration\MinkTestCase
{
    use CacheManagementTrait;
    use SearchFacetFilterTrait;

    /**
     * Test that normal facets work properly.
     *
     * @return void
     */
    public function testNormalFacets()
    {
        $page = $this->getSearchHomePage();
        $this->waitForPageLoad($page);
        $this->assertEquals('A - General Works', $this->findCssAndGetText($page, '.home-facet.callnumber-first a'));
        $this->clickCss($page, '.home-facet.callnumber-first a');
        $this->waitForPageLoad($page);
        $this->assertStringEndsWith(
            'Search/Results?filter%5B%5D=callnumber-first%3A%22A+-+General+Works%22',
            $this->getMinkSession()->getCurrentUrl()
        );
    }

    /**
     * Data provider for testHierarchicalFacets
     *
     * @return array
     */
    public static function hierarchicalFacetsProvider(): array
    {
        return [
            'default sort' => [
                null,
                null,
                'all',
            ],
            'top level alphabetical' => [
                'top',
                'all',
                'top',
            ],
            'all alphabetical' => [
                'all',
                'top',
                'all',
            ],
            'count' => [
                'count',
                'all',
                'count',
            ],
            'top level alphabetical (inherited)' => [
                null,
                'top',
                'top',
            ],
            'all alphabetical (inherited)' => [
                null,
                'all',
                'all',
            ],
            'count (inherited)' => [
                null,
                'count',
                'count',
            ],
        ];
    }

    /**
     * Test that hierarchy facets work properly.
     *
     * @param ?string $sort         Sort option
     * @param ?string $defaultSort  Default sort option
     * @param string  $expectedSort Expected sort order of options
     *
     * @dataProvider hierarchicalFacetsProvider
     *
     * @return void
     */
    public function testHierarchicalFacets(?string $sort, ?string $defaultSort, string $expectedSort)
    {
        $config = [
            'facets' => [
                'Results' => [
                    'hierarchical_facet_str_mv' => 'hierarchy',
                ],
                'SpecialFacets' => [
                    'hierarchical[]' => 'hierarchical_facet_str_mv',
                ],
                'HomePage' => [
                    'hierarchical_facet_str_mv' => 'Hierarchical',
                ],
                'Advanced_Settings' => [
                    'translated_facets[]' => 'hierarchical_facet_str_mv:Facets',
                ],
            ],
        ];
        if (null !== $sort) {
            $config['facets']['HomePage_Settings']['hierarchicalFacetSortOptions']['hierarchical_facet_str_mv'] = $sort;
        }
        if (null !== $defaultSort) {
            $config['facets']['SpecialFacets']['hierarchicalFacetSortOptions']['hierarchical_facet_str_mv']
                = $defaultSort;
        }
        $this->changeConfigs($config + $this->getCacheClearPermissionConfig());

        // Clear object cache to ensure clean state:
        $this->clearObjectCache();
        $page = $this->getSearchHomePage();
        $this->waitForPageLoad($page);

        // Check hierarchy filter:
        $expected = $this->getExpectedHierarchicalFacetTreeItems($expectedSort);
        $actual = $this->getHierarchicalFacetTreeItems(
            $page,
            '.home-facet.hierarchical_facet_str_mv .home-facet-container'
        );
        $this->assertSame($expected, $actual);

        $this->clickCss($page, '.home-facet.hierarchical_facet_str_mv .facet');
        $this->waitForPageLoad($page);
        $expectedValue = $this->getExpectedHierarchicalFacetFilterText($expectedSort, 0);
        $this->assertAppliedFilter($page, 0, 'hierarchy', $expectedValue);
    }
}
