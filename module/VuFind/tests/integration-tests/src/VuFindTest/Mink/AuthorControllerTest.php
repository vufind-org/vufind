<?php

/**
 * Mink author controller test class.
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
 * Mink author controller test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class AuthorControllerTest extends \VuFindTest\Integration\MinkTestCase
{
    /**
     * Standard setup method that runs before each test.
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        // Setup config
        $this->changeConfigs(
            [
                'config' => [
                    'Content' => ['authors' => false], // turn off Wikipedia for testing
                ],
            ]
        );
    }

    /**
     * Test searching for an author in the author module
     *
     * @return void
     */
    public function testAuthorSearch(): void
    {
        $session = $this->getMinkSession();
        $baseUrl = $this->getVuFindUrl() . '/Author/Home';
        $session->visit($baseUrl);
        $page = $session->getPage();
        $this->findCssAndSetValue($page, '#author_lookfor', 'shakespeare');
        $this->clickCss($page, '#content input[type="submit"]');
        $this->waitForPageLoad($page);
        // We should have some results:
        $this->assertMatchesRegularExpression(
            "/Showing 1 - \d+ results/",
            trim($this->findCssAndGetText($page, '.search-stats'))
        );
        // We should be on the author results page:
        $this->assertStringEndsWith(
            '/Author/Search?lookfor=shakespeare',
            $session->getCurrentUrl()
        );
        // The page should contain a link to the Shakespeare author page:
        $this->assertStringEndsWith(
            '/Author/Home?author=Shakespeare%2C+William+1564+-+1616',
            $page->findLink('Shakespeare, William 1564 - 1616')->getAttribute('href')
        );
    }

    /**
     * Data provider that offers various author controller paths for testing.
     *
     * @return array
     */
    public static function authorPathsProvider(): array
    {
        return [
            'home page' => ['/Author/Home'],
            'results page' => ['/Author/Search?lookfor=shakespeare'],
            'author page' => ['/Author/Home?author=Shakespeare%2C+William+1564+-+1616'],
        ];
    }

    /**
     * Confirm that the author controller does not interfere with the regular search box
     *
     * @param string $path Starting URL path to test
     *
     * @return void
     *
     * @dataProvider authorPathsProvider
     */
    public function testAuthorSearchDoesNotBreakSearchBox(string $path): void
    {
        $session = $this->getMinkSession();
        $baseUrl = $this->getVuFindUrl() . $path;
        $session->visit($baseUrl);
        $page = $session->getPage();
        $this->findCssAndSetValue($page, '#searchForm_lookfor', 'foo');
        $this->clickCss($page, '#searchForm button.btn-primary');
        $this->waitForPageLoad($page);
        $this->assertStringStartsWith(
            $this->getVuFindUrl() . '/Search/Results?lookfor=foo&type=AllFields',
            $session->getCurrentUrl()
        );
    }
}
