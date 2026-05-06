<?php

/**
 * Mink test class for the Search2 backend.
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

/**
 * Mink test class for the Search2 backend.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Search2Test extends \VuFindTest\Integration\MinkTestCase
{
    /**
     * Test that out of range search results page detection redirects to the right place.
     *
     * @return void
     */
    public function testOutOfRangePageDetection(): void
    {
        // Perform a search guaranteed to return just one record, and try to go to page 2:
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Search2/Results?lookfor=id:testbug2&page=2');
        $page = $session->getPage();
        $this->waitForPageLoad($page);
        // We should have ended up on page 1!
        $this->assertStringEndsWith('/Search2/Results?lookfor=id:testbug2&page=1', $session->getCurrentUrl());
    }
}
