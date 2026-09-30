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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
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
     * and no exception should be thrown.
     *
     * @return void
     */
    public function testEmptySearchResultsCauseNoProblems(): void
    {
        $this->changeConfigs(
            ['config' => ['Record' => ['next_prev_navigation' => true, 'first_last_navigation' => true]]]
        );

        // when a search returns no results
        // make sure no errors occur when visiting a collection record after
        $session = $this->getMinkSession();
        $page = $session->getPage();

        $session->visit($this->getVuFindUrl() . '/Search/Results?lookfor=__ReturnNoResults__&type=AllField');
        $this->assertSame('No Results!', $this->findCssAndGetText($page, '.search-stats > h2'));

        // collection should render as normal
        $session->visit($this->getVuFindUrl() . '/Record/geo20001');

        // should fail if exception is thrown
        $this->assertStringContainsString(
            'Test Publication 20001',
            $this->findCssAndGetText($page, 'div.media-body > h1[property=name]')
        );
    }

    /**
     * If next_prev_navigation and first_last_navigation are set to true
     * and a search results have been loaded via JS the navigation should
     * be shown in the results.
     *
     * @return void
     */
    public function testJSCauseNoProblems(): void
    {
        $this->changeConfigs(
            ['config' => ['Record' => ['next_prev_navigation' => true, 'first_last_navigation' => true]]]
        );

        // when a search returns no results
        // make sure no errors occur when visiting a collection record after
        $session = $this->getMinkSession();
        $page = $session->getPage();

        $session->visit($this->getVuFindUrl() . '/Search/Results?type=AllField');
        $this->waitForPageLoad($page);

        $this->clickCss($page, '.search-header .pagination-simple .page-next');
        $this->waitForPageLoad($page);

        $this->clickCss($page, '#result0 a.getFull');
        $this->waitForPageLoad($page);

        $this->findCss($page, 'nav .pager');
    }

    /**
     * If the total result count decreases during the next-prev navigation controls should update.
     *
     * @return void
     */
    public function testDecreasedResultCountChangesCauseNoProblems(): void
    {
        $this->changeConfigs(
            ['config' => ['Record' => ['next_prev_navigation' => true, 'first_last_navigation' => true]]]
        );

        // when a search returns no results
        // make sure no errors occur when visiting a collection record after
        $session = $this->getMinkSession();
        $page = $session->getPage();

        $session->visit($this->getVuFindUrl() . '/Search/Results?lookfor=Author&type=AllFields&limit=2&page=6');
        $this->waitForPageLoad($page);

        $this->clickCss($page, '#result1 a.getFull');
        $this->waitForPageLoad($page);

        $pagerText = $this->findCssAndGetText($page, '.pager .pager-text');
        $this->assertSame('#12 of 16 results', $pagerText);

        // Change searchspecs.yaml to reduce total results to 14.
        $this->changeYamlConfigs(
            [
                'searchspecs' => [
                    'AllFields' => [
                        'DismaxFields' => [
                            'author',
                        ],
                    ],
                ],
            ],
            ['searchspecs']
        );

        $this->clickCss($page, '.pager .page-next a');
        $this->waitForPageLoad($page);

        $pagerText = $this->findCssAndGetText($page, '.pager .pager-text');
        $this->assertSame('#13 of 16 results', $pagerText);

        // Test that reaching end of search results updates pager info
        $this->clickCss($page, '.pager .page-next a');
        $this->waitForPageLoad($page);

        $pagerText = $this->findCssAndGetText($page, '.pager .pager-text');
        $this->assertSame('#14 of 14 results', $pagerText);

        $this->findCss($page, '.pager .page-next.disabled');
        $this->findCss($page, '.pager .page-last.disabled');

        $lastPageUrl = $session->getCurrentUrl();

        // Test that updated info persists when going back
        $this->clickCss($page, '.pager .page-prev a');
        $this->waitForPageLoad($page);

        $pagerText = $this->findCssAndGetText($page, '.pager .pager-text');
        $this->assertSame('#13 of 14 results', $pagerText);

        $this->unFindCss($page, '.pager .page-next.disabled');

        // Test that link to last result is updated
        $this->clickCss($page, '.pager .page-last a');
        $this->waitForPageLoad($page);
        $this->assertSame($lastPageUrl, $session->getCurrentUrl());
    }
}
