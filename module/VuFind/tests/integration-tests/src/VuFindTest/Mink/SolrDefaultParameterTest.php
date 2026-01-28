<?php

/**
 * Mink test class for Solr default parameter functionality.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2026.
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
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Mink;

use function intval;

/**
 * Mink test class for Solr default parameter functionality.
 *
 * @category VuFind
 * @package  Tests
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class SolrDefaultParameterTest extends \VuFindTest\Integration\MinkTestCase
{
    /**
     * Test that default parameters work.
     *
     * @return void
     */
    public function testDefaultParameter(): void
    {
        $id = '0001732009-3';
        $this->changeConfigs(
            ['searches' => ['General' => ['default_parameters' => ['search' => 'fq=' . urlencode("id:$id")]]]]
        );
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Search/Results');
        $page = $session->getPage();
        $text = $this->findCssAndGetText($page, '.search-stats strong');
        [, $actualSize] = explode(' - ', $text);
        $this->assertSame(1, intval($actualSize));
        $this->assertStringContainsString(
            "/Record/$id?sid=",
            $this->findCss($page, '.result a.title')->getAttribute('href')
        );
    }
}
