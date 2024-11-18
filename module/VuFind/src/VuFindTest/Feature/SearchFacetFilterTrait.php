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
     * Check that a filter is applied
     *
     * @param Element $page           Page
     * @param string  $expectedFilter Filter description
     *
     * @return void
     */
    protected function assertAppliedFilter(Element $page, string $expectedFilter): void
    {
        $filter = $this->findCss($page, $this->activeFilterSelector);
        $label = $this->findCss($page, $this->activeFilterLabelSelector);
        $this->assertEquals('hierarchy:', $label->getText());
        $this->assertEquals("Remove Filter $expectedFilter", $filter->getText());
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
}
