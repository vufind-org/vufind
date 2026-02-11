<?php

/**
 * Mink test class for the Site Map page.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2026.
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
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Mink;

use Behat\Mink\Element\Element;
use VuFindTest\Integration\Session;

/**
 * Mink test class for the Site Map page.
 *
 * @category VuFind
 * @package  Tests
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class SiteMapPageTest extends \VuFindTest\Integration\MinkTestCase
{
    /**
     * Standard setup method that runs before each test.
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->changeConfigs(
            [
                'config' => [
                    'Site' => [
                        'siteMapPageEnabled' => true,
                    ],
                ],
            ]
        );
    }

    /**
     * Apply a non-default SiteMap.yaml configuration that includes submenu items.
     *
     * @return void
     */
    protected function applySiteMapConfigWithSubmenuItems(): void
    {
        $this->changeYamlConfigs(
            [
                'SiteMap' => [
                    '@parent_config_name' => false,
                    'HomePage' => [
                        'MenuItems' => [
                            [
                                'label' => 'Home Page',
                                'route' => 'home',
                            ],
                        ],
                    ],
                    'SubmenuItemsTest' => [
                        'label' => 'Submenu Items Test',
                        'MenuItems' => [
                            [
                                'label' => 'Linked Parent Item 1',
                                'url' => '#',
                                'submenuItems' => [
                                    [
                                        'label' => 'Submenu 1 Item 1',
                                        'url' => '#',
                                    ],
                                    [
                                        'label' => 'Submenu 1 Item 2',
                                        'url' => '#',
                                        'submenuItems' => [
                                            [
                                                'label' => 'Submenu 1.1 Item 1',
                                                'url' => '#',
                                            ],
                                            [
                                                'label' => 'Submenu 1.1 Item 2 with checkMethod that fails',
                                                'url' => '#',
                                                'checkMethod' => 'alwaysFail',
                                            ],
                                            [
                                                'label' => 'Submenu 1.1 Item 3',
                                                'url' => '#',
                                            ],
                                        ],
                                    ],
                                    [
                                        'label' => 'Submenu 1 Item 3 with checkMethod that fails',
                                        'url' => '#',
                                        'checkMethod' => 'alwaysFail',
                                    ],
                                ],
                            ],
                            [
                                'label' => 'Unlinked Parent Item 2',
                                'submenuItems' => [
                                    [
                                        'label' => 'Submenu 2 Item 1',
                                        'url' => '#',
                                    ],
                                    [
                                        'label' => 'Submenu 2 Item 2',
                                        'url' => '#',
                                    ],
                                ],
                            ],
                            [
                                'label' => 'Regular Item 3',
                                'url' => '#',
                            ],
                        ],
                    ],
                ],
            ],
            ['SiteMap']
        );
    }

    /**
     * Load Site Map page.
     *
     * @param ?Session $session Mink session (will be automatically established if not provided).
     *
     * @return Element
     */
    protected function getSiteMapPage(?Session $session = null): Element
    {
        $session ??= $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/SiteMap/Home');
        return $session->getPage();
    }

    /**
     * Test that the page is working with default settings.
     *
     * @return void
     */
    public function testPageWorks(): void
    {
        $page = $this->getSiteMapPage();
        $this->assertStringContainsString(
            'Site Map',
            $this->findCssAndGetText($page, '#content > h1:nth-child(1)')
        );
        $this->assertStringContainsString(
            'Home Page',
            $this->findCssAndGetText($page, '#content > h2:nth-child(2) > a:nth-child(1)')
        );
        $this->assertStringEndsWith(
            '/Content/askLibrary',
            $this->findCss($page, '#content')->findLink('Ask a Librarian')->getAttribute('href')
        );
    }

    /**
     * Test that when disabled a 'Page not found' message is shown and status
     * code 404 is returned.
     *
     * @return void
     */
    public function testDisabledPage(): void
    {
        $this->changeConfigs(
            [
                'config' => [
                    'Site' => [
                        'siteMapPageEnabled' => false,
                    ],
                ],
            ]
        );
        $session = $this->getMinkSession();
        $page = $this->getSiteMapPage($session);
        $this->assertEquals(
            404,
            $session->getStatusCode()
        );
        $this->assertStringContainsString(
            'Page not found',
            $this->findCssAndGetText($page, '#content')
        );
    }

    /**
     * Test submenu items and nested submenus with linked and unlinked parents
     * and with failing checkMethods.
     *
     * @return void
     */
    public function testSubmenuItems(): void
    {
        $this->applySiteMapConfigWithSubmenuItems();
        $page = $this->getSiteMapPage();
        $this->assertStringContainsString(
            'Submenu Items Test',
            $this->findCssAndGetText($page, '#content > h2:nth-child(3)')
        );
        $this->assertStringContainsString(
            'Submenu 1 Item 1',
            $this->findCssAndGetText(
                $page,
                '#content > ul:nth-child(4) > li:nth-child(1) > ul:nth-child(2) > li:nth-child(1) > a:nth-child(1)'
            )
        );
        $this->assertStringNotContainsString(
            'Submenu 1.1 Item 2 with checkMethod that fails',
            $this->findCssAndGetText(
                $page,
                '#content > ul:nth-child(4) > li:nth-child(1) > ul:nth-child(2) > li:nth-child(2) > ul:nth-child(2)'
            )
        );
        $this->assertStringContainsString(
            'Submenu 1.1 Item 3',
            $this->findCssAndGetText(
                $page,
                '#content > ul:nth-child(4) > li:nth-child(1) > ul:nth-child(2) > li:nth-child(2) > ul:nth-child(2) '
                    . '> li:nth-child(2) > a:nth-child(1)'
            )
        );
        $this->assertStringNotContainsString(
            'Submenu 1 Item 3 with checkMethod that fails',
            $this->findCssAndGetText($page, '#content > ul:nth-child(4) > li:nth-child(1) > ul:nth-child(2)')
        );
        $this->assertStringContainsString(
            'Unlinked Parent Item 2',
            $this->findCssAndGetText($page, '#content > ul:nth-child(4) > li:nth-child(2)')
        );
        $this->assertStringContainsString(
            'Submenu 2 Item 1',
            $this->findCssAndGetText(
                $page,
                '#content > ul:nth-child(4) > li:nth-child(2) > ul:nth-child(1) > li:nth-child(1) > a:nth-child(1)'
            )
        );
        $this->assertStringContainsString(
            'Regular Item 3',
            $this->findCssAndGetText($page, '#content > ul:nth-child(4) > li:nth-child(3) > a:nth-child(1)')
        );
    }
}
