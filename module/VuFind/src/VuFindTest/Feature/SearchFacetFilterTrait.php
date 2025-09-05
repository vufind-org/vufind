<?php

/**
 * Trait for working with faceting and filtering of search results.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2014.
 * Copyright (C) The National Library of Finland 2024.
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

namespace VuFindTest\Feature;

use Behat\Mink\Element\Element;
use Behat\Mink\Element\NodeElement;

use function count;

/**
 * Trait for working with faceting and filtering of search results.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
trait SearchFacetFilterTrait
{
    /**
     * CSS selector for finding the active filter nodes
     *
     * @var string
     */
    protected $activeFilterNodesSelector
        = '.active-filters.hidden-xs .filters > a, .active-filters.hidden-xs .filters > div';

    /**
     * CSS selector for finding the active filter values
     *
     * @var string
     */
    protected $activeFilterSelector = '.active-filters.hidden-xs .filters .filter-value';

    /**
     * CSS selector for finding the active filter list
     *
     * @var string
     */
    protected $activeFilterListSelector = '.active-filters.hidden-xs .filters .title-value-pair';

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
    protected $facetSecondLevelActiveLinkSelector = '.facet-tree button[aria-expanded=true] ~ ul .active a';

    /**
     * CSS selector for finding the first second level hierarchical facet
     *
     * @var string
     */
    protected $facetSecondLevelExcludeLinkSelector = '.facet-tree button[aria-expanded=true] ~ ul a.exclude';

    /**
     * Expected hierarchical facet options by sort setting
     *
     * @var array
     */
    protected $expectedHierarchicalFacetOptions = [
        'count' => [
            [
                'filter' => '0/level1a/',
                'displayText' => 'Top Level, Sorted Last',
                'children' => [
                    [
                        'filter' => '1/level1a/level2a/',
                        'displayText' => 'level2a',
                        'children' => [
                            [
                                'filter' => '2/level1a/level2a/level3a/',
                                'displayText' => 'level3a',
                            ],
                            [
                                'filter' => '2/level1a/level2a/level3b/',
                                'displayText' => 'level3b',
                            ],
                            [
                                'filter' => '2/level1a/level2a/level3d/',
                                'displayText' => 'level3d',
                            ],
                        ],
                    ],
                    [
                        'filter' => '1/level1a/level2b/',
                        'displayText' => 'level2b',
                        'children' => [
                            [
                                'filter' => '2/level1a/level2b/level3c/',
                                'displayText' => 'level3c',
                            ],
                            [
                                'filter' => '2/level1a/level2b/level3e/',
                                'displayText' => 'level3e',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'filter' => '0/level1z/',
                'displayText' => 'Top Level, Sorted First',
                'children' => [
                    [
                        'filter' => '1/level1z/level2y/',
                        'displayText' => 'Second Level, Sorted Last',
                        'children' => [
                            [
                                'filter' => '2/level1z/level2y/level3g/',
                                'displayText' => 'level3g',
                            ],
                        ],
                    ],
                    [
                        'filter' => '1/level1z/level2z/',
                        'displayText' => 'Second Level, Sorted First',
                        'children' => [
                            [
                                'filter' => '2/level1z/level2z/level3z/',
                                'displayText' => 'level3z',
                            ],
                        ],
                    ],
                ],
            ],
        ],
        'top' => [
            [
                'filter' => '0/level1z/',
                'displayText' => 'Top Level, Sorted First',
                'children' => [
                    [
                        'filter' => '1/level1z/level2y/',
                        'displayText' => 'Second Level, Sorted Last',
                        'children' => [
                            [
                                'filter' => '2/level1z/level2y/level3g/',
                                'displayText' => 'level3g',
                            ],
                        ],
                    ],
                    [
                        'filter' => '1/level1z/level2z/',
                        'displayText' => 'Second Level, Sorted First',
                        'children' => [
                            [
                                'filter' => '2/level1z/level2z/level3z/',
                                'displayText' => 'level3z',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'filter' => '0/level1a/',
                'displayText' => 'Top Level, Sorted Last',
                'children' => [
                    [
                        'filter' => '1/level1a/level2a/',
                        'displayText' => 'level2a',
                        'children' => [
                            [
                                'filter' => '2/level1a/level2a/level3a/',
                                'displayText' => 'level3a',
                            ],
                            [
                                'filter' => '2/level1a/level2a/level3b/',
                                'displayText' => 'level3b',
                            ],
                            [
                                'filter' => '2/level1a/level2a/level3d/',
                                'displayText' => 'level3d',
                            ],
                        ],
                    ],
                    [
                        'filter' => '1/level1a/level2b/',
                        'displayText' => 'level2b',
                        'children' => [
                            [
                                'filter' => '2/level1a/level2b/level3c/',
                                'displayText' => 'level3c',
                            ],
                            [
                                'filter' => '2/level1a/level2b/level3e/',
                                'displayText' => 'level3e',
                            ],
                        ],
                    ],
                ],
            ],
        ],
        'all' => [
            [
                'filter' => '0/level1z/',
                'displayText' => 'Top Level, Sorted First',
                'children' => [
                    [
                        'filter' => '1/level1z/level2z/',
                        'displayText' => 'Second Level, Sorted First',
                        'children' => [
                            [
                                'filter' => '2/level1z/level2z/level3z/',
                                'displayText' => 'level3z',
                            ],
                        ],
                    ],
                    [
                        'filter' => '1/level1z/level2y/',
                        'displayText' => 'Second Level, Sorted Last',
                        'children' => [
                            [
                                'filter' => '2/level1z/level2y/level3g/',
                                'displayText' => 'level3g',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'filter' => '0/level1a/',
                'displayText' => 'Top Level, Sorted Last',
                'children' => [
                    [
                        'filter' => '1/level1a/level2a/',
                        'displayText' => 'level2a',
                        'children' => [
                            [
                                'filter' => '2/level1a/level2a/level3a/',
                                'displayText' => 'level3a',
                            ],
                            [
                                'filter' => '2/level1a/level2a/level3b/',
                                'displayText' => 'level3b',
                            ],
                            [
                                'filter' => '2/level1a/level2a/level3d/',
                                'displayText' => 'level3d',
                            ],
                        ],
                    ],
                    [
                        'filter' => '1/level1a/level2b/',
                        'displayText' => 'level2b',
                        'children' => [
                            [
                                'filter' => '2/level1a/level2b/level3c/',
                                'displayText' => 'level3c',
                            ],
                            [
                                'filter' => '2/level1a/level2b/level3e/',
                                'displayText' => 'level3e',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    /**
     * Check that a filter is applied
     *
     * @param Element $page           Page
     * @param int     $index          Filter index (0-based)
     * @param string  $expectedType   Filter type or empty string for checkbox filter
     * @param string  $expectedFilter Filter description
     *
     * @return void
     */
    protected function assertAppliedFilter(
        Element $page,
        int $index,
        string $expectedType,
        string $expectedFilter
    ): void {
        $appliedFilter = $this->findCss($page, $this->activeFilterNodesSelector, null, $index);
        if ($expectedType) {
            $this->assertEquals($expectedType . ':', $this->findCssAndGetText($appliedFilter, '.filters-title'));
        } else {
            $this->unFindCss($appliedFilter, '.filters-title');
        }
        $this->assertEquals("Remove Filter $expectedFilter", $this->findCssAndGetText($appliedFilter, '.filter-value'));
    }

    /**
     * Check that a set of filters (and nothing more) is applied
     *
     * @param Element $page    Page
     * @param array   $filters Filters (array of 'type:filter')
     *
     * @return void
     */
    protected function assertAppliedFilters(Element $page, array $filters): void
    {
        $this->assertFilterCount($page, count($filters));
        foreach ($filters as $index => $current) {
            [$type, $filter] = explode(':', $current, 2);
            $this->assertAppliedFilter($page, $index, $type, $filter);
        }
    }

    /**
     * Get textual content of a facet element by facet link CSS selector
     *
     * @param Element $page     Page
     * @param string  $selector CSS selector for facet link
     *
     * @return string
     */
    protected function getFacetTextByLinkSelector(Element $page, string $selector): string
    {
        return $this->findCssAndCallMethod(
            $page,
            $selector,
            function (NodeElement $node): string {
                return $node->getParent()->getText();
            }
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
        $this->assertFilterCount($page, 0);
    }

    /**
     * Assert that the given number of filters are applied.
     *
     * @param Element $page     Mink page object
     * @param int     $expected Expected filter count
     *
     * @return void
     */
    protected function assertFilterCount(Element $page, int $expected): void
    {
        if (0 === $expected) {
            $this->unFindCss($page, $this->activeFilterSelector);
            return;
        }
        // Ensure that enough page has loaded:
        $this->findCss($page, $this->activeFilterSelector);
        $items = $page->findAll('css', $this->activeFilterSelector);
        $this->assertCount($expected, $items);
    }

    /**
     * Assert that the given number of facets are present in the full facet list
     *
     * @param Element $page            Mink page object
     * @param string  $list            List type ('count' or 'index')
     * @param int     $expected        Expected filter count
     * @param bool    $exclusionActive Should exclude links be present?
     *
     * @return void
     */
    protected function assertFullListFacetCount(
        Element $page,
        string $list,
        int $expected,
        bool $exclusionActive
    ): void {
        $items = $page->findAll('css', "#modal #facet-list-$list .js-facet-item");
        $this->assertCount($expected, $items);
        $excludes = $page->findAll('css', "#modal #facet-list-$list .exclude");
        $this->assertCount($exclusionActive ? $expected : 0, $excludes);
    }

    /**
     * Apply a range facet and load results
     *
     * @param Element $page           Mink page object
     * @param string  $facet          Facet name (e.g. 'publishDate')
     * @param ?string $from           "From" value
     * @param ?string $to             "To" value
     * @param bool    $multiselection Use multi-facet selection?
     *
     * @return void
     */
    protected function applyRangeFacet(
        Element $page,
        string $facet,
        ?string $from,
        ?string $to,
        bool $multiselection
    ): void {
        $sidebar = $this->findCss($page, '.sidebar');
        $container = $this->findCss($sidebar, "#side-panel-$facet");
        if ($multiselection) {
            $checkbox = $this->findCss($sidebar, '.js-user-selection-multi-filters');
            if (!$checkbox->getValue()) {
                $checkbox->click();
            }
        }

        if (null !== $from) {
            $this->findCssAndSetValue($page, '.date-from input', $from);
        }
        if (null !== $to) {
            $this->findCssAndSetValue($page, '.date-to input', $to);
        }

        if ($multiselection) {
            $this->clickCss($sidebar, '.js-apply-multi-facets-selection');
        } else {
            $this->clickCss($container, 'input[type="submit"]');
        }
        $this->waitForPageLoad($page);
    }

    /**
     * Get expected <select> field options for a hierarchical facet
     *
     * @param string $sort Sort setting
     *
     * @return array
     */
    protected function getExpectedHierarchicalFacetOptions(string $sort): array
    {
        $result = [];
        if (null === ($hierarchy = $this->expectedHierarchicalFacetOptions[$sort] ?? null)) {
            throw new \Exception("Invalid sort '$sort'");
        }
        foreach ($this->flattenFacetHierarchy($hierarchy) as $current) {
            [$level] = explode('/', $current['filter']);
            $result['~hierarchical_facet_str_mv:"' . $current['filter'] . '"'] = str_repeat('&nbsp;', 4 * $level)
                . $current['displayText'];
        }
        return $result;
    }

    /**
     * Get expected facet tree contents as a list for a hierarchical facet
     *
     * @param string $sort Sort setting
     *
     * @return array
     */
    protected function getExpectedHierarchicalFacetTreeItems(string $sort): array
    {
        $result = [];
        if (null === ($hierarchy = $this->expectedHierarchicalFacetOptions[$sort] ?? null)) {
            throw new \Exception("Invalid sort '$sort'");
        }
        foreach ($this->flattenFacetHierarchy($hierarchy) as $current) {
            $result[] = $current['displayText'];
        }
        return $result;
    }

    /**
     * Get expected display text for a hierarchical facet filter
     *
     * @param string $sort  Sort setting
     * @param int    $index Item index
     *
     * @return string
     */
    protected function getExpectedHierarchicalFacetFilterText(string $sort, int $index): string
    {
        if (null === ($hierarchy = $this->expectedHierarchicalFacetOptions[$sort] ?? null)) {
            throw new \Exception("Invalid sort '$sort'");
        }
        $facetValues = array_values($this->flattenFacetHierarchy($hierarchy));
        if (null === ($facetValue = $facetValues[$index] ?? null)) {
            throw new \Exception("Facet index $index out of bounds");
        }
        $display = [$facetValue['displayText']];
        [$level] = explode('/', $facetValue['filter']);
        while ($level > 0) {
            --$index;
            if (null === ($facetValue = $facetValues[$index] ?? null)) {
                throw new \Exception("Facet index $index out of bounds");
            }
            array_unshift($display, $facetValue['displayText']);
            [$level] = explode('/', $facetValue['filter']);
        }
        return implode('/', $display);
    }

    /**
     * Flatten a hierarchical facet list to a simple array
     *
     * @param array $facetList Facet list
     *
     * @return array Simple array of facets
     */
    protected function flattenFacetHierarchy($facetList)
    {
        $results = [];
        foreach ($facetList as $facetItem) {
            $children = !empty($facetItem['children'])
                ? $facetItem['children']
                : [];
            unset($facetItem['children']);
            $results[] = $facetItem;
            if ($children) {
                $results = array_merge(
                    $results,
                    $this->flattenFacetHierarchy($children)
                );
            }
        }
        return $results;
    }

    /**
     * Return a displayed hierarchical facet as a list of items
     *
     * @param Element $page                  Mink page object
     * @param string  $treeContainerSelector Tree container element selector (ul needs to be an immediate child of this)
     *
     * @return array
     */
    protected function getHierarchicalFacetTreeItems(Element $page, string $treeContainerSelector): array
    {
        return $this->processFacetLevel($this->findCss($page, $treeContainerSelector));
    }

    /**
     * Recursive helper for getHierarchicalFacetTreeItems to return a facet level and its children
     *
     * @param Element $node Container element for the list
     *
     * @return array
     */
    protected function processFacetLevel(Element $node): array
    {
        $result = [];
        foreach ($node->findAll('css', ':scope > ul > li a .text') as $item) {
            $result[] = $item->getText();
            $result = [...$result, ...$this->processFacetLevel($item)];
        }
        return $result;
    }
}
