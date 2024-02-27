<?php

/**
 * Next/previous navigation test class.
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
 * @author   Conor Sheehan <csheehan@nli.ie>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Mink;

/**
 * Next/previous navigation test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Conor Sheehan <csheehan@nli.ie>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class NextPrevNavTest extends \VuFindTest\Integration\MinkTestCase
{
    /**
     * If next_prev_navigation and first_last_navigation are set to true
     * and a search which returns no results is run
     * when a record page is visited no next prev navigation should be shown
     * and no exception should be thrown
     *
     * @return void
     */
    public function testEmptySearchResultsCauseNoProblems()
    {
        $this->changeConfigs(
            ['config' => ['Record' => ['next_prev_navigation' => true, 'first_last_navigation' => true]]]
        );

        // when a search returns no results
        // make sure no errors occur when visiting a collection record after
        $session = $this->getMinkSession();
        $page = $session->getPage();

        $session->visit($this->getVuFindUrl() . '/Search/Results?lookfor=__ReturnNoResults__&type=AllField');
        $this->assertEquals($this->findCssAndGetText($page, '.search-stats > h2'), 'No Results!');

        // collection should render as normal
        $session->visit($this->getVuFindUrl() . '/Record/geo20001');

        // should fail if exception is thrown
        $this->assertStringContainsString(
            'Test Publication 20001',
            $this->findCssAndGetText($page, 'div.media-body > h1[property=name]')
        );
    }
}
