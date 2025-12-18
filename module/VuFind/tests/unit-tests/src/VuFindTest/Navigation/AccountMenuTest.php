<?php

/**
 * Account menu tests.
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public
 *           License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Navigation;

use VuFind\Exception\BadConfig;
use VuFind\Navigation\AccountMenu;
use VuFindTest\Section\AbstractSectionTestCase;

/**
 * Account menu tests.
 *
 * @category VuFind
 * @package  Tests
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public
 *           License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class AccountMenuTest extends AbstractSectionTestCase
{
    /**
     * Test that the menu is the default menu if configuration is missing.
     *
     * @return void
     */
    public function testMissingConfiguration()
    {
        $this->assertEquals(
            $this->getAccountMenu()->getMenu(),
            $this->getAccountMenu(AccountMenu::getDefaultMenuConfig())->getMenu()
        );
    }

    /**
     * Test the default menu when all check methods return false.
     *
     * @return void
     */
    public function testDefaultMenuAllCheckMethodsReturnFalse()
    {
        $menu = $this->getAccountMenu(
            AccountMenu::getDefaultMenuConfig(),
            $this->getAccountMenuCheckMethods(false)
        )->getMenu();
        $this->assertCount(1, $menu['Account']['MenuItems']);
        $this->assertEquals('Profile', reset($menu['Account']['MenuItems'])['label']);
    }

    /**
     * Test backward compatibility for old configurations.
     *
     * @return void
     */
    public function testBackwardCompatibilityForOldConfigurations()
    {
        $menu = $this->getAccountMenu($this->getOldDefaultMenuConfig())->getMenu();
        $this->assertCount(12, $menu['Account']['MenuItems']);
    }

    /**
     * Get old default menu configuration.
     *
     * @return array
     */
    protected function getOldDefaultMenuConfig(): array
    {
        return [
            'MenuItems' => [
                [
                    'name' => 'favorites',
                    'label' => 'saved_items',
                    'route' => 'myresearch-favorites',
                    'icon' => 'user-favorites',
                    'checkMethod' => 'checkFavorites',
                ],
                [
                    'name' => 'checkedout',
                    'label' => 'Checked Out Items',
                    'route' => 'myresearch-checkedout',
                    'icon' => 'user-checked-out',
                    'status' => true,
                    'checkMethod' => 'checkCheckedout',
                ],
                [
                    'name' => 'historicloans',
                    'label' => 'Loan History',
                    'route' => 'checkouts-history',
                    'icon' => 'user-loan-history',
                    'checkMethod' => 'checkHistoricloans',
                ],
                [
                    'name' => 'holds',
                    'label' => 'Holds and Recalls',
                    'route' => 'holds-list',
                    'icon' => 'user-holds',
                    'status' => true,
                    'checkMethod' => 'checkHolds',
                ],
                [
                    'name' => 'storageRetrievalRequests',
                    'label' => 'Storage Retrieval Requests',
                    'route' => 'myresearch-storageretrievalrequests',
                    'icon' => 'user-storage-retrievals',
                    'status' => true,
                    'checkMethod' => 'checkStorageRetrievalRequests',
                ],
                [
                    'name' => 'ILLRequests',
                    'label' => 'Interlibrary Loan Requests',
                    'route' => 'myresearch-illrequests',
                    'icon' => 'user-ill-requests',
                    'status' => true,
                    'checkMethod' => 'checkILLRequests',
                ],
                [
                    'name' => 'fines',
                    'label' => 'Fines',
                    'route' => 'myresearch-fines',
                    'status' => true,
                    'checkMethod' => 'checkFines',
                    'iconMethod' => 'finesIcon',
                ],
                [
                    'name' => 'profile',
                    'label' => 'Profile',
                    'route' => 'myresearch-profile',
                    'icon' => 'profile',
                ],
                [
                    'name' => 'librarycards',
                    'label' => 'Library Cards',
                    'route' => 'librarycards-home',
                    'icon' => 'barcode',
                    'checkMethod' => 'checkLibraryCards',
                ],
                [
                    'name' => 'dgcontent',
                    'label' => 'Overdrive Content',
                    'route' => 'overdrive-mycontent',
                    'icon' => 'overdrive',
                    'checkMethod' => 'checkOverdrive',
                ],
                [
                    'name' => 'history',
                    'label' => 'Search History',
                    'route' => 'search-history',
                    'icon' => 'search',
                    'checkMethod' => 'checkHistory',
                ],
                [
                    'name' => 'logout',
                    'label' => 'Log Out',
                    'route' => 'myresearch-logout',
                    'icon' => 'sign-out',
                    'checkMethod' => 'checkLogout',
                ],
            ],
        ];
    }

    /**
     * Data provider for testRequiredConfiguration
     *
     * @return \Iterator<string, array>
     */
    public static function requiredConfigurationProvider(): \Iterator
    {
        yield 'Missing group settings' => [
            ['Account' => []],
        ];
        yield 'Missing menu item settings' => [
            [
                'Account' => [
                    'label' => 'Test',
                    'MenuItems' => [[]],
                ],
            ],
        ];
    }

    /**
     * Test required configuration.
     *
     * @param array  $config                      Account menu configuration
     * @param string $expectedExceptionClass      Expected exception class
     * @param string $expectedExceptionMsgMatches Expected exception message regexp
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('requiredConfigurationProvider')]
    public function testRequiredConfiguration(
        array $config,
        string $expectedExceptionClass = BadConfig::class,
        string $expectedExceptionMsgMatches = '/^Missing required setting: /'
    ) {
        $this->expectException($expectedExceptionClass);
        $this->expectExceptionMessageMatches($expectedExceptionMsgMatches);
        $this->getAccountMenu($config);
    }
}
