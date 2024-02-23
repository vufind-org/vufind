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
        $this->assertMatchesRegularExpression(
            "/Showing 1 - 1 results of 1 for search 'id:testbug1', query time: .*/",
            trim($this->findCss($page, '.search-stats')->getText())
        );
    }

    /**
     * Test simple top pagination
     *
     * @return void
     */
    public function testSimpleTopPagination(): void
    {
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Search/Results');
        $page = $session->getPage();

        // No paginator unless configured:
        $this->unFindCss($page, '.search-header .pagination');
        $this->unFindCss($page, '.search-header .pagination-simple');

        // Enable pagination:
        $this->changeConfigs(
            [
                'searches' => [
                    'General' => [
                        'top_paginator' => 'simple',
                    ],
                ],
            ]
        );

        $session->visit($this->getVuFindUrl() . '/Search/Results');
        $this->assertStringContainsString(
            'Showing 1 - 20 results',
            $this->findCss($page, '.search-stats')->getText()
        );

        // No prev page on first page:
        $this->unFindCss($page, '.search-header .pagination-simple .page-prev');

        $secondPage = $this->findCss($page, '.search-header .pagination-simple .page-next');
        $secondPage->click();
        $this->waitForPageLoad($page);
        $this->assertStringContainsString(
            'Showing 21 - 40 results',
            $this->findCss($page, '.search-stats')->getText()
        );
        // Prev page now present, click it:
        $this->clickCss($page, '.search-header .pagination-simple .page-prev');
        $this->waitForPageLoad($page);
        $this->assertStringContainsString(
            'Showing 1 - 20 results',
            $this->findCss($page, '.search-stats')->getText()
        );
    }

    /**
     * Test full top pagination
     *
     * @return void
     */
    public function testFullTopPagination(): void
    {
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Search/Results');
        $page = $session->getPage();

        // No paginator unless configured:
        $this->unFindCss($page, '.pagination-top');

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

        $session->visit($this->getVuFindUrl() . '/Search/Results');
        $this->assertStringContainsString(
            'Showing 1 - 20 results',
            $this->findCss($page, '.search-stats')->getText()
        );

        $this->assertEquals('1', $this->findCss($page, '.pagination-top li.active')->getText());
        $secondPage = $this->findCss($page, '.pagination-top li', null, 1);
        $secondPage->find('css', 'a')->click();
        $this->waitForPageLoad($page);

        $this->assertStringContainsString(
            'Showing 21 - 40 results',
            $this->findCss($page, '.search-stats')->getText()
        );
        $this->assertEquals('2', $this->findCss($page, '.pagination-top li.active')->getText());
        // First page now present, click it:
        $firstPage = $this->findCss($page, '.pagination-top li');
        $firstPage->find('css', 'a')->click();
        $this->assertStringContainsString(
            'Showing 1 - 20 results',
            $this->findCss($page, '.search-stats')->getText()
        );
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
}
