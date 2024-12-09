<?php

/**
 * Mink author search test class.
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

use VuFindTest\Feature\SearchFacetFilterTrait;

/**
 * Mink author search test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class AuthorSearchTest extends \VuFindTest\Integration\MinkTestCase
{
    use SearchFacetFilterTrait;

    /**
     * Test searching for a known corporate author
     *
     * @return void
     */
    public function testCorporateAuthorSearch(): void
    {
        $page = $this->performSearch('corporate', 'Author');
        $facets = $this->getFacetTextByLinkSelector($page, '#side-collapse-building a');
        // We'll check for a known count from a known MARC file to confirm that
        // results came back.
        $this->assertStringContainsString('author_relators.mrc 10', $facets);
    }

    /**
     * Test searching for a known primary author
     *
     * @return void
     */
    public function testPrimaryAuthorSearch(): void
    {
        $page = $this->performSearch('primary', 'Author');
        $facets = $this->getFacetTextByLinkSelector($page, '#side-collapse-building a');
        // We'll check for a known count from a known MARC file to confirm that
        // results came back.
        $this->assertStringContainsString('author_relators.mrc 11', $facets);
    }
}
