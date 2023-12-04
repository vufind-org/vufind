<?php

/**
 * Mink search facet/filter functionality test class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2011.
 * Copyright (C) The National Library of Finland 2023.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Mink;

use Behat\Mink\Element\Element;

use function count;

/**
 * Mink search facet/filter functionality test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 * @retry    4
 */
class SearchFacetsTest extends \VuFindTest\Integration\MinkTestCase
{
    /**
     * CSS selector for finding the active filter values
     *
     * @var string
     */
    protected $activeFilterSelector = '.active-filters.hidden-xs .filters .filter-value';

    /**
     * CSS selector for finding the active filter labels
     *
     * @var string
     */
    protected $activeFilterLabelSelector = '.active-filters.hidden-xs .filters .filters-title';

    /**
     * CSS selector for finding the first hierarchical facet expand button
     *
     * @var string
     */
    protected $facetExpandSelector = '.facet-tree .facet-tree__toggle-expanded .facet-tree__expand';

    /**
     * CSS selector for finding the first expanded hierarchical facet
     *
     * @var string
     */
    protected $facetExpandedSelector = '.facet-tree button[aria-expanded=true] ~ ul';

    /**
     * CSS selector for finding the first second level hierarchical facet
     *
     * @var string
     */
    protected $facetSecondLevelLinkSelector = '.facet-tree button[aria-expanded=true] ~ ul a';

    /**
     * CSS selector for finding the first active second level hierarchical facet
     *
     * @var string
     */
    protected $facetSecondLevelActiveLinkSelector = '.facet-tree button[aria-expanded=true] ~ ul a.active';

    /**
     * CSS selector for finding the first second level hierarchical facet
     *
     * @var string
     */
    protected $facetSecondLevelExcludeLinkSelector = '.facet-tree button[aria-expanded=true] ~ ul a.exclude';

    /**
     * Get filtered search
     *
     * @return Element
     */
    protected function getFilteredSearch(): Element
    {
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Search/Results?filter%5B%5D=building%3A"weird_ids.mrc"');
        return $session->getPage();
    }

    /**
     * Helper function for simple facet application test
     *
     * @param Element $page Mink page object
     *
     * @return void
     */
    protected function facetApplyProcedure(Element $page): void
    {
        // Confirm that we have 9 results and no filters to begin with:
        $time = $this->findCss($page, '.search-query-time');
        $stats = $this->findCss($page, '.search-stats');
        $this->assertEquals(
            "Showing 1 - 9 results of 9 for search 'building:weird_ids.mrc'" . $time->getText(),
            $stats->getText()
        );
        $items = $page->findAll('css', $this->activeFilterSelector);
        $this->assertCount(0, $items);

        // Facet to Fiction (after making sure we picked the right link):
        $facetList = $this->findCss($page, '#side-collapse-genre_facet a[data-title="Fiction"]');
        $this->assertEquals('Fiction 7 results 7', $facetList->getText());
        $facetList->click();

        // Check that when the page reloads, we have fewer results and a filter:
        $this->waitForPageLoad($page);
        $time = $this->findCss($page, '.search-query-time');
        $stats = $this->findCss($page, '.search-stats');
        $this->assertEquals(
            "Showing 1 - 7 results of 7 for search 'building:weird_ids.mrc'" . $time->getText(),
            $stats->getText()
        );
        $items = $page->findAll('css', $this->activeFilterSelector);
        $this->assertCount(1, $items);
    }

    /**
     * Helper function for facets lists
     *
     * @param Element $page            Mink page object
     * @param int     $limit           Configured lightbox length
     * @param bool    $exclusionActive Is facet exclusion on?
     *
     * @return void
     */
    protected function facetListProcedure(Element $page, int $limit, bool $exclusionActive = false): void
    {
        $this->waitForPageLoad($page);
        $items = $page->findAll('css', '#modal #facet-list-count .js-facet-item');
        $this->assertCount($limit, $items);
        $excludes = $page
            ->findAll('css', '#modal #facet-list-count .exclude');
        $this->assertEquals($exclusionActive ? $limit : 0, count($excludes));
        // more
        $this->clickCss($page, '#modal .js-facet-next-page');
        $this->waitForPageLoad($page);
        $items = $page->findAll('css', '#modal #facet-list-count .js-facet-item');
        $this->assertEquals($limit * 2, count($items));
        $excludeControl = $exclusionActive ? 'Exclude matching results ' : '';
        $this->assertEquals(
            'Weird IDs 9 results 9 ' . $excludeControl
            . 'Fiction 7 results 7 ' . $excludeControl
            . 'The Study Of P|pes 1 results 1 ' . $excludeControl
            . 'The Study and Scor_ng of Dots.and-Dashes:Colons 1 results 1 ' . $excludeControl
            . 'The Study of "Important" Things 1 results 1 ' . $excludeControl
            . 'The Study of %\'s? 1 results 1 ' . $excludeControl
            . 'The Study of +\'s? 1 results 1 ' . $excludeControl
            . 'The Study of @Twitter #test 1 results 1 ' . $excludeControl
            . 'more…',
            $this->findCss($page, '#modal #facet-list-count')->getText()
        );
        $excludes = $page
            ->findAll('css', '#modal #facet-list-count .exclude');
        $this->assertEquals($exclusionActive ? $limit * 2 : 0, count($excludes));

        // sort by title
        $this->clickCss($page, '[data-sort="index"]');
        $this->waitForPageLoad($page);
        $items = $page->findAll('css', '#modal #facet-list-index .js-facet-item');
        $this->assertCount($limit, $items); // reset number of items
        $this->assertEquals(
            'Fiction 7 results 7 ' . $excludeControl
            . 'The Study Of P|pes 1 results 1 ' . $excludeControl
            . 'The Study and Scor_ng of Dots.and-Dashes:Colons 1 results 1 ' . $excludeControl
            . 'The Study of "Important" Things 1 results 1 ' . $excludeControl
            . 'more…',
            $this->findCss($page, '#modal #facet-list-index')->getText()
        );
        $excludes = $page
            ->findAll('css', '#modal #facet-list-index .exclude');
        $this->assertEquals($exclusionActive ? $limit : 0, count($excludes));
        // sort by index again
        $this->clickCss($page, '[data-sort="count"]');
        $this->waitForPageLoad($page);
        $items = $page->findAll('css', '#modal #facet-list-count .js-facet-item');
        $this->assertEquals($limit * 2, count($items)); // maintain number of items
        // When exclusion is active, the result count is outside of the link tag:
        $expectedLinkText = $exclusionActive ? 'Weird IDs' : 'Weird IDs 9 results 9';
        $weirdIDs = $this->findAndAssertLink(
            $page->findById('modal'),
            $expectedLinkText
        );
        $this->assertEquals($expectedLinkText, $weirdIDs->getText());
        // apply US facet
        $weirdIDs->click();
        $this->waitForPageLoad($page);
    }

    /**
     * Test applying a facet to filter results (standard facet sidebar)
     *
     * @return void
     */
    public function testApplyFacet(): void
    {
        $page = $this->performSearch('building:weird_ids.mrc');

        // Confirm that we are NOT using the AJAX sidebar:
        $ajaxContainer = $page->findAll('css', '.side-facets-container-ajax');
        $this->assertCount(0, $ajaxContainer);

        // Now run the body of the test procedure:
        $this->facetApplyProcedure($page);
    }

    /**
     * Test applying a facet to filter results (deferred facet sidebar)
     *
     * @return void
     */
    public function testApplyFacetDeferred(): void
    {
        $this->changeConfigs(
            [
                'searches' => [
                    'General' => [
                        'default_side_recommend[]' => 'SideFacetsDeferred:Results:CheckboxFacets',
                    ],
                ],
            ]
        );
        $page = $this->performSearch('building:weird_ids.mrc');

        // Confirm that we ARE using the AJAX sidebar:
        $ajaxContainer = $page->findAll('css', '.side-facets-container-ajax');
        $this->assertCount(1, $ajaxContainer);

        // Now run the body of the test procedure:
        $this->facetApplyProcedure($page);
    }

    /**
     * Test expanding facets into the lightbox
     *
     * @return void
     */
    public function testFacetLightbox(): void
    {
        $limit = 4;
        $this->changeConfigs(
            [
                'facets' => [
                    'Results_Settings' => [
                        'showMoreInLightbox[*]' => true,
                        'lightboxLimit' => $limit,
                    ],
                ],
            ]
        );
        $page = $this->performSearch('building:weird_ids.mrc');
        // Open the genre facet
        $genreMore = $this->findCss($page, '#side-collapse-genre_facet .more-facets');
        $genreMore->click();
        $this->facetListProcedure($page, $limit);
        $genreMore->click();
        $this->clickCss($page, '#modal .js-facet-item.active');
        // facet removed
        $this->unFindCss($page, $this->activeFilterSelector);
    }

    /**
     * Test expanding facets into the lightbox
     *
     * @return void
     */
    public function testFacetLightboxMoreSetting(): void
    {
        $limit = 4;
        $this->changeConfigs(
            [
                'facets' => [
                    'Results_Settings' => [
                        'showMoreInLightbox[*]' => 'more',
                        'lightboxLimit' => $limit,
                    ],
                ],
            ]
        );
        $page = $this->performSearch('building:weird_ids.mrc');
        // Open the genre facet
        $this->clickCss($page, '#side-collapse-genre_facet .more-btn');
        $this->clickCss($page, '#side-collapse-genre_facet .all-facets');
        $this->facetListProcedure($page, $limit);
        $this->clickCss($page, '#side-collapse-genre_facet .more-btn');
        $this->clickCss($page, '#side-collapse-genre_facet .all-facets');
        $this->clickCss($page, '#modal .js-facet-item.active');
        // facet removed
        $this->unFindCss($page, $this->activeFilterSelector);
    }

    /**
     * Test that exclusion works properly deep in lightbox results.
     *
     * @return void
     */
    public function testFacetLightboxExclusion(): void
    {
        $limit = 4;
        $this->changeConfigs(
            [
                'facets' => [
                    'Results_Settings' => [
                        'showMoreInLightbox[*]' => true,
                        'lightboxLimit' => $limit,
                        'exclude' => '*',
                    ],
                ],
            ]
        );
        $page = $this->performSearch('building:weird_ids.mrc');
        // Open the genre facet
        $this->clickCss($page, '#side-collapse-genre_facet .more-facets');
        $this->facetListProcedure($page, $limit, true);
        $this->assertCount(1, $page->findAll('css', $this->activeFilterSelector));
    }

    /**
     * Test that filtering out facet values does not break lightbox pagination.
     *
     * @return void
     */
    public function testFilteredLightboxPagination(): void
    {
        $limit = 4;
        $this->changeConfigs(
            [
                'facets' => [
                    'HideFacetValue' => [
                        'genre_facet' => [
                            'Fiction',
                        ],
                    ],
                    'Results_Settings' => [
                        'showMoreInLightbox[*]' => true,
                        'lightboxLimit' => $limit,
                    ],
                ],
            ]
        );
        $page = $this->performSearch('building:weird_ids.mrc');
        // Open the genre facet
        $this->clickCss($page, '#side-collapse-genre_facet .more-facets');
        $this->waitForPageLoad($page);
        $items = $page->findAll('css', '#modal #facet-list-count .js-facet-item');
        $this->assertCount($limit - 1, $items); // (-1 is for the filtered value)
        // more
        $this->clickCss($page, '#modal .js-facet-next-page');
        $this->waitForPageLoad($page);
        $items = $page->findAll('css', '#modal #facet-list-count .js-facet-item');
        $this->assertEquals($limit * 2 - 1, count($items));
        $this->assertEquals(
            'Weird IDs 9 results 9 '
            . 'The Study Of P|pes 1 results 1 '
            . 'The Study and Scor_ng of Dots.and-Dashes:Colons 1 results 1 '
            . 'The Study of "Important" Things 1 results 1 '
            . 'The Study of %\'s? 1 results 1 '
            . 'The Study of +\'s? 1 results 1 '
            . 'The Study of @Twitter #test 1 results 1 '
            . 'more…',
            $this->findCss($page, '#modal #facet-list-count')->getText()
        );
    }

    /**
     * Support method to click a hierarchical facet.
     *
     * @param Element $page Mink page object
     *
     * @return void
     */
    protected function clickHierarchyFacet(Element $page): void
    {
        // Open second level:
        $this->clickCss($page, $this->facetExpandSelector);
        // Check results:
        $this->findCss($page, $this->facetExpandedSelector);
        // Click second level facet:
        $this->clickCss($page, $this->facetSecondLevelLinkSelector);
        // Check the active filter:
        $filter = $this->findCss($page, $this->activeFilterSelector);
        $label = $this->findCss($page, $this->activeFilterLabelSelector);
        $this->assertEquals('hierarchy:', $label->getText());
        $this->assertEquals('Remove Filter level1a/level2a', $filter->getText());
        // Check that the applied facet is displayed properly:
        $this->findCss($page, $this->facetSecondLevelActiveLinkSelector);
    }

    /**
     * Test that hierarchy facets work properly.
     *
     * @return void
     */
    public function testHierarchicalFacets(): void
    {
        $this->changeConfigs(
            [
                'facets' => [
                    'Results' => [
                        'hierarchical_facet_str_mv' => 'hierarchy',
                    ],
                    'SpecialFacets' => [
                        'hierarchical[]' => 'hierarchical_facet_str_mv',
                    ],
                ],
            ]
        );
        $page = $this->performSearch('building:"hierarchy.mrc"');
        $this->clickHierarchyFacet($page);
    }

    /**
     * Test that hierarchy facet exclusion works properly.
     *
     * @return void
     */
    public function testHierarchicalFacetExclude(): void
    {
        $this->changeConfigs(
            [
                'facets' => [
                    'Results' => [
                        'hierarchical_facet_str_mv' => 'hierarchy',
                    ],
                    'SpecialFacets' => [
                        'hierarchical[]' => 'hierarchical_facet_str_mv',
                    ],
                    'Results_Settings' => [
                        'exclude' => 'hierarchical_facet_str_mv',
                    ],
                ],
            ]
        );
        $extractCount = function ($str) {
            $parts = explode(',', $str);
            return $parts[0];
        };
        $page = $this->performSearch('building:"hierarchy.mrc"');
        $stats = $this->findCss($page, '.search-stats');
        $this->assertEquals(
            'Showing 1 - 10 results of 10 for search \'building:"hierarchy.mrc"\'',
            $extractCount($stats->getText())
        );
        $this->clickCss($page, $this->facetExpandSelector);
        $this->clickCss($page, $this->facetSecondLevelExcludeLinkSelector);
        $filter = $this->findCss($page, $this->activeFilterSelector);
        $label = $this->findCss($page, '.filters .filters-title');
        $this->assertEquals('hierarchy:', $label->getText());
        $this->assertEquals('Remove Filter level1a/level2a', $filter->getText());
        $stats = $this->findCss($page, '.search-stats');
        $this->assertEquals(
            'Showing 1 - 7 results of 7 for search \'building:"hierarchy.mrc"\'',
            $extractCount($stats->getText())
        );
    }

    /**
     * Test that we can persist uncollapsed state of collapsed facets
     *
     * @return void
     */
    public function testCollapseStatePersistence(): void
    {
        $this->changeConfigs(
            [
                'facets' => [
                    'Results' => [
                        'hierarchical_facet_str_mv' => 'hierarchy',
                    ],
                    'Results_Settings' => [
                        'collapsedFacets' => '*',
                    ],
                    'SpecialFacets' => [
                        'hierarchical[]' => 'hierarchical_facet_str_mv',
                    ],
                ],
            ]
        );
        $page = $this->performSearch('building:"hierarchy.mrc"');
        // Uncollapse format so we can check if it is still open after reload:
        $this->clickCss($page, '#side-panel-format .collapsed');
        // Uncollapse hierarchical facet so we can click it:
        $this->clickCss($page, '#side-panel-hierarchical_facet_str_mv .collapsed');
        $this->clickHierarchyFacet($page);

        // We have now reloaded the page. Let's toggle format off and on to confirm
        // that it was opened, and let's also toggle building on to confirm that
        // it was not alread opened.
        $this->clickCss($page, '#side-panel-format .title'); // off
        $this->waitForPageLoad($page);
        $this->clickCss($page, '#side-panel-format .collapsed'); // on
        $this->clickCss($page, '#side-panel-building .collapsed'); // on
    }

    /**
     * Assert that the filter used by these tests is still applied.
     *
     * @param Element $page Mink page object
     *
     * @return void
     */
    protected function assertFilterIsStillThere(Element $page): void
    {
        $filter = $this->findCss($page, $this->activeFilterSelector);
        $this->assertEquals('Remove Filter weird_ids.mrc', $filter->getText());
    }

    /**
     * Assert that no filters are applied.
     *
     * @param Element $page Mink page object
     *
     * @return void
     */
    protected function assertNoFilters(Element $page): void
    {
        $items = $page->findAll('css', $this->activeFilterSelector);
        $this->assertCount(0, $items);
    }

    /**
     * Assert that the "reset filters" button is not present.
     *
     * @param Element $page Mink page object
     *
     * @return void
     */
    protected function assertNoResetFiltersButton(Element $page): void
    {
        $reset = $page->findAll('css', '.reset-filters-btn');
        $this->assertCount(0, $reset);
    }

    /**
     * Test retain current filters default behavior
     *
     * @return void
     */
    public function testDefaultRetainFiltersBehavior(): void
    {
        $page = $this->getFilteredSearch();
        $this->assertFilterIsStillThere($page);
        // Re-click the search button and confirm that filters are still there
        $this->clickCss($page, '#searchForm .btn.btn-primary');
        $this->assertFilterIsStillThere($page);
        // Click the "reset filters" button and confirm that filters are gone and
        // that the button disappears when no longer needed.
        $this->clickCss($page, '.reset-filters-btn');
        $this->assertNoFilters($page);
        $this->assertNoResetFiltersButton($page);
    }

    /**
     * Test retaining filters on home page
     *
     * @return void
     */
    public function testRetainFiltersOnHomePageBehavior(): void
    {
        $page = $this->getFilteredSearch();
        // Back to home spage:
        $this->clickCss($page, '.navbar-brand');
        $this->assertFilterIsStillThere($page);
        // Remove the filter and confirm that filters are gone and that the
        // "reset filters" button disappears when no longer needed.
        $this->clickCss($page, $this->activeFilterSelector);
        $this->assertNoFilters($page);
        $this->assertNoResetFiltersButton($page);
    }

    /**
     * Test that filters carry over to selected records and are retained
     * from there.
     *
     * @return void
     */
    public function testFiltersOnRecord(): void
    {
        $page = $this->getFilteredSearch();
        $this->assertFilterIsStillThere($page);
        // Now click the first result:
        $this->clickCss($page, '.result-body a.title');
        // Confirm that filters are still visible:
        $this->assertFilterIsStillThere($page);
        // Re-click the search button...
        $this->clickCss($page, '#searchForm .btn.btn-primary');
        // Confirm that filter is STILL applied
        $this->assertFilterIsStillThere($page);
    }

    /**
     * Test "never retain filters" configurable behavior
     *
     * @return void
     */
    public function testNeverRetainFiltersBehavior(): void
    {
        $this->changeConfigs(
            [
                'searches' => [
                    'General' => ['retain_filters_by_default' => false],
                ],
            ]
        );
        $page = $this->getFilteredSearch();
        $this->assertFilterIsStillThere($page);
        // Confirm that there is no reset button:
        $this->assertNoResetFiltersButton($page);
        // Re-click the search button and confirm that filters go away
        $this->clickCss($page, '#searchForm .btn.btn-primary');
        $this->assertNoFilters($page);
    }

    /**
     * Test resetting to a default filter state
     *
     * @return void
     */
    public function testDefaultFiltersWithResetButton(): void
    {
        // Unlike the other tests, which use $this->getFilteredSearch() to set up
        // the weird_ids.mrc filter through a URL parameter, this test sets up the
        // filter as a default through the configuration.
        $this->changeConfigs(
            [
                'searches' => [
                    'General' => ['default_filters' => ['building:weird_ids.mrc']],
                ],
            ]
        );

        // Do a blank search to confirm that default filter is applied:
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Search/Results');
        $page = $session->getPage();
        $this->assertFilterIsStillThere($page);

        // Confirm that the reset button is NOT present:
        $this->assertNoResetFiltersButton($page);

        // Now manually clear the filter:
        $this->clickCss($page, '.filter-value');

        // Confirm that no filters are displayed:
        $this->assertNoFilters($page);

        // Now click the reset button to bring back the default:
        $this->clickCss($page, '.reset-filters-btn');
        $this->assertFilterIsStillThere($page);
        $this->assertNoResetFiltersButton($page);
    }

    /**
     * Test that OR facets work as expected.
     *
     * @return void
     */
    public function testOrFacets(): void
    {
        $this->changeConfigs(
            [
                'facets' => [
                    'Results_Settings' => ['orFacets' => 'building'],
                ],
            ]
        );

        // Do a blank search to determine initial counts
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Search/Results');
        $page = $session->getPage();

        // Extract information about the top two facets from the list:
        $facets = $this->findCss($page, '#side-collapse-building')->getText();
        $list = explode(' ', $facets);
        $firstFacet = array_shift($list);
        $firstFacetCount = array_shift($list);
        // Shift off the accessibility text
        array_shift($list);
        array_shift($list);
        $secondFacet = array_shift($list);
        $secondFacetCount = array_shift($list);

        // Facets should be ordered in descending order by count, and should have
        // non-zero counts...
        $this->assertTrue($firstFacetCount >= $secondFacetCount);
        $this->assertTrue($secondFacetCount > 0);

        // Clicking the second facet should restrict the result list:
        $this->clickCss(
            $page,
            '#side-collapse-building a[data-title="' . $secondFacet . '"]'
        );
        $this->assertStringContainsString(
            "Showing 1 - 20 results of $secondFacetCount",
            $this->findCss($page, '.search-header .search-stats')->getText()
        );

        // Now clicking the first facet should EXPAND the result list:
        $expectedTotal = $firstFacetCount + $secondFacetCount;
        $this->clickCss(
            $page,
            '#side-collapse-building a[data-title="' . $firstFacet . '"]'
        );
        $this->assertStringContainsString(
            "Showing 1 - 20 results of $expectedTotal",
            $this->findCss($page, '.search-header .search-stats')->getText()
        );
    }
}
