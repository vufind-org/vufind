<?php

/**
 * Site map tests.
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
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Navigation;

use VuFind\Navigation\SiteMap;
use VuFindTest\Unit\AbstractSectionTestCase;

/**
 * Site map tests.
 *
 * @category VuFind
 * @package  Tests
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SiteMapTest extends AbstractSectionTestCase
{
    /**
     * Test that the default configuration file matches the default
     * configuration returned by the section class.
     *
     * @return void
     */
    public function testDefaultConfiguration()
    {
        $container = $this->getContainerWithSectionRelatedServices();
        $this->assertEquals(
            $this->getSiteMap($container)->getSectionConfig(),
            $this->getSiteMap($container, SiteMap::getDefaultMenuConfig())->getSectionConfig()
        );
    }

    /**
     * Test that the menu is the default menu if configuration is missing and
     * that the section key in the default configuration is working.
     *
     * @return void
     */
    public function testMissingConfigurationAndSectionKey()
    {
        $container = $this->getContainerWithSectionRelatedServices();
        $this->assertEquals(
            $this->getSiteMap($container, [])->getMenu(),
            $this->getSiteMap($container, SiteMap::getDefaultMenuConfig())->getMenu()
        );
    }

    /**
     * Test that a section key specifying a SiteMap plugin section is caught.
     *
     * @return void
     */
    public function testSectionKeySpecifyingSiteMap()
    {
        $this->expectExceptionMessage('Specifying SiteMap plugin sections is not possible');
        $container = $this->getContainerWithSectionRelatedServices();
        $config = [
            'SiteMap' => [
                'section' => 'SiteMap',
            ],
        ];
        $this->getSiteMap($container, $config)->getMenu();
    }

    /**
     * Test that clashing group keys are caught when section key is used.
     *
     * @return void
     */
    public function testClashingGroupKeysFromUsingSectionKey()
    {
        $this->expectExceptionMessage('Group key clash in configuration: Header');
        $container = $this->getContainerWithSectionRelatedServices();
        $config = [
            'Header' => [
                'label' => 'This group clashes with the default HeaderBar configuration',
                'MenuItems' => [
                    [
                        'label' => 'Dummy item',
                        'url' => '#',
                    ],
                ],
            ],
            'DefaultHeader' => [
                'section' => 'HeaderBar',
            ],
        ];
        $this->getSiteMap($container, $config)->getMenu();
    }
}
