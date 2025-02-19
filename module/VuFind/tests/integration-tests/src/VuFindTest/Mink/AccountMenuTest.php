<?php

/**
 * Mink account ajax menu test class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2011.
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

use function count;

/**
 * Mink account ajax menu test class.
 *
 * Class must be final due to use of "new static()" by LiveDatabaseTrait.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
final class AccountMenuTest extends \VuFindTest\Integration\MinkTestCase
{
    use \VuFindTest\Feature\LiveDatabaseTrait;
    use \VuFindTest\Feature\UserCreationTrait;
    use \VuFindTest\Feature\DemoDriverTestTrait;

    /**
     * Standard setup method.
     *
     * @return void
     */
    public static function setUpBeforeClass(): void
    {
        static::failIfDataExists();
    }

    /**
     * Create a specific state in the account ajax storage.
     *
     * Cleared when browser closes.
     * If run multiple times in one test function, manually clear cache.
     *
     * @param array $states States to set in JS session storage
     *
     * @return void
     */
    protected function setJSStorage($states)
    {
        $session = $this->getMinkSession();
        $this->waitForPageLoad($session->getPage());
        $js = '';
        $theme = $this->getCurrentTheme();
        foreach ($states as $key => $state) {
            $themeState = [$theme => $state];
            $js .= 'sessionStorage.setItem(\'vf-account-status-' . $key . '\', \'' . json_encode($themeState) . '\');';
        }
        $session->evaluateScript($js);
    }

    /**
     * Get associative array of storage state
     *
     * @return array
     */
    protected function getJSStorage()
    {
        $session = $this->getMinkSession();
        return $session->evaluateScript(
            'return {' .
            '  "checkedOut": sessionStorage.getItem("vf-account-status-checkedOut"),' .
            '  "fines": sessionStorage.getItem("vf-account-status-fines"),' .
            '  "holds": sessionStorage.getItem("vf-account-status-holds"),' .
            '  "illRequests": sessionStorage.getItem("vf-account-status-illRequests"),' .
            '  "storageRetrievalRequests": sessionStorage.getItem("vf-account-status-storageRetrievalRequests"),' .
            '}'
        );
    }

    /**
     * Establish the fines in the session that will be used by various tests below...
     *
     * @return object
     */
    protected function setUpFinesEnvironment()
    {
        // Seed some fines
        $this->setJSStorage(['fines' => ['value' => 30.5, 'display' => '$30.50']]);
        $session = $this->getMinkSession();
        $session->reload();
        $this->waitForPageLoad($session->getPage());
        return $session->getPage();
    }

    /**
     * Data provider for menu configuration tests
     *
     * @return array
     */
    public static function menuConfigurationProvider(): array
    {
        return [
            'no ajax, no dropdown' => [
                false,
                false,
                0,
            ],
            'ajax, no dropdown' => [
                true,
                false,
                1,
            ],
            'no ajax, dropdown' => [
                false,
                true,
                0,
            ],
            'ajax, dropdown' => [
                true,
                true,
                2,
            ],
        ];
    }

    /**
     * Test the menu configuration.
     *
     * @param bool $ajax                Enable account ajax?
     * @param bool $dropdown            Enable navbar dropdown menu?
     * @param int  $expectedStatusCount How many instances of status badge to expect
     *
     * @dataProvider menuConfigurationProvider
     *
     * @return void
     */
    public function testMenuConfiguration(bool $ajax, bool $dropdown, int $expectedStatusCount)
    {
        $this->changeConfigs(
            [
                'Demo' => $this->getDemoIniOverrides(),
                'config' => [
                    'Catalog' => ['driver' => 'Demo'],
                    'Authentication' => [
                        'method' => 'ILS',
                        'enableAjax' => $ajax,
                        'enableDropdown' => $dropdown,
                    ],
                ],
            ]
        );

        $page = $this->login('catuser', 'catpass')->getPage();
        $menu = $page->findAll('css', '#login-dropdown');
        $this->assertCount($dropdown ? 1 : 0, $menu);
        $this->findCss($page, '.account-menu .fines');
        $this->assertEqualsWithTimeout(
            $expectedStatusCount,
            function () use ($page) {
                return count($page->findAll('css', '.account-menu .fines-status'));
            }
        );
    }

    /**
     * Set some values and delete them to test VuFind.account.clearCache
     * with parameters.
     *
     * @return void
     */
    public function testIndividualCacheClearing()
    {
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl());
        // Seed some fines
        $this->setJSStorage(['fines' => ['value' => 30.5, 'display' => '$30.50']]);
        // Clear different cache
        $session->evaluateScript('VuFind.account.clearCache("holds");');
        $storage = $this->getJSStorage();
        $this->assertNotNull($storage['fines']);
        // Clear correct cache
        $session->evaluateScript('VuFind.account.clearCache("fines");');
        $storage = $this->getJSStorage();
        $this->assertNull($storage['fines']);
    }

    /**
     * Set some values and delete them to test VuFind.account.clearCache
     * without parameters.
     *
     * @return void
     */
    public function testGlobalCacheClearing()
    {
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl());
        $page = $session->getPage();

        // This needs a valid list of caches, so create a user without ILS access:
        $this->clickCss($page, '#loginOptions a');
        $this->clickCss($page, '.modal-body .createAccountLink');
        $this->fillInAccountForm($page);
        $this->clickCss($page, '.modal-body .btn.btn-primary');
        $this->waitForPageLoad($page);

        $page = $this->setUpFinesEnvironment();
        // Check storage
        $storage = $this->getJSStorage();
        $this->assertNotNull($storage['fines']);
        // Clear all cached data
        $session->evaluateScript('VuFind.account.clearCache();');
        // Check storage again
        $storage = $this->getJSStorage();
        $this->assertNull($storage['fines']);
    }

    /**
     * Data provider for testAccountIcon
     *
     * @return array
     */
    public static function accountIconProvider(): array
    {
        return [
            'no icon' => [
                [
                    // No fines
                    ['fines' => ['total' => 0, 'display' => 'ZILTCH']],
                    // Holds in transit only
                    ['holds' => ['in_transit' => 1, 'available' => 0, 'other' => 0]],
                    // ILL Requests in transit only
                    ['illRequests' => ['in_transit' => 1, 'available' => 0, 'other' => 0]],
                    // Storage Retrievals in transit only
                    ['storageRetrievalRequests' => ['in_transit' => 1, 'available' => 0, 'other' => 0]],
                ],
                '.account-status-none',
            ],
            'good' => [
                [
                    // Holds available
                    ['holds' => ['in_transit' => 0, 'available' => 1, 'level' => 1]],
                    // ILL Requests available
                    ['illRequests' => ['in_transit' => 0, 'available' => 1, 'level' => 1]],
                    // Storage Retrievals available
                    ['storageRetrievalRequests' => ['in_transit' => 0, 'available' => 1, 'level' => 1]],
                ],
                '.account-status-good',
            ],
            'warning' => [
                [
                    ['checkedOut' => ['warn' => 1, 'level' => 2]],
                ],
                '.account-status-warning',
            ],
            'danger' => [
                [
                    // User has fines
                    ['fines' => ['value' => 1000000, 'display' => '$...yikes', 'level' => 3]],
                    // Checkedout overdue
                    ['checkedOut' => ['overdue' => 1, 'level' => 3]],
                ],
                '.account-status-danger',
            ],
            'danger overrides warning' => [
                [['checkedOut' => ['warn' => 2, 'overdue' => 1, 'level' => 3]]],
                '.account-status-danger',
            ],
            'danger overrides good' => [
                [
                    [
                        'checkedOut' => ['overdue' => 1, 'level' => 3],
                        'holds' => ['available' => 1, 'level' => 1],
                    ],
                ],
                '.account-status-danger',
            ],
            'warning overrides good' => [
                [
                    [
                        'checkedOut' => ['warn' => 1, 'level' => 2],
                        'holds' => ['available' => 1, 'level' => 1],
                    ],
                ],
                '.account-status-warning',
            ],
            'good overrides none' => [
                [
                    [
                        'holds' => ['available' => 1, 'level' => 1],
                        'fines' => ['total' => 0, 'display' => 'none', 'level' => 0],
                    ],
                ],
                '.account-status-good',
            ],
        ];
    }

    /**
     * Abstracted test to set storage and check if the icon is correct
     *
     * @param array  $storage    Array of storage values to test
     * @param string $checkClass Icon class to check
     *
     * @dataProvider accountIconProvider
     *
     * @return void
     */
    public function testAccountIcon(array $storage, string $checkClass): void
    {
        $this->changeConfigs(
            [
                'config' => [
                    'Authentication' => [
                        'enableAjax' => true,
                    ],
                    'Catalog' => ['driver' => 'Demo'],
                ],
            ]
        );
        $session = $this->login();
        foreach ($storage as $item) {
            $this->setJSStorage($item);
            $session->reload();
            $page = $session->getPage();
            $this->findCss($page, '#account-icon ' . $checkClass);
            foreach (array_keys($item) as $key) {
                $session->evaluateScript('VuFind.account.clearCache("' . $key . '");');
            }
        }
    }

    /**
     * Test status badges
     *
     * @return void
     */
    public function testStatusBadges(): void
    {
        $this->changeConfigs(
            [
                'Demo' => $this->getDemoIniOverrides(),
                'config' => [
                    'Catalog' => ['driver' => 'Demo'],
                    'Authentication' => [
                        'method' => 'ILS',
                        'enableAjax' => true,
                    ],
                ],
            ]
        );

        $session = $this->login('catuser', 'catpass');
        $page = $session->getPage();
        $this->waitForPageLoad($page);

        // Checkouts
        $checkoutsStatus = $this->findCss($page, '.myresearch-menu .checkedout-status');
        $this->assertEquals(
            '1',
            $this->findCssAndGetText($checkoutsStatus, '.badge.account-info')
        );
        $this->assertEquals(
            'Items due later: 1 ,',
            $this->findCssAndGetText($checkoutsStatus, '.visually-hidden, .sr-only')
        );

        $this->assertEquals(
            '2',
            $this->findCssAndGetText($checkoutsStatus, ' .badge.account-warning')
        );
        $this->assertEquals(
            'Items due soon: 2 ,',
            $this->findCssAndGetText($checkoutsStatus, '.visually-hidden, .sr-only', null, 1)
        );

        $this->assertEquals(
            '3',
            $this->findCssAndGetText($checkoutsStatus, '.badge.account-alert')
        );
        $this->assertEquals(
            'Items overdue: 3 ,',
            $this->findCssAndGetText($checkoutsStatus, '.visually-hidden, .sr-only', null, 2)
        );

        // Holds
        $holdsStatus = $this->findCss($page, '.myresearch-menu .holds-status');
        $this->assertEquals(
            '1',
            $this->findCssAndGetText($holdsStatus, '.badge.account-info')
        );
        $this->assertEquals(
            'Available for Pickup: 1 ,',
            $this->findCssAndGetText($holdsStatus, '.visually-hidden, .sr-only')
        );

        $this->assertEquals(
            '2',
            $this->findCssAndGetText($holdsStatus, '.badge.account-warning')
        );
        $this->assertEquals(
            'In Transit: 2 ,',
            $this->findCssAndGetText($holdsStatus, '.visually-hidden, .sr-only', null, 1)
        );

        $this->assertEquals(
            '3',
            $this->findCssAndGetText($holdsStatus, '.badge.account-none')
        );
        $this->assertEquals(
            'Other Status: 3 ,',
            $this->findCssAndGetText($holdsStatus, '.visually-hidden, .sr-only', null, 2)
        );

        // Fines
        $this->assertEquals(
            '$1.23',
            $this->findCssAndGetText($page, '.myresearch-menu .fines-status .badge.account-alert')
        );
    }

    /**
     * Standard teardown method.
     *
     * @return void
     */
    public static function tearDownAfterClass(): void
    {
        static::removeUsers(['username1', 'catuser']);
    }

    /**
     * Utility method to login
     *
     * @param string $user     Username
     * @param string $password Password
     *
     * @return \Behat\Mink\Session
     */
    protected function login($user = 'username1', $password = 'test')
    {
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl());
        $page = $session->getPage();
        $this->clickCss($page, '#loginOptions a');
        $this->waitForPageLoad($page);
        $this->fillInLoginForm($page, $user, $password);
        $this->clickCss($page, '.modal-body .btn.btn-primary');
        $this->waitForPageLoad($page);
        return $session;
    }

    /**
     * Get transaction JSON for Demo.ini.
     *
     * @param string $bibId Bibliographic record ID to create fake item info for.
     *
     * @return array
     */
    protected function getFakeTransactions($bibId)
    {
        $transactions = [];
        $template = [
            'barcode' => 1234567890,
            'renew'   => 0,
            'renewLimit' => 1,
            'request' => 0,
            'id' => $bibId,
            'source' => 'Solr',
            'item_id' => 0,
            'renewable' => true,
        ];
        $params = [
            [
                'dueStatus' => 'due',
                'duedate' => strtotime('now +5 days'),
            ],
            [
                'dueStatus' => 'due',
                'duedate' => strtotime('now +5 days'),
            ],
            [
                'dueStatus' => 'overdue',
                'duedate' => strtotime('now -1 days'),
            ],
            [
                'dueStatus' => 'overdue',
                'duedate' => strtotime('now -1 days'),
            ],
            [
                'dueStatus' => 'overdue',
                'duedate' => strtotime('now -2 days'),
            ],
            [
                'dueStatus' => false,
                'duedate' => strtotime('now +20 days'),
            ],
        ];
        foreach ($params as $current) {
            $transactions[] = [...$template, ...$current];
        }
        return json_encode($transactions);
    }
}
