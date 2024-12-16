<?php

/**
 * "Jump to record" test class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018.
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
 * "Jump to record" test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class JumpToRecordTest extends \VuFindTest\Integration\MinkTestCase
{
    /**
     * Test that we can jump to the first record in a single-record result set.
     *
     * @return void
     */
    public function testJumpToFirst()
    {
        $this->changeConfigs(
            ['config' => ['Record' => ['jump_to_single_search_result' => true]]]
        );

        $page = $this->performSearch('id:testbug2');

        $this->assertEquals(
            'La congiura dei Principi Napoletani 1701 : (prima e seconda stesura) /',
            trim($this->findCssAndGetText($page, 'h1'))
        );

        // check if jump to is disabled on breadcrumb link
        $this->clickCss($page, '.breadcrumb li:first-child');
        $this->waitForPageLoad($page);

        $expected = 'Showing 1 - 1 results of 1';
        $this->assertStringStartsWith(
            $expected,
            $this->findCssAndGetText($page, '.search-stats')
        );
    }

    /**
     * Same as the previous test, but without switching on the jump setting; this
     * should result in a result list.
     *
     * @return void
     */
    public function testDoNotJumpToFirst()
    {
        $page = $this->performSearch('id:testbug2');

        $expected = 'Showing 1 - 1 results of 1';
        $this->assertStringStartsWith(
            $expected,
            $this->findCssAndGetText($page, '.search-stats')
        );
    }
}
