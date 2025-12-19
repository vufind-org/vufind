<?php

/**
 * Admin menu tests.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024.
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

use VuFind\Exception\BadConfig;
use VuFind\Navigation\AdminMenu;
use VuFindTest\Unit\AbstractSectionTestCase;

/**
 * Admin menu tests.
 *
 * @category VuFind
 * @package  Tests
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class AdminMenuTest extends AbstractSectionTestCase
{
    /**
     * Test that the menu is the default menu if configuration is missing.
     *
     * @return void
     */
    public function testMissingConfiguration()
    {
        $container = $this->getContainerWithSectionRelatedServices();
        $this->assertEquals(
            $this->getAdminMenu($container)->getMenu(),
            $this->getAdminMenu($container, AdminMenu::getDefaultMenuConfig())->getMenu()
        );
    }

    /**
     * Test the default menu when all check methods return false.
     *
     * @return void
     */
    public function testDefaultMenuAllCheckMethodsReturnFalse()
    {
        $container = $this->getContainerWithSectionRelatedServices();
        $menu = $this->getAdminMenu(
            $container,
            AdminMenu::getDefaultMenuConfig(),
            $this->getAdminMenuCheckMethods(false)
        )->getMenu();
        $this->assertCount(7, $menu['Admin']['MenuItems']);
    }

    /**
     * Data provider for testRequiredConfiguration
     *
     * @return \Iterator<string, array>
     */
    public static function requiredConfigurationProvider(): \Iterator
    {
        yield 'Missing menu item settings' => [
            [
                'Admin' => [
                    'MenuItems' => [[]],
                ],
            ],
            BadConfig::class,
            'Missing required setting: label',
        ];
    }

    /**
     * Test required configuration.
     *
     * @param array   $config                 Account menu configuration
     * @param string  $expectedExceptionClass Expected exception class
     * @param ?string $expectedExceptionMsg   Expected exception message
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('requiredConfigurationProvider')]
    public function testRequiredConfiguration(
        array $config,
        string $expectedExceptionClass,
        ?string $expectedExceptionMsg = null
    ): void {
        $this->expectException($expectedExceptionClass);
        if ($expectedExceptionMsg) {
            $this->expectExceptionMessage($expectedExceptionMsg);
        }
        $container = $this->getContainerWithSectionRelatedServices();
        $this->getAdminMenu($container, $config);
    }
}
