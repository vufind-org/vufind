<?php

/**
 * Mink test class for the HeaderBar section plugin.
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

use Symfony\Component\Yaml\Yaml;

/**
 * Mink test class for the HeaderBar section plugin.
 *
 * @category VuFind
 * @package  Tests
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class HeaderBarTest extends \VuFindTest\Integration\MinkTestCase
{
    /**
     * Apply a non-default HeaderBar.yaml configuration that includes submenu items.
     *
     * @return void
     */
    protected function applyHeaderBarConfigWithSubmenuItems(): void
    {
        $yaml = <<<YAML
            Header:
              "@parent_yaml": false
              MenuItems:
                - label: 'Dropdown Menu'
                  name: submenuitems-test
                  submenuItems:
                    - label: 'Submenu Item 1'
                      url: '#'
                    - label: 'Submenu Item 2'
                      description: 'Submenu Item 2 Description'
                      url: '#'
                    - label: 'Submenu Item 3'
                      url: '#'
            YAML;
        $this->changeYamlConfigs(['HeaderBar' => Yaml::parse($yaml)], ['HeaderBar']);
    }

    /**
     * Test that the page is working with default settings.
     *
     * @return void
     */
    public function testPageWorks(): void
    {
        $page = $this->getSearchHomePage();
        $this->assertStringContainsString(
            'Login',
            $this->findCssAndGetText($page, 'html > body > header > nav #loginOptions > a')
        );
    }

    /**
     * Test submenu items.
     *
     * @return void
     */
    public function testSubmenuItems(): void
    {
        $this->applyHeaderBarConfigWithSubmenuItems();
        $page = $this->getSearchHomePage();
        $this->assertStringContainsString(
            'Submenu Item 1',
            $this->findCssAndGetText(
                $page,
                'html > body > header > nav .submenuitems-test > ul > li:nth-child(1) > a'
            )
        );
        $this->assertStringContainsString(
            'Submenu Item 2',
            $this->findCssAndGetText(
                $page,
                'html > body > header > nav .submenuitems-test > ul > li:nth-child(2) > a > span:nth-child(1)'
            )
        );
        $this->assertStringContainsString(
            'Submenu Item 2 Description',
            $this->findCssAndGetText(
                $page,
                'html > body > header > nav .submenuitems-test > ul > li:nth-child(2) > a > span.description'
            )
        );
    }
}
