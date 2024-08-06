<?php

/**
 * Trait for working with sorting of search results.
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

namespace VuFindTest\Feature;

use Behat\Mink\Element\Element;

/**
 * Trait for working with sorting of search results.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
trait SearchSortTrait
{
    /**
     * Selector for sort control
     *
     * @var string
     */
    protected $sortControlSelector = '#sort_options_1';

    /**
     * VuFind default sort options
     *
     * @var string[]
     */
    protected $defaultSortOptions = [
        'Relevance',
        'Date Descending',
        'Date Ascending',
        'Call Number',
        'Author',
    ];

    /**
     * Check that first and last record of the results are correct
     *
     * @param Element $page  Current page
     * @param int     $count Expected total result count
     * @param string  $first Expected first title
     * @param string  $last  Expected last title
     *
     * @return void
     */
    protected function assertResultTitles(Element $page, int $count, string $first, string $last): void
    {
        $titles = $page->findAll('css', '.result a.title');
        $this->assertCount($count, $titles);
        $this->assertEquals($first, $titles[0]->getText());
        $this->assertEquals($last, $titles[$count - 1]->getText());
        // Check that record links contain sid parameter:
        $url = $titles[0]->getAttribute('href');
        parse_str(parse_url($url, PHP_URL_QUERY), $urlParams);
        $this->assertArrayHasKey('sid', $urlParams);
        $this->assertNotEmpty($urlParams['sid']);
    }

    /**
     * Change sort order of search results
     *
     * @param Element $page  Current page
     * @param string  $value Sort option
     *
     * @return void
     */
    protected function sortResults(Element $page, string $value): void
    {
        $this->findCssAndSetValue($page, $this->sortControlSelector, $value);
    }

    /**
     * Assert the selected sort option.
     *
     * @param Element $page   Current page
     * @param string  $active Selected sort option
     *
     * @return void
     */
    protected function assertSelectedSort(Element $page, string $active): void
    {
        $sort = $this->findCss($page, $this->sortControlSelector);
        $this->assertEquals((string)$active, $sort->getValue());
    }
}
