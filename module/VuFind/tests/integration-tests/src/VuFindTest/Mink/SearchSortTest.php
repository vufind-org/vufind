<?php

/**
 * Test for sorting of search results.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2021.
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
 * Test for sorting of search results.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class SearchSortTest extends \VuFindTest\Integration\MinkTestCase
{
    use \VuFindTest\Feature\SearchSortTrait;

    /**
     * Test that an invalid sort option sends us to the default value.
     *
     * @return void
     */
    public function testInvalidSort(): void
    {
        $page = $this->setUpSearch('foobar', 'relevance');
        $this->assertSortControl($page, 'relevance');
    }

    /**
     * Test default sort
     *
     * @return void
     */
    public function testDefaultSort(): void
    {
        $page = $this->setUpSearch('', 'title desc');
        $this->assertSortControl($page, 'title desc');
    }

    /**
     * Test the sort control
     *
     * @return void
     */
    public function testSortChange(): void
    {
        $page = $this->setUpSearch('title', 'title');

        // Check current sort:
        $this->assertSortControl($page, 'title');

        // Check expected first and last record on first page:
        $this->assertResultTitles($page, 20, 'Test Publication 20001', 'Test Publication 20020');

        // Go to second page:
        $this->clickCss($page, '.pagination li:not(.active) > a');
        $this->waitForPageLoad($page);
        $this->assertResultTitles($page, 20, 'Test Publication 20021', 'Test Publication 20040');

        // Change sort to title reversed (last option) and verify:
        $this->clickCss($page, $this->sortControlSelector . ' option', null, count($this->defaultSortOptions) + 1);
        $this->waitForPageLoad($page);
        // Check current sort:
        $this->assertSortControl($page, 'title desc');
        // Check expected first and last record (page should be reset):
        $this->assertResultTitles($page, 20, 'Test Publication 20177', 'Test Publication 201738');
        // Check that url no longer contains the page parameter:
        $this->assertStringNotContainsString('&page', $this->getCurrentQueryString());

        // Change sort back to relevance (first option) and verify:
        $this->clickCss($page, $this->sortControlSelector . ' option');
        $this->waitForPageLoad($page);
        $this->assertResultTitles($page, 20, 'Test Publication 20001', 'Test Publication 20020');
    }

    /**
     * Test the sort control
     *
     * @return void
     */
    public function testHiddenSort(): void
    {
        $this->changeConfigs(
            [
                'searches' => [
                    'General' => [
                        'default_sort' => 'title',
                    ],
                    'Sorting' => [
                        'title' => 'Title',
                    ],
                    'HiddenSorting' => [
                        'pattern' => [
                            '.* desc',
                        ],
                    ],
                ],
            ]
        );
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Search/Results?filter[]=building%3A%22geo.mrc%22&sort=title');
        $page = $session->getPage();

        // Check expected first and last record on first page:
        $this->assertResultTitles($page, 20, 'Test Publication 20001', 'Test Publication 20020');

        // Change sort to title reversed (hidden option) and verify:
        $session->visit($this->getVuFindUrl() . '/Search/Results?filter[]=building%3A%22geo.mrc%22&sort=title desc');
        $page = $session->getPage();

        // Check expected first and last record:
        $this->assertResultTitles($page, 20, 'Test Publication 20177', 'Test Publication 201738');
    }

    /**
     * Test sort stickiness
     *
     * @return void
     */
    public function testSortStickiness(): void
    {
        // Call number has a custom default sort; if we do a regular search and leave
        // the sort at default, then do a call number search, we expect the sort to
        // change accordingly. We also expect it to change back if we do a non-call-no
        // search.
        $page = $this->performSearch('*:*');
        $this->assertSelectedSort($page, 'relevance');
        $this->submitSearchForm($page, '*:*', 'CallNumber');
        $this->assertSelectedSort($page, 'callnumber-sort');
        $this->submitSearchForm($page, '*:*', 'AllFields');
        $this->assertSelectedSort($page, 'relevance');

        // However, if we choose a non-default sort, we expect it to stick across
        // all search types:
        $this->clickCss($page, $this->sortControlSelector . ' option', null, 2);
        $this->waitForPageLoad($page);
        $this->assertSelectedSort($page, 'year asc');
        $this->submitSearchForm($page, '*:*', 'CallNumber');
        $this->assertSelectedSort($page, 'year asc');
        $this->submitSearchForm($page, '*:*', 'AllFields');
        $this->assertSelectedSort($page, 'year asc');
    }

    /**
     * Set up a search page with sorting configured
     *
     * @param string $sortParam Requested sort option
     * @param string $default   default_sort setting for searches.ini
     *
     * @return Element
     */
    protected function setUpSearch(string $sortParam, string $default): Element
    {
        $this->changeConfigs(
            [
                'searches' => [
                    'General' => [
                        'default_sort' => $default,
                    ],
                    'Sorting' => [
                        'title' => 'Title',
                        'title desc' => 'Title Reversed',
                    ],
                ],
            ]
        );
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . "/Search/Results?filter[]=building%3A%22geo.mrc%22&sort=$sortParam");
        return $session->getPage();
    }

    /**
     * Assert the contents and selected element of the sort control.
     *
     * @param Element $page   Current page
     * @param string  $active Expected active option
     *
     * @return void
     */
    protected function assertSortControl(Element $page, string $active)
    {
        $this->assertSelectedSort($page, $active);
        $optionElements = $page->findAll('css', $this->sortControlSelector . ' option');
        $callback = function (Element $element): string {
            return $element->getText();
        };
        $actualOptions = array_map($callback, $optionElements);
        $this->assertEquals([...$this->defaultSortOptions, 'Title', 'Title Reversed'], $actualOptions);
    }
}
