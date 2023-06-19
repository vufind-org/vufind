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
}
