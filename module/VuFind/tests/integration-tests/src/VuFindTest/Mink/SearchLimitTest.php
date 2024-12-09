<?php

/**
 * Test for search limits.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2021.
 * Copyright (C) The National Library of Finland 2023.
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
use VuFindTest\Feature\SearchLimitTrait;

use function intval;

/**
 * Test for search limits.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class SearchLimitTest extends \VuFindTest\Integration\MinkTestCase
{
    use SearchLimitTrait;

    /**
     * Selector for limit control
     *
     * @var string
     */
    protected $limitControlSelector = '#limit';

    /**
     * Set up a search page with limits configured
     *
     * @param string $limitParam Value of limit GET parameter
     * @param string $options    limit_options setting for searches.ini
     * @param string $default    default_limit setting for searches.ini
     *
     * @return Element
     */
    protected function setUpLimitedSearch(
        string $limitParam,
        string $options = null,
        string $default = '20'
    ): Element {
        $config = ['default_limit' => $default, 'limit_options' => $options];
        $this->changeConfigs(['searches' => ['General' => $config]]);
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . "/Search/Results?filter[]=building%3A%22geo.mrc%22&limit=$limitParam");
        return $session->getPage();
    }

    /**
     * Assert the page size of the current search result set.
     *
     * @param Element $page Current page
     * @param int     $size Expected page size
     *
     * @return void
     */
    protected function assertResultSize(Element $page, int $size)
    {
        $text = $this->findCssAndGetText($page, '.search-stats strong');
        [, $actualSize] = explode(' - ', $text);
        $this->assertEquals($size, intval($actualSize));
    }

    /**
     * Check that first and last record of the results are correct
     *
     * @param Element $page  Current page
     * @param string  $first Expected first title
     * @param string  $last  Expected last title
     * @param int     $count Expected result count
     *
     * @return void
     */
    protected function assertResultTitles($page, string $first, string $last, int $count): void
    {
        $titles = $page->findAll('css', '.result a.title');
        $this->assertCount($count, $titles);
        $this->assertEquals($first, $titles[0]->getText());
        $this->assertEquals($last, $titles[$count - 1]->getText());
    }

    /**
     * Test that default page size is 20, with no limit controls displayed.
     *
     * @return void
     */
    public function testDefaults(): void
    {
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Search/Results');
        $page = $session->getPage();
        $this->assertResultSize($page, 20);
        $this->assertNoLimitControl($page);
    }

    /**
     * Test that a custom limit behaves as expected.
     *
     * @return void
     */
    public function testCustomLimits(): void
    {
        // Test that non-default value gets selected:
        $page = $this->setUpLimitedSearch('9', '3,6,9', '6');
        $this->assertResultSize($page, 9);
        $this->assertLimitControl($page, [3, 6, 9], 9);

        // Test that default is used for empty setting:
        $page = $this->setUpLimitedSearch('', '3,6,9', '6');
        $this->assertResultSize($page, 6);
        $this->assertLimitControl($page, [3, 6, 9], 6);
    }

    /**
     * Test that an invalid limit option sends us to the default value.
     *
     * @return void
     */
    public function testInvalidLimits(): void
    {
        // If we request 40 items when only 3/6/9 are supported and default is 6,
        // we should get 6 results. VuFind blocks limits that are higher than the
        // highest supported value.
        $page = $this->setUpLimitedSearch('40', '3,6,9', '6');
        $this->assertResultSize($page, 6);
        $this->assertLimitControl($page, [3, 6, 9], 6);

        // If we request 4 items when only 3/6/9 are supported and default is 6,
        // we should get 4 results. VuFind allows non-standard limits that are lower
        // than the highest supported value.
        $page = $this->setUpLimitedSearch('4', '3,6,9', '6');
        $this->assertResultSize($page, 4);
        $this->assertLimitControl($page, [3, 6, 9], 3);
    }

    /**
     * Test that non-numeric limit values in GET parameter are handled correctly.
     *
     * @return void
     */
    public function testNonNumericLimitValues(): void
    {
        // Characters at end of number should get ignored:
        $page = $this->setUpLimitedSearch('9%27A=0', '3,6,9', '6');
        $this->assertResultSize($page, 9);
        $this->assertLimitControl($page, [3, 6, 9], 9);

        // Entirely nonsensical limit should give default value:
        $page = $this->setUpLimitedSearch('GARBAGE', '3,6,9', '6');
        $this->assertResultSize($page, 6);
        $this->assertLimitControl($page, [3, 6, 9], 6);
    }

    /**
     * Test the limit control
     *
     * @return void
     */
    public function testLimitChange(): void
    {
        $page = $this->setUpLimitedSearch('20', '20,40', '20');

        // Check expected first and last record on first page:
        $this->assertResultTitles($page, 'Test Publication 20001', 'Test Publication 20020', 20);

        // Go to second page:
        $this->clickCss($page, '.pagination li:not(.active) > a');
        $this->waitForPageLoad($page);
        $this->assertResultTitles($page, 'Test Publication 20021', 'Test Publication 20040', 20);

        // Change limit and verify:
        $this->setResultLimit($page, 40);
        $this->waitForPageLoad($page);
        // Check expected first and last record (page should be reset):
        $this->assertResultTitles($page, 'Test Publication 20001', 'Test Publication 20040', 40);
        // Check that url no longer contains the page parameter:
        $this->assertStringNotContainsString('&page', $this->getCurrentQueryString());
    }
}
