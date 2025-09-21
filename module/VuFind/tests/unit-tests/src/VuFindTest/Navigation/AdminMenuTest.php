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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Tests
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Navigation;

use VuFind\Navigation\AdminMenu;

/**
 * Admin menu tests.
 *
 * @category VuFind
 * @package  Tests
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class AdminMenuTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test that the menu is the default menu if configuration is missing.
     *
     * @return void
     */
    public function testMissingConfiguration()
    {
        $this->assertEquals(
            $this->getAdminMenu()->getMenu(),
            $this->getAdminMenu(AdminMenu::getDefaultMenuConfig())->getMenu()
        );
    }

    /**
     * Test the default menu when all check methods return false.
     *
     * @return void
     */
    public function testDefaultMenuAllCheckMethodsReturnFalse()
    {
        $menu = $this->getAdminMenu(
            AdminMenu::getDefaultMenuConfig(),
            $this->getCheckMethods(false)
        )->getMenu();
        $this->assertCount(7, $menu['Admin']['MenuItems']);
    }

    /**
     * Get mock AdminMenu.
     *
     * @param array $config       Configuration to use
     * @param array $checkMethods Values to return for specific check methods
     *
     * @return AdminMenu
     */
    protected function getAdminMenu(
        array $config = [],
        array $checkMethods = [],
    ): AdminMenu {
        $adminMenu = $this->getMockBuilder(AdminMenu::class)
            ->setConstructorArgs(
                [
                    $config,
                    false,
                ]
            )
            ->onlyMethods(array_keys($this->getCheckMethods()))
            ->getMock();
        foreach ($this->getCheckMethods() as $checkMethod => $default) {
            $adminMenu->method($checkMethod)->willReturn($checkMethods[$checkMethod] ?? $default);
        }
        return $adminMenu;
    }

    /**
     * Get all check methods.
     *
     * @param bool $value Value for the check methods to return
     *
     * @return array
     */
    protected function getCheckMethods(bool $value = true): array
    {
        return [
            'checkShowOverdrive' => $value,
        ];
    }
}
