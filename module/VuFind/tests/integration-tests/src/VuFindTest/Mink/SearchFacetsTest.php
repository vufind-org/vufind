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
     * @param Element $page   Mink page object
     * @param array   $facets Facets to apply (title and expected counts)
     *
     * @return void
     */
    protected function facetApplyProcedure(Element $page, array $facets): void
    {
        // Confirm that we have 9 results and no filters to begin with:
        $this->assertStringStartsWith(
            'Showing 1 - 9 results of 9',
            $this->findCssAndGetText($page, '.search-stats')
        );
        $items = $page->findAll('css', $this->activeFilterSelector);
        $this->assertCount(0, $items);

        $active = 0;
        foreach ($facets as $facet) {
            $title = $facet['title'];
            $count = $facet['count'];
            $resultCount = $facet['resultCount'];
            // Apply the facet (after making sure we picked the right link):
            $facetSelector = '#side-collapse-genre_facet a[data-title="' . $title . '"]';
            $this->assertEquals("$title $count results $count", $this->findCssAndGetText($page, $facetSelector));
            $this->clickCss($page, $facetSelector);
            ++$active;

            // Check that when the page reloads, we have fewer results and a filter:
            $this->waitForPageLoad($page);
            $this->assertStringStartsWith(
                "Showing 1 - $resultCount results of $resultCount",
                $this->findCssAndGetText($page, '.search-stats')
            );
            $items = $page->findAll('css', $this->activeFilterSelector);
            $this->assertCount($active, $items);
        }

        // Confirm that all selected facets show as active:
        foreach ($facets as $facet) {
            $title = $facet['title'];
            $activeFacetSelector = '#side-collapse-genre_facet a[data-title="' . $title . '"].active';
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
        $items = $page->findAll('css', '#modal #facet-list-count .js-facet-item');
        $this->assertCount($limit, $items);
        $excludes = $page
            ->findAll('css', '#modal #facet-list-count .exclude');
        $this->assertCount($exclusionActive ? $limit : 0, $excludes);
        // more
        $this->clickCss($page, '#modal .js-facet-next-page');
        $this->waitForPageLoad($page);
        $items = $page->findAll('css', '#modal #facet-list-count .js-facet-item');
        $this->assertCount($limit * 2, $items);
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
        $excludes = $page
            ->findAll('css', '#modal #facet-list-count .exclude');
        $this->assertCount($exclusionActive ? $limit * 2 : 0, $excludes);

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
            $this->findCssAndGetText($page, '#modal #facet-list-index')
        );
        $excludes = $page
            ->findAll('css', '#modal #facet-list-index .exclude');
        $this->assertCount($exclusionActive ? $limit : 0, $excludes);
        // sort by count again
        $this->clickCss($page, '[data-sort="count"]');
        $this->waitForPageLoad($page);
        $items = $page->findAll('css', '#modal #facet-list-count .js-facet-item');
        $this->assertCount($limit, $items); // reload, resetting to just one page of results
        // now back to title, to see if loading a second page works
        $this->clickCss($page, '[data-sort="index"]');
        $this->waitForPageLoad($page);
        $this->clickCss($page, '#modal #facet-list-index .js-facet-next-page');
        $this->waitForPageLoad($page);
        $items = $page->findAll('css', '#modal #facet-list-index .js-facet-item');
        $this->assertCount($limit * 2, $items); // reset number of items
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
            ],
            'deferred AND facets' => [
                true,
                false,
                $andFacets,
            ],
            'non-deferred OR facets' => [
                false,
                true,
                $orFacets,
            ],
            'deferred OR facets' => [
                true,
                true,
                $orFacets,
            ],
        ];
    }

    /**
     * Test applying a facet to filter results (deferred facet sidebar)
     *
     * @param bool  $deferred Are deferred facets enabled?
     * @param bool  $orFacets Are OR facets enabled?
     * @param array $facets   Facets to apply
     *
     * @dataProvider applyFacetProvider
     *
     * @return void
     */
    public function testApplyFacet(bool $deferred, bool $orFacets, array $facets): void
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
        $this->facetApplyProcedure($page, $facets);

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
        $this->assertAppliedFilter($page, 'level1a/level2a');
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
        $this->assertEquals('hierarchy:', $this->findCssAndGetText($page, '.filters .filters-title'));
        $this->assertEquals(
            'Remove Filter level1a/level2a',
            $this->findCssAndGetText($page, $this->activeFilterSelector)
        );
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
                [
                    [
                        'value' => 'Top Level, Sorted Last',
                        'children' => [
                            'level2a',
                            'level2b',
                        ],
                    ],
                    [
                        'value' => 'Top Level, Sorted First',
                        'children' => [
                            'Second Level, Sorted Last',
                            'Second Level, Sorted First',
                        ],
                    ],
                ],
            ],
            [
                'top',
                [
                    [
                        'value' => 'Top Level, Sorted First',
                        'children' => [
                            'Second Level, Sorted Last',
                            'Second Level, Sorted First',
                        ],
                    ],
                    [
                        'value' => 'Top Level, Sorted Last',
                        'children' => [
                            'level2a',
                            'level2b',
                        ],
                    ],
                ],
            ],
            [
                'all',
                [
                    [
                        'value' => 'Top Level, Sorted First',
                        'children' => [
                            'Second Level, Sorted First',
                            'Second Level, Sorted Last',
                        ],
                    ],
                    [
                        'value' => 'Top Level, Sorted Last',
                        'children' => [
                            'level2a',
                            'level2b',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Test that hierarchical facet sort options work properly.
     *
     * @param ?string $sort     Sort option
     * @param array   $expected Expected facet values in order
     *
     * @dataProvider hierarchicalFacetSortProvider
     *
     * @return void
     */
    public function testHierarchicalFacetSort(?string $sort, array $expected): void
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
        foreach ($expected as $index => $facet) {
            $topLi = $this->findCss($page, '#side-collapse-hierarchical_facet_str_mv ul.facet-tree > li', null, $index);
            $this->assertEquals(
                $facet['value'],
                $this->findCssAndGetText($topLi, '.facet-value'),
                "Hierarchical facet item $index"
            );
            foreach ($facet['children'] as $childIndex => $childFacet) {
                $childLi = $this->findCss($topLi, 'ul > li.facet-tree__parent', null, $childIndex);
                $this->assertEquals(
                    $childFacet,
                    $this->findCssAndGetText($childLi, '.facet-value'),
                    "Hierarchical facet item $index child $childIndex"
                );
            }
        }
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
        $this->assertEquals(
            'Remove Filter weird_ids.mrc',
            $this->findCssAndGetText($page, $this->activeFilterSelector)
        );
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
}
