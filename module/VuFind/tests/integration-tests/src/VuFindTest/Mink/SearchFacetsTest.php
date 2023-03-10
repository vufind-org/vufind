<?php

/**
 * Mink search facet/filter functionality test class.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2011.
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

/**
 * Mink search facet/filter functionality test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 * @retry    4
 */
class SearchFacetsTest extends \VuFindTest\Integration\MinkTestCase
{
    /**
     * CSS selector for finding active filters
     *
     * @var string
     */
    protected $activeFilterSelector = '.active-filters.hidden-xs .filters .filter-value';

    /**
     * Get filtered search
     *
     * @return \Behat\Mink\Element\Element
     */
    protected function getFilteredSearch()
    {
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Search/Results?filter%5B%5D=building%3A"weird_ids.mrc"');
        return $session->getPage();
    }

    /**
     * Helper function for simple facet application test
     *
     * @param \Behat\Mink\Element\Element $page Mink page object
     *
     * @return void
     */
    protected function facetApplyProcedure($page)
    {
        // Confirm that we have 9 results and no filters to begin with:
        $time = $this->findCss($page, '.search-query-time');
        $stats = $this->findCss($page, '.search-stats');
        $this->assertEquals(
            "Showing 1 - 9 results of 9 for search 'building:weird_ids.mrc'" . $time->getText(),
            $stats->getText()
        );
        $items = $page->findAll('css', $this->activeFilterSelector);
        $this->assertEquals(0, count($items));

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
        $this->assertEquals(1, count($items));
    }

    /**
     * Helper function for facets lists
     *
     * @param \Behat\Mink\Element\Element $page            Mink page object
     * @param int                         $limit           Configured lightbox length
     * @param bool                        $exclusionActive Is facet exclusion on?
     *
     * @return void
     */
    protected function facetListProcedure($page, $limit, $exclusionActive = false)
    {
        $this->waitForPageLoad($page);
        $items = $page->findAll('css', '#modal #facet-list-count .js-facet-item');
        $this->assertEquals($limit, count($items));
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
        $this->assertEquals($limit, count($items)); // reset number of items
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
    public function testApplyFacet()
    {
        $page = $this->performSearch('building:weird_ids.mrc');

        // Confirm that we are NOT using the AJAX sidebar:
        $ajaxContainer = $page->findAll('css', '.side-facets-container-ajax');
        $this->assertEquals(0, count($ajaxContainer));

        // Now run the body of the test procedure:
        $this->facetApplyProcedure($page);
    }

    /**
     * Test applying a facet to filter results (deferred facet sidebar)
     *
     * @return void
     */
    public function testApplyFacetDeferred()
    {
        $this->changeConfigs(
            [
                'searches' => [
                    'General' => [
                        'default_side_recommend[]' => 'SideFacetsDeferred:Results:CheckboxFacets',
                    ]
                ]
            ]
        );
        $page = $this->performSearch('building:weird_ids.mrc');

        // Confirm that we ARE using the AJAX sidebar:
        $ajaxContainer = $page->findAll('css', '.side-facets-container-ajax');
        $this->assertEquals(1, count($ajaxContainer));

        // Now run the body of the test procedure:
        $this->facetApplyProcedure($page);
    }

    /**
     * Test expanding facets into the lightbox
     *
     * @return void
     */
    public function testFacetLightbox()
    {
        $limit = 4;
        $this->changeConfigs(
            [
                'facets' => [
                    'Results_Settings' => [
                        'showMoreInLightbox[*]' => true,
                        'lightboxLimit' => $limit
                    ]
                ]
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
    public function testFacetLightboxMoreSetting()
    {
        $limit = 4;
        $this->changeConfigs(
            [
                'facets' => [
                    'Results_Settings' => [
                        'showMoreInLightbox[*]' => 'more',
                        'lightboxLimit' => $limit
                    ]
                ]
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
    public function testFacetLightboxExclusion()
    {
        $limit = 4;
        $this->changeConfigs(
            [
                'facets' => [
                    'Results_Settings' => [
                        'showMoreInLightbox[*]' => true,
                        'lightboxLimit' => $limit,
                        'exclude' => '*',
                    ]
                ]
            ]
        );
        $page = $this->performSearch('building:weird_ids.mrc');
        // Open the genre facet
        $this->clickCss($page, '#side-collapse-genre_facet .more-facets');
        $this->facetListProcedure($page, $limit, true);
        $this->assertEquals(1, count($page->findAll('css', $this->activeFilterSelector)));
    }

    /**
     * Support method to click a hierarchical facet.
     *
     * @param \Behat\Mink\Element\Element $page Mink page object
     *
     * @return void
     */
    protected function clickHierarchyFacet($page)
    {
        $this->clickCss($page, '#j1_1.jstree-closed .jstree-icon');
        $this->findCss($page, '#j1_1.jstree-open .jstree-icon');
        $this->clickCss($page, '#j1_2 a');
        $filter = $this->findCss($page, $this->activeFilterSelector);
        $label = $this->findCss($page, '.filters .filters-title');
        $this->assertEquals('hierarchy:', $label->getText());
        $this->assertEquals('Remove Filter level1a/level2a', $filter->getText());
        $this->findCss($page, '#j1_2 .applied');
    }

    /**
     * Test that hierarchy facets work properly.
     *
     * @return void
     */
    public function testHierarchicalFacets()
    {
        $this->changeConfigs(
            [
                'facets' => [
                    'Results' => [
                        'hierarchical_facet_str_mv' => 'hierarchy'
                    ],
                    'SpecialFacets' => [
                        'hierarchical[]' => 'hierarchical_facet_str_mv'
                    ]
                ]
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
    public function testHierarchicalFacetExclude()
    {
        $this->changeConfigs(
            [
                'facets' => [
                    'Results' => [
                        'hierarchical_facet_str_mv' => 'hierarchy'
                    ],
                    'SpecialFacets' => [
                        'hierarchical[]' => 'hierarchical_facet_str_mv'
                    ],
                    'Results_Settings' => [
                        'exclude' => 'hierarchical_facet_str_mv',
                    ]
                ]
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
        $this->clickCss($page, '#j1_1.jstree-closed .jstree-icon');
        $this->findCss($page, '#j1_1.jstree-open .jstree-icon');
        $this->clickCss($page, '#j1_2 a.exclude');
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
    public function testCollapseStatePersistence()
    {
        $this->changeConfigs(
            [
                'facets' => [
                    'Results' => [
                        'hierarchical_facet_str_mv' => 'hierarchy'
                    ],
                    'Results_Settings' => [
                        'collapsedFacets' => '*'
                    ],
                    'SpecialFacets' => [
                        'hierarchical[]' => 'hierarchical_facet_str_mv'
                    ]
                ]
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
     * @param \Behat\Mink\Element\Element $page Mink page object
     *
     * @return void
     */
    protected function assertFilterIsStillThere($page)
    {
        $filter = $this->findCss($page, $this->activeFilterSelector);
        $this->assertEquals('Remove Filter weird_ids.mrc', $filter->getText());
    }

    /**
     * Assert that no filters are applied.
     *
     * @param \Behat\Mink\Element\Element $page Mink page object
     *
     * @return void
     */
    protected function assertNoFilters($page)
    {
        $items = $page->findAll('css', $this->activeFilterSelector);
        $this->assertEquals(0, count($items));
    }

    /**
     * Assert that the "reset filters" button is not present.
     *
     * @param \Behat\Mink\Element\Element $page Mink page object
     *
     * @return void
     */
    protected function assertNoResetFiltersButton($page)
    {
        $reset = $page->findAll('css', '.reset-filters-btn');
        $this->assertEquals(0, count($reset));
    }

    /**
     * Test retain current filters default behavior
     *
     * @return void
     */
    public function testDefaultRetainFiltersBehavior()
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
    public function testRetainFiltersOnHomePageBehavior()
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
    public function testFiltersOnRecord()
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
    public function testNeverRetainFiltersBehavior()
    {
        $this->changeConfigs(
            [
                'searches' => [
                    'General' => ['retain_filters_by_default' => false]
                ]
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
    public function testDefaultFiltersWithResetButton()
    {
        // Unlike the other tests, which use $this->getFilteredSearch() to set up
        // the weird_ids.mrc filter through a URL parameter, this test sets up the
        // filter as a default through the configuration.
        $this->changeConfigs(
            [
                'searches' => [
                    'General' => ['default_filters' => ['building:weird_ids.mrc']]
                ]
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
    public function testOrFacets()
    {
        $this->changeConfigs(
            [
                'facets' => [
                    'Results_Settings' => ['orFacets' => 'building']
                ]
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
