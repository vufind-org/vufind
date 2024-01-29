<?php

/**
 * Test basic search functionality.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023.
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

use Behat\Mink\Element\Element;

/**
 * Test basic search functionality.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 * @retry    4
 */
class BasicSearchTest extends \VuFindTest\Integration\MinkTestCase
{
    /**
     * Test that an out-of-bounds page number redirects to an in-bounds page.
     *
     * @return void
     */
    public function testOutOfBoundsPage()
    {
        $session = $this->getMinkSession();
        $baseUrl = $this->getVuFindUrl() . '/Search/Results?lookfor=id:testbug1';
        $session->visit($baseUrl . '&page=1000');
        $this->assertEquals($baseUrl . '&page=1', $session->getCurrentUrl());
        $page = $session->getPage();
        $this->assertStringStartsWith(
            'Showing 1 - 1 results of 1',
            trim($this->findCss($page, '.search-stats')->getText())
        );
    }

    /**
     * Data provider for testDefaultTopPagination
     *
     * @return array
     */
    public static function topPaginationProvider(): array
    {
        return [
            [false],
            [true],
        ];
    }

    /**
     * Test default top pagination
     *
     * @param bool $jsResults Whether to update search results with JS
     *
     * @dataProvider topPaginationProvider
     *
     * @return void
     */
    public function testDefaultTopPagination(bool $jsResults): void
    {
        // Change configuration:
        $this->changeConfigs(
            [
                'searches' => [
                    'General' => [
                        'load_results_with_js' => $jsResults,
                    ],
                ],
            ]
        );

        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Search/Results');
        $page = $session->getPage();

        // Should never have full top pagination:
        $this->unFindCss($page, '.pagination-top');

        if ($jsResults) {
            // Simple paginator by default with JS results:
            $this->findCss($page, '.search-header .pagination-simple');
        } else {
            // No paginator by default without JS results:
            $this->unFindCss($page, '.search-header .pagination-simple');
        }
    }

    /**
     * Test simple top pagination
     *
     * @param bool $jsResults Whether to update search results with JS
     *
     * @dataProvider topPaginationProvider
     *
     * @return void
     */
    public function testSimpleTopPagination(bool $jsResults): void
    {
        $config = [
            'load_results_with_js' => $jsResults,
        ];
        if (!$jsResults) {
            // Enable top paginator:
            $config['top_paginator'] = 'simple';
        }
        $this->changeConfigs(
            [
                'searches' => [
                    'General' => $config,
                ],
            ]
        );

        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Search/Results');
        $page = $session->getPage();
        $this->assertShowingResults($page, '1 - 20');

        // No prev page on first page:
        $this->unFindCss($page, '.search-header .pagination-simple .page-prev');

        $secondPage = $this->findCss($page, '.search-header .pagination-simple .page-next');
        $secondPage->click();
        $this->waitForPageLoad($page);
        $this->assertShowingResults($page, '21 - 40');
        $this->scrollToResults();

        // Prev page now present, click it:
        $this->clickCss($page, '.search-header .pagination-simple .page-prev');
        $this->waitForPageLoad($page);
        $this->assertShowingResults($page, '1 - 20');
    }

    /**
     * Test full top pagination
     *
     * @return void
     */
    public function testFullTopPagination(): void
    {
        // Enable pagination:
        $this->changeConfigs(
            [
                'searches' => [
                    'General' => [
                        'top_paginator' => 'full',
                    ],
                ],
            ]
        );

        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Search/Results');
        $page = $session->getPage();

        $session->visit($this->getVuFindUrl() . '/Search/Results');
        $this->assertShowingResults($page, '1 - 20');

        $this->assertEquals('1', $this->findCss($page, '.pagination-top li.active')->getText());
        $secondPage = $this->findCss($page, '.pagination-top li', null, 1);
        $secondPage->find('css', 'a')->click();
        $this->waitForPageLoad($page);

        $this->assertShowingResults($page, '21 - 40');
        $this->assertEquals('2', $this->findCss($page, '.pagination-top li.active')->getText());

        // First page now present, click it:
        $this->scrollToResults();
        $this->clickCss($page, '.pagination-top li a');
        $this->assertShowingResults($page, '1 - 20');
        $this->assertEquals('1', $this->findCss($page, '.pagination-top li.active')->getText());
    }

    /**
     * Test bottom pagination
     *
     * @return void
     */
    public function testBottomPagination(): void
    {
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Search/Results');
        $page = $session->getPage();

        $this->assertEquals('1', $this->findCss($page, '.pagination li.active')->getText());
        $secondPage = $this->findCss($page, '.pagination li', null, 1);
        $secondPage->find('css', 'a')->click();
        $this->waitForPageLoad($page);

        $this->assertEquals('2', $this->findCss($page, '.pagination li.active')->getText());
    }

    /**
     * Check that correct result range is being displayed
     *
     * @param Element $page    Page
     * @param string  $results Result range (e.g. '1 - 20')
     *
     * @return void
     */
    protected function assertShowingResults(Element $page, string $results): void
    {
        $this->assertStringContainsStringWithTimeout(
            "Showing $results results",
            function () use ($page): string {
                return $this->findCss($page, '.search-stats')->getText();
            }
        );
    }

    /**
     * Scroll to results immediately to avoid elements from moving around while we click them
     *
     * @return void
     */
    protected function scrollToResults(): void
    {
        $this->getMinkSession()->executeScript(
            'typeof VuFind.search !== "undefined" && VuFind.search.scrollToResults("instant")'
        );
    }
}
