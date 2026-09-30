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
use Symfony\Component\Yaml\Yaml;
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
                    'Feedback' => [
                        'tab_enabled' => true,
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
        $yaml = <<<YAML
            "@parent_yaml": false
            
            GroupWithoutLabelAndWithoutSubmenuItems:
              MenuItems:
                - label: 'Item 1 rendered as heading'
                  url: '#'
                - label: 'Item 2 rendered as heading'
                  url: '#'
            
            GroupWithoutLabelAndWithSubmenuItems:
              MenuItems:
                - label: 'Item 1 rendered as heading'
                  url: '#'
                - label: 'Item 2 rendered as heading'
                  url: '#'
                  submenuItems:
                    - label: 'Submenu Item 1 rendered as list item'
                      url: '#'
                    - label: 'Submenu Item 2 rendered as list item'
                      url: '#'
                - label: 'Item 3 rendered as heading'
                  url: '#'
            
            GroupWithLabelAndWithoutSubmenuItems:
              label: 'Group label rendered as heading'
              MenuItems:
                - label: 'Item 1 rendered as list item'
                  url: '#'
                - label: 'Item 2 rendered as list item'
                  url: '#'
            
            GroupWithLabelAndWithSubmenuItems:
              label: 'Group label rendered as heading'
              MenuItems:
                - label: 'Item 1 rendered as list item'
                  url: '#'
                - label: 'Item 2 rendered as list item'
                  url: '#'
                  submenuItems:
                    - label: 'Submenu Item 1 rendered as list item'
                      url: '#'
                    - label: 'Submenu Item 2 rendered as list item'
                      url: '#'
                - label: 'Item 3 rendered as list item'
                  url: '#'
            
            NestedSubmenuItems:
              label: 'Nested Submenu Items'
              MenuItems:
                - label: 'Linked Parent Item 1'
                  route: content-page
                  routeParams:
                    page: askLibrary
                  submenuItems:
                    - label: 'Submenu 1 Item 1'
                      route: content-page
                      routeParams:
                        page: askLibrary
                    - label: 'Submenu 1 Item 2'
                      url: '#'
                      submenuItems:
                        - label: 'Submenu 1.1 Item 1'
                          url: '#'
                        - label: 'Submenu 1.1 Item 2 with checkMethod that fails'
                          url: '#'
                          checkMethod: alwaysFail
                        - label: 'Submenu 1.1 Item 3'
                          url: '#'
                    - label: 'Submenu 1 Item 3 with checkMethod that fails'
                      url: '#'
                      checkMethod: alwaysFail
                    - label: 'Submenu 1 Item 4'
                      url: '#'
                - label: 'Unlinked Parent Item 2'
                  submenuItems:
                    - label: 'Submenu 2 Item 1'
                      url: '#'
                    - label: 'Submenu 2 Item 2'
                      url: '#'
                - label: 'Regular Item 3'
                  url: '#'
            YAML;
        $this->changeYamlConfigs(['SiteMap' => Yaml::parse($yaml)], ['SiteMap']);
    }

    /**
     * Apply a non-default HeaderBar.yaml configuration with excluded groups and items.
     *
     * @return void
     */
    protected function applyHeaderBarConfigWithExcludedGroupsAndItems(): void
    {
        $yaml = <<<YAML
            "@parent_yaml": false
            
            ExcludedGroup:
              label: 'Group 1 excluded from site map page'
              MenuItems:
                - label: 'Group 1 Item 1'
                  url: '#'
              excludeFromSiteMapPage: true
            
            GroupWithOneExcludedItem:
              label: 'Group 2 with one item excluded from site map page'
              MenuItems:
                - label: 'Group 2 Item 1'
                  url: '#'
                - label: 'Group 2 Item 2 excluded from site map page'
                  url: '#'
                  excludeFromSiteMapPage: true
                - label: 'Group 2 Item 3'
                  url: '#'
            
            GroupWithAllItemsExcluded:
              label: 'Group 3 with all items excluded from site map page'
              MenuItems:
                - label: 'Group 3 Item 1 excluded from site map page'
                  url: '#'
                  excludeFromSiteMapPage: true
                - label: 'Group 3 Item 2 excluded from site map page'
                  url: '#'
                  excludeFromSiteMapPage: true
                - label: 'Group 3 Item 3 excluded from site map page'
                  url: '#'
                  excludeFromSiteMapPage: true
            YAML;
        $this->changeYamlConfigs(['HeaderBar' => Yaml::parse($yaml)], ['HeaderBar']);
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

        // Item with siteMapPageTemplate is rendered using that template.
        $this->assertInstanceOf(
            Element::class,
            $this->findCss($page, '#content')->findLink('Feedback')
        );
        $this->assertNull(
            $this->findCss($page, '#content')->findLink('Feedback')->getAttribute('id')
        );

        // Item with excludeFromSiteMapPage: true not is shown.
        $this->assertStringNotContainsString(
            'Language',
            $this->findCssAndGetText($page, '#content')
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
     * Test rendering of group labels, menu items and submenu items.
     *
     * @return void
     */
    public function testLabelAndItemRendering(): void
    {
        $this->applySiteMapConfigWithSubmenuItems();
        $page = $this->getSiteMapPage();

        // Group without label and without submenu items.
        $this->assertStringContainsString(
            'Item 1 rendered as heading',
            $this->findCssAndGetText($page, '#content > h2:nth-child(2) > a:nth-child(1)')
        );
        $this->assertStringContainsString(
            'Item 2 rendered as heading',
            $this->findCssAndGetText($page, '#content > h2:nth-child(3) > a:nth-child(1)')
        );

        // Group without label and with submenu items.
        $this->assertStringContainsString(
            'Item 1 rendered as heading',
            $this->findCssAndGetText($page, '#content > h2:nth-child(4) > a:nth-child(1)')
        );
        $this->assertStringContainsString(
            'Item 2 rendered as heading',
            $this->findCssAndGetText($page, '#content > h2:nth-child(5) > a:nth-child(1)')
        );
        $this->assertStringContainsString(
            'Submenu Item 1 rendered as list item',
            $this->findCssAndGetText($page, '#content > ul:nth-child(6) > li:nth-child(1) > a:nth-child(1)')
        );
        $this->assertStringContainsString(
            'Item 3 rendered as heading',
            $this->findCssAndGetText($page, '#content > h2:nth-child(7) > a:nth-child(1)')
        );

        // Group with label and without submenu items.
        $this->assertStringContainsString(
            'Group label rendered as heading',
            $this->findCssAndGetText($page, '#content > h2:nth-child(8)')
        );
        $this->assertStringContainsString(
            'Item 1 rendered as list item',
            $this->findCssAndGetText($page, '#content > ul:nth-child(9) > li:nth-child(1) > a:nth-child(1)')
        );

        // Group with label and with submenu items.
        $this->assertStringContainsString(
            'Group label rendered as heading',
            $this->findCssAndGetText($page, '#content > h2:nth-child(10)')
        );
        $this->assertStringContainsString(
            'Item 1 rendered as list item',
            $this->findCssAndGetText($page, '#content > ul:nth-child(11) > li:nth-child(1) > a:nth-child(1)')
        );
        $this->assertStringContainsString(
            'Submenu Item 1 rendered as list item',
            $this->findCssAndGetText(
                $page,
                '#content > ul:nth-child(11) > li:nth-child(2) > ul:nth-child(2) > li:nth-child(1) > a:nth-child(1)'
            )
        );
        $this->assertStringContainsString(
            'Item 3 rendered as list item',
            $this->findCssAndGetText($page, '#content > ul:nth-child(11) > li:nth-child(3) > a:nth-child(1)')
        );
    }

    /**
     * Test nested submenu items with linked and unlinked parents and with
     * failing checkMethods.
     *
     * @return void
     */
    public function testNestedSubmenuItems(): void
    {
        $this->applySiteMapConfigWithSubmenuItems();
        $page = $this->getSiteMapPage();
        $this->assertStringContainsString(
            'Nested Submenu Items',
            $this->findCssAndGetText($page, '#content > h2:nth-child(12)')
        );
        $this->assertStringContainsString(
            'Submenu 1 Item 1',
            $this->findCssAndGetText(
                $page,
                '#content > ul:nth-child(13) > li:nth-child(1) > ul:nth-child(2) > li:nth-child(1) > a:nth-child(1)'
            )
        );
        $this->assertStringContainsString(
            'Submenu 1.1 Item 3',
            $this->findCssAndGetText(
                $page,
                '#content > ul:nth-child(13) > li:nth-child(1) > ul:nth-child(2) > li:nth-child(2) '
                    . '> ul:nth-child(2) > li:nth-child(2) > a:nth-child(1)'
            )
        );
        $this->assertStringContainsString(
            'Submenu 1 Item 4',
            $this->findCssAndGetText(
                $page,
                '#content > ul:nth-child(13) > li:nth-child(1) > ul:nth-child(2) > li:nth-child(3) > a:nth-child(1)'
            )
        );
        $this->assertStringContainsString(
            'Unlinked Parent Item 2',
            $this->findCssAndGetText($page, '#content > ul:nth-child(13) > li:nth-child(2)')
        );
        $this->assertStringContainsString(
            'Submenu 2 Item 1',
            $this->findCssAndGetText(
                $page,
                '#content > ul:nth-child(13) > li:nth-child(2) > ul:nth-child(2) > li:nth-child(1) > a:nth-child(1)'
            )
        );
        $this->assertStringContainsString(
            'Regular Item 3',
            $this->findCssAndGetText($page, '#content > ul:nth-child(13) > li:nth-child(3) > a:nth-child(1)')
        );
    }

    /**
     * Test groups and items excluded from site map page.
     *
     * @return void
     */
    public function testExcludedGroupsAndItems(): void
    {
        $this->applyHeaderBarConfigWithExcludedGroupsAndItems();
        $page = $this->getSiteMapPage();
        $this->assertStringNotContainsString(
            'Group 1 excluded from site map page',
            $this->findCssAndGetText($page, '#content')
        );
        $this->assertStringContainsString(
            'Group 2 Item 1',
            $this->findCssAndGetText($page, '#content')
        );
        $this->assertStringNotContainsString(
            'Group 2 Item 2',
            $this->findCssAndGetText($page, '#content')
        );
        $this->assertStringContainsString(
            'Group 2 Item 3',
            $this->findCssAndGetText($page, '#content')
        );
        $this->assertStringNotContainsString(
            'Group 3 with all items excluded from site map page',
            $this->findCssAndGetText($page, '#content')
        );
    }
}
