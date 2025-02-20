<?php

/**
 * Mink search facet/filter functionality test class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2011.
 * Copyright (C) The National Library of Finland 2023-2024.
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
use VuFindTest\Feature\SearchFacetFilterTrait;
use VuFindTest\Feature\SearchLimitTrait;
use VuFindTest\Feature\SearchSortTrait;

/**
 * Mink search facet/filter functionality test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class SearchFacetsTest extends \VuFindTest\Integration\MinkTestCase
{
    use SearchLimitTrait;
    use SearchSortTrait;
    use SearchFacetFilterTrait;

    /**
     * CSS selector for the genre facet "more" link.
     *
     * @var string
     */
    protected $genreMoreSelector = '#side-collapse-genre_facet .more-facets';

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
     * @param Element $page        Mink page object
     * @param array   $facets      Facets to apply (title and expected counts)
     * @param bool    $multiselect Use multi-facet selection?
     *
     * @return void
     */
    protected function facetApplyProcedure(Element $page, array $facets, bool $multiselect): void
    {
        // Confirm that we have 9 results and no filters to begin with:
        $this->assertStringStartsWith(
            'Showing 1 - 9 results of 9',
            $this->findCssAndGetText($page, '.search-stats')
        );
        $this->assertNoFilters($page);

        $active = 0;

        if ($multiselect) {
            $this->clickCss($page, '.js-user-selection-multi-filters');
        }
        foreach ($facets as $facet) {
            $title = $facet['title'];
            $count = $facet['count'];
            $resultCount = $facet['resultCount'];
            // Apply the facet (after making sure we picked the right link):
            $facetSelector = '#side-collapse-genre_facet a[data-title="' . $title . '"]';
            $this->assertEquals(
                "$title $count results $count",
                $this->getFacetTextByLinkSelector($page, $facetSelector)
            );
            $this->clickCss($page, $facetSelector);
            ++$active;

            if (!$multiselect) {
                // Check that when the page reloads, we have fewer results and a filter:
                $this->waitForPageLoad($page);
                $this->assertStringStartsWith(
                    "Showing 1 - $resultCount results of $resultCount",
                    $this->findCssAndGetText($page, '.search-stats')
                );
                $this->assertFilterCount($page, $active);
            }
        }
        if ($multiselect) {
            // Apply and check that we have the count indicated in the last facet to select:
            $facet = end($facets);
            $resultCount = $facet['resultCount'];
            $this->clickCss($page, '.js-apply-multi-facets-selection');
            $this->waitForPageLoad($page);
            $this->assertStringStartsWith(
                "Showing 1 - $resultCount results of $resultCount",
                $this->findCssAndGetText($page, '.search-stats')
            );
            $this->assertFilterCount($page, $active);
        }

        // Confirm that all selected facets show as active:
        foreach ($facets as $facet) {
            $title = $facet['title'];
            $activeFacetSelector = '#side-collapse-genre_facet .active a[data-title="' . $title . '"]';
            $this->findCss($page, $activeFacetSelector);
        }
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
        $this->assertFullListFacetCount($page, 'count', $limit, $exclusionActive);
        // more
        $this->clickCss($page, '#modal .js-facet-next-page');
        $this->waitForPageLoad($page);
        $this->assertFullListFacetCount($page, 'count', $limit * 2, $exclusionActive);

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
            $this->findCssAndGetText($page, '#modal #facet-list-count')
        );

        // sort by title
        $this->clickCss($page, '[data-sort="index"]');
        $this->waitForPageLoad($page);
        $this->assertFullListFacetCount($page, 'index', $limit, $exclusionActive);
        $this->assertEquals(
            'Fiction 7 results 7 ' . $excludeControl
            . 'The Study Of P|pes 1 results 1 ' . $excludeControl
            . 'The Study and Scor_ng of Dots.and-Dashes:Colons 1 results 1 ' . $excludeControl
            . 'The Study of "Important" Things 1 results 1 ' . $excludeControl
            . 'more…',
            $this->findCssAndGetText($page, '#modal #facet-list-index')
        );
        // sort by count again
        $this->clickCss($page, '[data-sort="count"]');
        $this->waitForPageLoad($page);
        // reload, resetting to just one page of results:
        $this->assertFullListFacetCount($page, 'count', $limit, $exclusionActive);
        // now back to title, to see if loading a second page works
        $this->clickCss($page, '[data-sort="index"]');
        $this->waitForPageLoad($page);
        $this->clickCss($page, '#modal #facet-list-index .js-facet-next-page');
        $this->waitForPageLoad($page);
        $this->assertFullListFacetCount($page, 'index', $limit * 2, $exclusionActive);
        $this->assertEquals(
            'Fiction 7 results 7 ' . $excludeControl
            . 'The Study Of P|pes 1 results 1 ' . $excludeControl
            . 'The Study and Scor_ng of Dots.and-Dashes:Colons 1 results 1 ' . $excludeControl
            . 'The Study of "Important" Things 1 results 1 ' . $excludeControl
            . 'The Study of %\'s? 1 results 1 ' . $excludeControl
            . 'The Study of +\'s? 1 results 1 ' . $excludeControl
            . 'The Study of @Twitter #test 1 results 1 ' . $excludeControl
            . 'The Study of Back S\ashes 1 results 1 ' . $excludeControl
            . 'more…',
            $this->findCssAndGetText($page, '#modal #facet-list-index')
        );
        // back to count one last time...
        $this->clickCss($page, '[data-sort="count"]');
        $this->waitForPageLoad($page);
        $expectedLinkText = 'Weird IDs 9 results 9';
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
     * Data provider for testApplyFacet
     *
     * @return array
     */
    public static function applyFacetProvider(): array
    {
        $andFacets = [
            [
                'title' => 'Fiction',
                'count' => 7,
                'resultCount' => 7,
            ],
        ];

        $orFacets = [
            [
                'title' => 'Fiction',
                'count' => 7,
                'resultCount' => 7,
            ],
            [
                'title' => 'The Study Of P|pes',
                'count' => 1,
                'resultCount' => 8,
            ],
            [
                'title' => 'Weird IDs',
                'count' => 9,
                'resultCount' => 9,
            ],
        ];

        return [
            'non-deferred AND facets' => [
                false,
                false,
                $andFacets,
                false,
            ],
            'deferred AND facets' => [
                true,
                false,
                $andFacets,
                false,
            ],
            'non-deferred OR facets' => [
                false,
                true,
                $orFacets,
                false,
            ],
            'deferred OR facets' => [
                true,
                true,
                $orFacets,
                false,
            ],
            'multiselect non-deferred AND facets' => [
                false,
                false,
                $andFacets,
                true,
            ],
            'multiselect deferred AND facets' => [
                true,
                false,
                $andFacets,
                true,
            ],
            'multiselect non-deferred OR facets' => [
                false,
                true,
                $orFacets,
                true,
            ],
            'multiselect deferred OR facets' => [
                true,
                true,
                $orFacets,
                true,
            ],
        ];
    }

    /**
     * Flip-flop the language to cause URL rewrites (useful for testing handling of
     * arrays in query parameters).
     *
     * @param Element $page Current page object
     *
     * @return void
     */
    protected function flipflopLanguage(Element $page): void
    {
        // Flip to German:
        $this->clickCss($page, '.language.dropdown');
        $this->clickCss($page, '.language.dropdown li:not(.active) a');
        $this->waitForPageLoad($page);
        // Flip back to English:
        $this->clickCss($page, '.language.dropdown');
        $this->clickCss($page, '.language.dropdown li:not(.active) a');
        $this->waitForPageLoad($page);
    }

    /**
     * Test applying a facet to filter results (deferred facet sidebar)
     *
     * @param bool  $deferred    Are deferred facets enabled?
     * @param bool  $orFacets    Are OR facets enabled?
     * @param array $facets      Facets to apply
     * @param bool  $multiselect Use multiselection?
     *
     * @dataProvider applyFacetProvider
     *
     * @return void
     */
    public function testApplyFacet(bool $deferred, bool $orFacets, array $facets, bool $multiselect): void
    {
        $this->changeConfigs(
            [
                'searches' => [
                    'General' => [
                        'default_side_recommend[]'
                            => ($deferred ? 'SideFacetsDeferred' : 'SideFacets') . ':Results:CheckboxFacets',
                        'limit_options' => '20,40',
                    ],
                ],
                'facets' => [
                    'Results_Settings' => [
                        'orFacets' => $orFacets ? '*' : 'false',
                        'collapsedFacets' => '*',
                        'multiFacetsSelection' => $multiselect,
                    ],
                ],
            ]
        );
        $page = $this->performSearch('building:weird_ids.mrc');
        $this->sortResults($page, 'title');
        $this->waitForPageLoad($page);
        $this->setResultLimit($page, 40);
        $this->waitForPageLoad($page);

        // Confirm that we ARE using the correct sidebar type:
        $ajaxContainer = $page->findAll('css', '.side-facets-container-ajax');
        $this->assertCount($deferred ? 1 : 0, $ajaxContainer);

        // Uncollapse the genre facet to load its contents:
        $this->clickCss($page, '#side-panel-genre_facet .collapsed');

        // Now run the body of the test procedure:
        $this->facetApplyProcedure($page, $facets, $multiselect);

        // Verify that sort order is still correct:
        $this->assertSelectedSort($page, 'title');

        // Verify that limit is still correct:
        $this->assertLimitControl($page, [20, 40], 40);
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
        $this->clickCss($page, $this->genreMoreSelector);
        $this->facetListProcedure($page, $limit);
        $this->clickCss($page, $this->genreMoreSelector);
        $this->clickCss($page, '#modal .js-facet-item.active');
        // facet removed
        $this->unFindCss($page, $this->activeFilterSelector);
    }

    /**
     * Test filtering and unfiltering the expanded facets in the lightbox
     *
     * @return void
     */
    public function testFacetLightboxFilteringAndClearing(): void
    {
        $this->changeConfigs(
            [
                'facets' => [
                    'Results_Settings' => [
                        'showMoreInLightbox[*]' => true,
                    ],
                ],
            ]
        );
        $page = $this->performSearch('building:weird_ids.mrc');
        // Open the genre facet
        $this->clickCss($page, $this->genreMoreSelector);
        $this->waitForPageLoad($page);
        // Filter to values containing the letter "d" -- this should eliminate "Fiction"
        // from the list:
        $this->findCssAndSetValue($page, '#modal input[data-name="contains"]', 'd');
        $this->assertEqualsWithTimeout(
            'Weird IDs 9 results 9 '
            . 'The Study Of P|pes 1 results 1 '
            . 'The Study and Scor_ng of Dots.and-Dashes:Colons 1 results 1 '
            . 'The Study of "Important" Things 1 results 1 '
            . 'The Study of %\'s? 1 results 1 '
            . 'The Study of +\'s? 1 results 1 '
            . 'The Study of @Twitter #test 1 results 1 '
            . 'The Study of Back S\ashes 1 results 1 '
            . 'The Study of Cold Hard Ca$h 1 results 1 '
            . 'The Study of Forward S/ashes 1 results 1 '
            . 'The Study of Things & Combinations <HTML Edition> 1 results 1',
            function () use ($page) {
                return $this->findCssAndGetText($page, '#modal #facet-list-count');
            }
        );

        // now clear the filter
        $this->clickCss($page, '#modal button[type="reset"]');
        $this->waitForPageLoad($page);
        $this->assertEquals(
            'Weird IDs 9 results 9 '
            . 'Fiction 7 results 7 '
            . 'The Study Of P|pes 1 results 1 '
            . 'The Study and Scor_ng of Dots.and-Dashes:Colons 1 results 1 '
            . 'The Study of "Important" Things 1 results 1 '
            . 'The Study of %\'s? 1 results 1 '
            . 'The Study of +\'s? 1 results 1 '
            . 'The Study of @Twitter #test 1 results 1 '
            . 'The Study of Back S\ashes 1 results 1 '
            . 'The Study of Cold Hard Ca$h 1 results 1 '
            . 'The Study of Forward S/ashes 1 results 1 '
            . 'The Study of Things & Combinations <HTML Edition> 1 results 1',
            $this->findCssAndGetText($page, '#modal #facet-list-count')
        );
    }

    /**
     * Test filtering and sorting the expanded facets in the lightbox
     *
     * @return void
     */
    public function testFacetLightboxFilteringAndSorting(): void
    {
        $this->changeConfigs(
            [
                'facets' => [
                    'Results_Settings' => [
                        'showMoreInLightbox[*]' => true,
                    ],
                ],
            ]
        );
        $page = $this->performSearch('building:weird_ids.mrc');
        // Open the genre facet
        $this->clickCss($page, $this->genreMoreSelector);
        $this->waitForPageLoad($page);
        // Filter to values containing the letter "d" -- this should eliminate "Fiction"
        // from the list:
        $this->findCssAndSetValue($page, '#modal input[data-name="contains"]', 'd');
        $this->assertEqualsWithTimeout(
            'Weird IDs 9 results 9 '
            . 'The Study Of P|pes 1 results 1 '
            . 'The Study and Scor_ng of Dots.and-Dashes:Colons 1 results 1 '
            . 'The Study of "Important" Things 1 results 1 '
            . 'The Study of %\'s? 1 results 1 '
            . 'The Study of +\'s? 1 results 1 '
            . 'The Study of @Twitter #test 1 results 1 '
            . 'The Study of Back S\ashes 1 results 1 '
            . 'The Study of Cold Hard Ca$h 1 results 1 '
            . 'The Study of Forward S/ashes 1 results 1 '
            . 'The Study of Things & Combinations <HTML Edition> 1 results 1',
            function () use ($page) {
                return $this->findCssAndGetText($page, '#modal #facet-list-count');
            }
        );

        // sort by title
        $this->clickCss($page, '[data-sort="index"]');
        $this->assertEqualsWithTimeout(
            'The Study Of P|pes 1 results 1 '
            . 'The Study and Scor_ng of Dots.and-Dashes:Colons 1 results 1 '
            . 'The Study of "Important" Things 1 results 1 '
            . 'The Study of %\'s? 1 results 1 '
            . 'The Study of +\'s? 1 results 1 '
            . 'The Study of @Twitter #test 1 results 1 '
            . 'The Study of Back S\ashes 1 results 1 '
            . 'The Study of Cold Hard Ca$h 1 results 1 '
            . 'The Study of Forward S/ashes 1 results 1 '
            . 'The Study of Things & Combinations <HTML Edition> 1 results 1 '
            . 'Weird IDs 9 results 9',
            function () use ($page) {
                return $this->findCssAndGetText($page, '#modal #facet-list-index');
            }
        );

        // now clear the filter
        $this->clickCss($page, '#modal button[type="reset"]');
        $this->assertEqualsWithTimeout(
            'Fiction 7 results 7 '
            . 'The Study Of P|pes 1 results 1 '
            . 'The Study and Scor_ng of Dots.and-Dashes:Colons 1 results 1 '
            . 'The Study of "Important" Things 1 results 1 '
            . 'The Study of %\'s? 1 results 1 '
            . 'The Study of +\'s? 1 results 1 '
            . 'The Study of @Twitter #test 1 results 1 '
            . 'The Study of Back S\ashes 1 results 1 '
            . 'The Study of Cold Hard Ca$h 1 results 1 '
            . 'The Study of Forward S/ashes 1 results 1 '
            . 'The Study of Things & Combinations <HTML Edition> 1 results 1 '
            . 'Weird IDs 9 results 9',
            function () use ($page) {
                return $this->findCssAndGetText($page, '#modal #facet-list-index');
            }
        );

        // ...and restore the original sort
        $this->clickCss($page, '[data-sort="count"]');
        $this->assertEqualsWithTimeout(
            'Weird IDs 9 results 9 '
            . 'Fiction 7 results 7 '
            . 'The Study Of P|pes 1 results 1 '
            . 'The Study and Scor_ng of Dots.and-Dashes:Colons 1 results 1 '
            . 'The Study of "Important" Things 1 results 1 '
            . 'The Study of %\'s? 1 results 1 '
            . 'The Study of +\'s? 1 results 1 '
            . 'The Study of @Twitter #test 1 results 1 '
            . 'The Study of Back S\ashes 1 results 1 '
            . 'The Study of Cold Hard Ca$h 1 results 1 '
            . 'The Study of Forward S/ashes 1 results 1 '
            . 'The Study of Things & Combinations <HTML Edition> 1 results 1',
            function () use ($page) {
                return $this->findCssAndGetText($page, '#modal #facet-list-count');
            }
        );
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
        $this->clickCss($page, $this->genreMoreSelector);
        $this->facetListProcedure($page, $limit, true);
        $this->assertFilterCount($page, 1);
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
        $this->clickCss($page, $this->genreMoreSelector);
        $this->waitForPageLoad($page);
        $items = $page->findAll('css', '#modal #facet-list-count .js-facet-item');
        $this->assertCount($limit - 1, $items); // (-1 is for the filtered value)
        // more
        $this->clickCss($page, '#modal .js-facet-next-page');
        $this->waitForPageLoad($page);
        $items = $page->findAll('css', '#modal #facet-list-count .js-facet-item');
        $this->assertCount($limit * 2 - 1, $items);
        $this->assertEquals(
            'Weird IDs 9 results 9 '
            . 'The Study Of P|pes 1 results 1 '
            . 'The Study and Scor_ng of Dots.and-Dashes:Colons 1 results 1 '
            . 'The Study of "Important" Things 1 results 1 '
            . 'The Study of %\'s? 1 results 1 '
            . 'The Study of +\'s? 1 results 1 '
            . 'The Study of @Twitter #test 1 results 1 '
            . 'more…',
            $this->findCssAndGetText($page, '#modal #facet-list-count')
        );
    }

    /**
     * Test multiselection in facet lightbox
     *
     * @return void
     */
    public function testFacetLightboxMultiselect(): void
    {
        $this->changeConfigs(
            [
                'facets' => [
                    'Results_Settings' => [
                        'showMoreInLightbox[*]' => true,
                        'lightboxLimit' => 10,
                        'multiFacetsSelection' => true,
                        'exclude' => '*',
                    ],
                ],
            ]
        );
        $page = $this->performSearch('building:weird_ids.mrc');
        // Open the genre facet
        $this->clickCss($page, $this->genreMoreSelector);
        $modal = $this->findCss($page, '#modal');
        $this->assertIsObject($modal);
        // Check for multi-filter controls:
        $this->clickCss($modal, '.js-user-selection-multi-filters');
        $this->findCss($modal, '.js-full-facet-list.multi-facet-selection-active');
        $this->findCss($modal, '.js-apply-multi-facets-selection');
        // Change order and check for multi-filter controls:
        $this->clickCss($modal, '[data-sort="index"]');
        $this->findCss($modal, '.js-full-facet-list.multi-facet-selection-active');
        $this->findCss($modal, '.js-apply-multi-facets-selection');
        // Load more:
        $this->clickCss($modal, '.js-facet-next-page');
        // Select and exclude a facet item:
        $this->clickCss($modal, 'a[data-title="Weird IDs"]');
        $this->clickCss($this->findCss($modal, 'a[data-title="Fiction"]')->getParent(), 'a.exclude');
        $this->clickCss($modal, '.js-apply-multi-facets-selection');
        $this->waitForPageLoad($page);
        $this->assertFilterCount($page, 2);
        $this->assertEquals(
            'Genre: NOT Remove Filter Fiction AND Remove Filter Weird IDs',
            $this->findCss($page, $this->activeFilterListSelector)->getText()
        );
    }

    /**
     * Support method to click a hierarchical facet.
     *
     * @param Element $page Mink page object
     *
     * @return void
     */
    protected function clickHierarchicalFacet(Element $page): void
    {
        // Open second level:
        $this->clickCss($page, $this->facetExpandSelector);
        // Check results:
        $this->findCss($page, $this->facetExpandedSelector);
        // Click second level facet:
        $this->clickCss($page, $this->facetSecondLevelLinkSelector);
        // Check the active filter:
        $this->assertAppliedFilters($page, ['hierarchy:level1a/level2a']);
        // Check that the applied facet is displayed properly:
        $this->findCss($page, $this->facetSecondLevelActiveLinkSelector);
    }

    /**
     * Test that hierarchical facets work properly.
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
        // Do a search and verify that sort order is maintained:
        $page = $this->performSearch('building:"hierarchy.mrc"');
        $this->sortResults($page, 'title');
        $this->waitForPageLoad($page);
        $this->clickHierarchicalFacet($page);
        $this->assertSelectedSort($page, 'title');
        // Remove the filter:
        $this->clickCss($page, $this->activeFilterSelector);
        $this->waitForPageLoad($page);
        $this->assertSelectedSort($page, 'title');
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
        $this->assertEquals(
            'Showing 1 - 10 results of 10',
            $extractCount($this->findCssAndGetText($page, '.search-stats'))
        );
        $this->clickCss($page, $this->facetExpandSelector);
        $this->clickCss($page, $this->facetSecondLevelExcludeLinkSelector);
        $this->assertAppliedFilters($page, ['hierarchy:level1a/level2a']);
        $this->assertEquals(
            'Showing 1 - 7 results of 7',
            $extractCount($this->findCssAndGetText($page, '.search-stats'))
        );
    }

    /**
     * Data provider for testHierarchicalFacetSort
     *
     * @return array
     */
    public static function hierarchicalFacetSortProvider(): array
    {
        return [
            [
                null,
                'count',
            ],
            [
                'count',
                'count',
            ],
            [
                'top',
                'top',
            ],
            [
                'all',
                'all',
            ],
        ];
    }

    /**
     * Test that hierarchical facet sort options work properly.
     *
     * @param ?string $sort         Sort option
     * @param string  $expectedSort Expected sort order of facet hierarchy
     *
     * @dataProvider hierarchicalFacetSortProvider
     *
     * @return void
     */
    public function testHierarchicalFacetSort(?string $sort, string $expectedSort): void
    {
        $facetConfig = [
            'Results' => [
                'hierarchical_facet_str_mv' => 'hierarchy',
            ],
            'SpecialFacets' => [
                'hierarchical[]' => 'hierarchical_facet_str_mv',
            ],
            'Advanced_Settings' => [
                'translated_facets[]' => 'hierarchical_facet_str_mv:Facets',
            ],
        ];
        if (null !== $sort) {
            $facetConfig['SpecialFacets']['hierarchicalFacetSortOptions[hierarchical_facet_str_mv]'] = $sort;
        }
        $this->changeConfigs(
            [
                'facets' => $facetConfig,
            ]
        );
        $page = $this->performSearch('building:"hierarchy.mrc"');

        $expected = $this->getExpectedHierarchicalFacetTreeItems($expectedSort);
        $actual = $this->getHierarchicalFacetTreeItems($page, '#side-collapse-hierarchical_facet_str_mv');
        $this->assertSame($expected, $actual);
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
        $this->clickHierarchicalFacet($page);

        // We have now reloaded the page. Let's toggle format off and on to confirm
        // that it was opened, and let's also toggle building on to confirm that
        // it was not already opened.
        $this->clickCss($page, '#side-panel-format .title'); // off
        $this->waitForPageLoad($page);
        $this->clickCss($page, '#side-panel-format .collapsed'); // on
        $this->clickCss($page, '#side-panel-building .collapsed'); // on
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
        $this->waitForPageLoad($page);
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
     * Test disabled "always display reset filters" configurable behavior
     *
     * @return void
     */
    public function testDisabledResetFiltersBehavior()
    {
        $this->changeConfigs(
            [
                'searches' => [
                    'General' => [
                        'retain_filters_by_default' => false,
                        'always_display_reset_filters' => false,
                    ],
                ],
            ]
        );
        $page = $this->getFilteredSearch();
        $this->assertFilterIsStillThere($page);
        // Confirm that there is no reset button:
        $this->assertNoResetFiltersButton($page);
    }

    /**
     * Test enabled "always display reset filters" configurable behavior
     *
     * @return void
     */
    public function testEnabledResetFiltersBehavior()
    {
        $this->changeConfigs(
            [
                'searches' => [
                    'General' => [
                        'retain_filters_by_default' => false,
                        'always_display_reset_filters' => true,
                    ],
                ],
            ]
        );
        $page = $this->getFilteredSearch();
        $this->assertFilterIsStillThere($page);
        // Confirm that there is a reset button:
        $this->assertResetFiltersButton($page);
        // Reset filters:
        $this->clickCss($page, '.reset-filters-btn');
        // Confirm that there is no reset button:
        $this->assertNoResetFiltersButton($page);
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
        $facets = $this->findCssAndGetText($page, '#side-collapse-building');
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
            $this->findCssAndGetText($page, '.search-header .search-stats')
        );

        // Now clicking the first facet should EXPAND the result list:
        $expectedTotal = $firstFacetCount + $secondFacetCount;
        $this->clickCss(
            $page,
            '#side-collapse-building a[data-title="' . $firstFacet . '"]'
        );
        $this->assertStringContainsString(
            "Showing 1 - 20 results of $expectedTotal",
            $this->findCssAndGetText($page, '.search-header .search-stats')
        );
    }

    /**
     * Data provider for testCheckboxFacets
     *
     * @return array
     */
    public static function checkboxFacetSelectionProvider(): array
    {
        $result = [];
        foreach ([false, true] as $selectMulti) {
            foreach ([false, true] as $unselectMulti) {
                $params = '(' . ($selectMulti ? 'multi' : 'single') . '/' . ($unselectMulti ? 'multi' : 'single') . ')';
                $result["select one $params"] = [
                    ['Books'],
                    8,
                    $selectMulti,
                    $unselectMulti,
                ];
                $name = "select two $params";
                $result[$name] = [
                    ['Books', 'Fiction'],
                    7,
                    $selectMulti,
                    $unselectMulti,
                ];
            }
        }
        return $result;
    }

    /**
     * Test checkbox facet selection
     *
     * @param array $checkFacets   Facet checkboxes to check
     * @param int   $expectedCount Expected result count
     * @param bool  $selectMulti   Select multiple?
     * @param bool  $unselectMulti Unselect multiple?
     *
     * @dataProvider checkboxFacetSelectionProvider
     *
     * @return void
     */
    public function testCheckboxFacetSelection(
        array $checkFacets,
        int $expectedCount,
        bool $selectMulti,
        bool $unselectMulti
    ): void {
        $this->changeConfigs(
            [
                'facets' => [
                    'Results_Settings' => [
                        'multiFacetsSelection' => $selectMulti || $unselectMulti,
                    ],
                    'CheckboxFacets' => [
                        'format:Book' => 'Books',
                        'genre_facet:Fiction' => 'Fiction',
                    ],
                ],
            ]
        );

        $page = $this->performSearch('building:weird_ids.mrc OR building:journals.mrc');
        $sidebar = $this->findCss($page, '.sidebar');
        $checkboxFilters = $this->findCss($sidebar, '.checkbox-filters');

        // Check all facets:
        if ($selectMulti) {
            $this->clickCss($sidebar, '.js-user-selection-multi-filters');
        }
        foreach ($checkFacets as $facet) {
            $link = $this->findAndAssertLink($checkboxFilters, $facet);
            $link->click();
            if (!$selectMulti) {
                $this->waitForPageLoad($page);
            }
        }
        if ($selectMulti) {
            $this->clickCss($sidebar, '.js-apply-multi-facets-selection');
            $this->waitForPageLoad($page);
        }
        $this->assertStringContainsString(
            "Showing 1 - $expectedCount results",
            $this->findCssAndGetText($page, '.search-header .search-stats')
        );

        // Uncheck all facets:
        if ($unselectMulti) {
            $this->clickCss($sidebar, '.js-user-selection-multi-filters');
        }
        foreach ($checkFacets as $facet) {
            $link = $this->findAndAssertLink($checkboxFilters, $facet);
            $link->click();
            if (!$unselectMulti) {
                $this->waitForPageLoad($page);
            }
        }
        if ($unselectMulti) {
            $this->clickCss($sidebar, '.js-apply-multi-facets-selection');
            $this->waitForPageLoad($page);
        }
        $this->assertStringContainsString(
            'Showing 1 - 19 results',
            $this->findCssAndGetText($page, '.search-header .search-stats')
        );
    }

    /**
     * Data provider for testRangeFacets
     *
     * @return array
     */
    public static function rangeFacetsProvider(): array
    {
        return [
            [false],
            [true],
        ];
    }

    /**
     * Test range facets
     *
     * @param bool $multiselection Use multi-facet selection?
     *
     * @dataProvider rangeFacetsProvider
     *
     * @return void
     */
    public function testRangeFacets(bool $multiselection): void
    {
        $this->changeConfigs(
            [
                'facets' => [
                    'Results_Settings' => [
                        'multiFacetsSelection' => $multiselection,
                    ],
                    'CheckboxFacets' => [
                        'format:Book' => 'Books',
                    ],
                ],
            ]
        );

        $page = $this->performSearch('building:weird_ids.mrc');
        $sidebar = $this->findCss($page, '.sidebar');

        // Filter by date range and checkbox filter:
        $checkboxFilters = $this->findCss($sidebar, '.checkbox-filters');
        if ($multiselection) {
            $this->clickCss($sidebar, '.js-user-selection-multi-filters');
            $this->clickCss($checkboxFilters, 'a.checkbox-filter');
        } else {
            $this->clickCss($checkboxFilters, 'a.checkbox-filter');
            $this->waitForPageLoad($page);
        }
        $this->applyRangeFacet($page, 'publishDate', '2000', '', $multiselection);

        // Verify that we have two filters:
        $this->assertAppliedFilters($page, [':Books', 'Year of Publication:2000 - *']);

        // Change date range filter and check results:
        $this->applyRangeFacet($page, 'publishDate', null, '2001', $multiselection);
        $this->assertAppliedFilters($page, [':Books', 'Year of Publication:2000 - 2001']);

        // Change date range filter again and check results:
        $this->applyRangeFacet($page, 'publishDate', '2001', '2007', $multiselection);
        $this->assertAppliedFilters($page, [':Books', 'Year of Publication:2001 - 2007']);

        // Remove dates in range filter and check results:
        $this->applyRangeFacet($page, 'publishDate', '', '', $multiselection);
        $this->assertAppliedFilters($page, [':Books']);

        // Add date range filter again and check results:
        $this->applyRangeFacet($page, 'publishDate', '2001', '2007', $multiselection);
        $this->assertAppliedFilters($page, [':Books', 'Year of Publication:2001 - 2007']);

        if ($multiselection) {
            // Apply another facet and change date range at the same time:
            $this->clickCss($sidebar, '.js-user-selection-multi-filters');
            $this->clickCss($page, '#side-collapse-institution a[data-title="MyInstitution"]');
            $this->applyRangeFacet($page, 'publishDate', '2001', '2010', $multiselection);
            $this->assertAppliedFilters(
                $page,
                [':Books', 'Institution:MyInstitution', 'Year of Publication:2001 - 2010']
            );

            // Remove all filters and check results:
            $this->clickCss($sidebar, '.js-user-selection-multi-filters');
            $this->clickCss($checkboxFilters, 'a.checkbox-filter');
            $this->clickCss($page, '#side-collapse-institution a[data-title="MyInstitution"]');
            $this->applyRangeFacet($page, 'publishDate', '', '', true);
            $this->assertNoFilters($page);
        }
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
        $this->assertAppliedFilters($page, ['Library:weird_ids.mrc']);
    }

    /**
     * Assert that the "reset filters" button is present.
     *
     * @param \Behat\Mink\Element\Element $page Mink page object
     *
     * @return void
     */
    protected function assertResetFiltersButton($page)
    {
        $reset = $page->findAll('css', '.reset-filters-btn');
        // The toggle bar has its own reset button, so we should have 2:
        $this->assertCount(2, $reset);
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
     * Data provider for testMultiSelectOnAdvancedSearch()
     *
     * @return array[]
     */
    public static function multiSelectOnAdvancedSearchProvider(): array
    {
        return [
            'with language switch / with checkbox' => [true, true],
            'without language switch / with checkbox' => [false, true],
            'with language switch / without checkbox' => [true, false],
            'without language switch / without checkbox' => [false, false],
        ];
    }

    /**
     * Test applying multi-facet selection to advanced search results, with or without changing the
     * language setting first and/or including a pre-existing checkbox filter.
     *
     * @param bool $changeLanguage  Should we change the language before applying the facets?
     * @param bool $includeCheckbox Should we apply a checkbox prior to multi-selection?
     *
     * @dataProvider multiSelectOnAdvancedSearchProvider
     *
     * @return void
     */
    public function testMultiSelectOnAdvancedSearch(bool $changeLanguage, bool $includeCheckbox): void
    {
        $facets = [
            'Results_Settings' => [
                'multiFacetsSelection' => true,
            ],
        ];
        if ($includeCheckbox) {
            // Create a pointless checkbox filter that will not impact the result set size
            // (we're just testing that it applies to the URL correctly):
            $facets['CheckboxFacets']['title:*'] = 'Has Title';
        }
        $this->changeConfigs(compact('facets'));
        $path = '/Search/Advanced';
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . $path);
        $page = $session->getPage();
        $this->waitForPageLoad($page);
        $this->findCssAndSetValue($page, '#search_lookfor0_0', 'test');
        $this->findCssAndSetValue($page, '#search_lookfor0_1', 'history');
        $this->findCss($page, '[type=submit]')->press();

        if ($includeCheckbox) {
            $link = $this->findAndAssertLink($page, 'Has Title');
            $link->click();
            $this->waitForPageLoad($page);
        }

        if ($changeLanguage) {
            $this->flipflopLanguage($page);
        }

        // Activate the first two facet values (and the checkbox filter, if requested):
        $this->clickCss($page, '.js-user-selection-multi-filters');
        $this->clickCss($page, '.facet__list__item a');
        $this->clickCss($page, '.facet__list__item a', index: 1);
        $this->clickCss($page, '.js-apply-multi-facets-selection');

        // A past bug would cause search terms to get duplicated after facets
        // were applied; make sure the search remains as expected!
        $this->assertEquals(
            '(All Fields:test AND All Fields:history)',
            $this->findCssAndGetText($page, '.adv_search_terms strong')
        );

        // Make sure we have the expected number of filters applied on screen and in the URL query:
        $this->assertCount(2, $page->findAll('css', '.facet.active'));
        $this->assertCount($includeCheckbox ? 1 : 0, $page->findAll('css', '.checkbox-filter [data-checked="true"]'));
        $query = parse_url($session->getCurrentUrl(), PHP_URL_QUERY);
        parse_str($query, $queryArray);
        $expectedFilterCount = $includeCheckbox ? 3 : 2;
        $this->assertCount($expectedFilterCount, $queryArray['filter']);

        // If configured, flip-flop language again to potentially modify filter params:
        if ($changeLanguage) {
            $this->flipflopLanguage($page);
        }

        // Let's also confirm that we can now remove the filters:
        $this->clickCss($page, '.js-user-selection-multi-filters');
        $this->clickCss($page, '.facet.active');
        $this->clickCss($page, '.facet.active');
        $this->clickCss($page, '.js-apply-multi-facets-selection');

        $this->assertCount(0, $page->findAll('css', '.facet.active'));
    }

    /**
     * Test that filters applied during search show up on the record page and can be removed there.
     *
     * @return void
     */
    public function testFilterClearingOnRecordPage(): void
    {
        // Start with a search with multiple filters applied:
        $path = '/Search/Results'
            . '?filter[]=building%3A"geo.mrc"'
            . '&filter[]=format%3A"Book"'
            . '&filter[]=author_facet%3A"Erickson%2C+Joette"&type=AllFields';
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . $path);
        $page = $session->getPage();
        $this->assertCount(3, $page->findAll('css', '.facet.active'));
        $this->clickCss($page, '#result0 a.title');
        $this->waitForPageLoad($page);

        // Make sure the active filters show up:
        $filterArea = $this->findCss($page, '.active-filters');
        $filters = $filterArea->findAll('css', '.filter-value');
        $this->assertCount(3, $filters);

        // Save the current URL so we can return to it:
        $urlWithSid = $session->getCurrentUrl();

        // Remove the first filter:
        $filters[0]->click();
        $this->waitForPageLoad($page);

        // There should now be fewer filters:
        $filters = $filterArea->findAll('css', '.filter-value');
        $this->assertCount(2, $filters);

        // Now submit a new search:
        $this->clickCss($page, '#searchForm .btn-primary');
        $this->waitForPageLoad($page);
        $this->assertCount(2, $page->findAll('css', '.facet.active'));

        // Now go back to the original record page with the SID in the URL and
        // confirm the return of the filters:
        $session->visit($urlWithSid);
        $filters = $filterArea->findAll('css', '.filter-value');
        $this->assertCount(3, $filters);

        // Remove the second filter:
        $filters[1]->click();
        $this->waitForPageLoad($page);

        // There should now be fewer filters:
        $filters = $filterArea->findAll('css', '.filter-value');
        $this->assertCount(2, $filters);
    }
}
