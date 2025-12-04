<?php

/**
 * Test new item search functionality.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Mink;

use Behat\Mink\Element\Element;

/**
 * Test new item search functionality.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class NewItemsTest extends \VuFindTest\Integration\MinkTestCase
{
    /**
     * Submit a new item search and return the resulting page object.
     *
     * @param int    $buttonIndex    Index of range button to press
     * @param string $expectedRanges Expected text of the range select buttons
     *
     * @return Element
     */
    protected function submitNewItemSearch(
        int $buttonIndex = 1,
        string $expectedRanges = 'Yesterday Past 5 Days Past 30 Days'
    ): Element {
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Search/NewItem');
        $page = $session->getPage();
        // Confirm custom ranges display correctly:
        $this->assertEquals(
            $expectedRanges,
            $this->findCssAndGetText($page, '.form-search-newitem .btn-group')
        );
        // Now perform a search:
        $this->clickCss($page, '.form-search-newitem .btn-group .btn-primary', index: $buttonIndex);
        $this->clickCss($page, '.form-search-newitem input[type="submit"]');
        $this->waitForPageLoad($page);
        return $page;
    }

    /**
     * Test that pagination works as expected in new items.
     *
     * @return void
     */
    public function testSolrDrivenNewItemsPagination(): void
    {
        $this->changeConfigs(
            [
                'searches' => [
                    'General' => [
                        'default_limit' => 10,
                    ],
                    'NewItem' => [
                        'method' => 'solr',
                    ],
                ],
            ]
        );
        $page = $this->submitNewItemSearch();
        // Confirm that we've reached the custom results page:
        $this->assertStringStartsWith(
            'Showing 1 - 10 results of 20 New Items',
            $this->findCssAndGetText($page, '.search-stats')
        );
        $this->clickCss($page, '.page-link');
        $this->waitForPageLoad($page);
        $this->assertStringEndsWith(
            'Showing 11 - 20 results of 20 New Items',
            $this->findCssAndGetText($page, '.search-stats')
        );
    }

    /**
     * Test that Solr-powered new items work as expected (using non-default range settings).
     *
     * @return void
     */
    public function testSolrDrivenNewItemsWithNonDefaultRanges(): void
    {
        $this->changeConfigs(
            [
                'searches' => [
                    'NewItem' => [
                        'method' => 'solr',
                        'ranges' => '1,15,60',
                    ],
                ],
            ]
        );
        $page = $this->submitNewItemSearch(expectedRanges: 'Yesterday Past 15 Days Past 60 Days');

        // Confirm that we've reached the custom results page:
        $this->assertEquals(
            'Showing 1 - 20 results of 20 New Items',
            $this->findCssAndGetText($page, '.search-stats')
        );

        // Make sure that author links do not have inappropriate hidden filters:
        $authorLink = $this->findAndAssertLink($page, 'Shakespeare, William 1564 - 1616');
        $this->assertStringEndsWith(
            '/Author/Home?author=Shakespeare,%20William%201564%20-%201616',
            $authorLink->getAttribute('href')
        );

        // Make sure that facet links do not have inappropriate hidden filters:
        $facetLink = $this->findAndAssertLink($page, 'B - Philosophy, Psychology, Religion');
        $this->assertEquals(
            '?range=15&department=&filter%5B%5D=callnumber-first%3A%22B+-+Philosophy%2C+Psychology%2C+Religion%22',
            $facetLink->getAttribute('href')
        );

        // Click through to a record and make sure it does not include unexpected hidden filters:
        $title = 'Englisch-deutsche Studienausgabe der Dramen Shakespeares /';
        $recordLink = $this->findAndAssertLink($page, $title);
        $recordLink->click();
        $this->waitForPageLoad($page);
        $this->assertEquals($title, $this->findCssAndGetText($page, 'h1'));
        $recordAuthorLink = $this->findAndAssertLink($page, 'Shakespeare, William 1564 - 1616');
        $this->assertStringEndsWith(
            '/Author/Home?author=Shakespeare,%20William%201564%20-%201616',
            $recordAuthorLink->getAttribute('href')
        );

        // Perform a fresh search and make sure unwanted hidden filters do not bleed through:
        $session = $this->getMinkSession();
        $session->back(); // return to new item list
        $this->clickCss($page, '.search.container .btn-primary');
        $this->waitForPageLoad($page);
        $this->assertStringEndsWith(
            '/Search/Results?lookfor=&type=AllFields',
            $session->getCurrentUrl()
        );
    }
}
