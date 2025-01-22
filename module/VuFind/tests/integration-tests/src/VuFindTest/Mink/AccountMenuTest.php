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
     * Standard setup + login
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        // Setup config
        $this->changeConfigs(
            [
            'Demo' => $this->getDemoIniOverrides(),
            'config' => [
                'Catalog' => ['driver' => 'Demo'],
                'Authentication' => [
                    'enableAjax' => true,
                    'enableDropdown' => false,
                ],
            ],
            ]
        );
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
        foreach ($states as $key => $state) {
            $js .= 'sessionStorage.setItem(\'vf-account-status-' . $key . '\', \'' . json_encode($state) . '\');';
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
     * Test that the menu is absent when enableAjax is true and enableDropdown
     * is false.
     *
     * @return void
     */
    public function testMenuOffAjaxNoDropdown()
    {
        // Create user
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl());
        $page = $session->getPage();
        $this->clickCss($page, '#loginOptions a');
        $this->clickCss($page, '.modal-body .createAccountLink');
        $this->fillInAccountForm($page);
        $this->clickCss($page, '.modal-body .btn.btn-primary');
        $this->waitForPageLoad($page);

        // Seed some fines
        $page = $this->setUpFinesEnvironment();
        $menu = $page->findAll('css', '#login-dropdown');
        $this->assertCount(0, $menu);
        $stati = $page->findAll('css', '.account-menu .fines-status.hidden');
        $this->assertCount(0, $stati);
    }

    /**
     * Test that the menu is absent when enableAjax is false and enableDropdown
     * is false.
     *
     * @depends testMenuOffAjaxNoDropdown
     *
     * @return void
     */
    public function testMenuOffNoAjaxNoDropdown()
    {
        // Nothing on
        $this->changeConfigs(
            [
                'config' => [
                    'Authentication' => [
                        'enableAjax' => false,
                        'enableDropdown' => false,
                    ],
                ],
            ]
        );
        $this->login();
        $page = $this->setUpFinesEnvironment();
        $menu = $page->findAll('css', '#login-dropdown');
        $this->assertCount(0, $menu);
        $stati = $page->findAll('css', '.account-menu .fines-status.hidden');
        $this->assertCount(1, $stati);
    }

    /**
     * Test that the menu is absent when enableAjax is false and enableDropdown
     * is true.
     *
     * @depends testMenuOffAjaxNoDropdown
     *
     * @return void
     */
    public function testMenuOffNoAjaxDropdown()
    {
        $this->changeConfigs(
            [
                'config' => [
                    'Authentication' => [
                        'enableAjax' => false,
                        'enableDropdown' => true,
                    ],
                ],
            ]
        );
        $this->login();
        $page = $this->setUpFinesEnvironment();
        $menu = $page->findAll('css', '#login-dropdown');
        $this->assertCount(1, $menu);
        $stati = $page->findAll('css', '.account-menu .fines-status.hidden');
        $this->assertCount(2, $stati); // one in menu, one in dropdown
    }

    /**
     * Test that the menu is absent when enableAjax is true and enableDropdown
     * is true.
     *
     * @depends testMenuOffAjaxNoDropdown
     *
     * @return void
     */
    public function testMenuOffAjaxDropdown()
    {
        $this->changeConfigs(
            [
                'config' => [
                    'Authentication' => [
                        'enableAjax' => true,
                        'enableDropdown' => true,
                    ],
                ],
            ]
        );
        $this->login();
        $page = $this->setUpFinesEnvironment();
        $menu = $page->findAll('css', '#login-dropdown');
        $this->assertCount(1, $menu);
        $this->unFindCss($page, '.account-menu .fines-status.hidden');
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
        $session = $this->login();
        // Seed some fines
        $this->setJSStorage(['fines' => ['value' => 30.5, 'display' => '$30.50']]);
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
     * Utility class to login
     *
     * @return \Behat\Mink\Session
     */
    protected function login()
    {
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl());
        $page = $session->getPage();
        $this->clickCss($page, '#loginOptions a');
        $this->waitForPageLoad($page);
        $this->fillInLoginForm($page, 'username1', 'test');
        $this->clickCss($page, '.modal-body .btn.btn-primary');
        $this->waitForPageLoad($page);
        return $session;
    }

    /**
     * Abstracted test to set storage and check if the icon is correct
     *
     * @param array  $storage    Array of storage values to test
     * @param string $checkClass Icon class to check
     *
     * @return void
     */
    protected function checkIcon($storage, $checkClass)
    {
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl());
        foreach ($storage as $item) {
            $this->setJSStorage($item);
            $session->reload();
            $page = $session->getPage();
            $this->waitForPageLoad($page);
            $this->findCss($page, '#account-icon ' . $checkClass);
            foreach ($item as $key => $value) {
                $session->evaluateScript('VuFind.account.clearCache("' . $key . '");');
            }
        }
    }

    /**
     * Check cases that don't change the account icon
     *
     * @return void
     */
    public function testIconNone()
    {
        $this->login();
        $storage = [
            // No fines
            [
                'fines' => [
                    'total' => 0,
                    'display' => 'ZILTCH',
                ],
            ],
            // Holds in transit only
            [
                'holds' => [
                    'in_transit' => 1,
                    'available' => 0,
                    'other' => 0,
                ],
            ],
            // ILL Requests in transit only
            [
                'illRequests' => [
                    'in_transit' => 1,
                    'available' => 0,
                    'other' => 0,
                ],
            ],
            // Storage Retrievals in transit only
            [
                'storageRetrievalRequests' => [
                    'in_transit' => 1,
                    'available' => 0,
                    'other' => 0,
                ],
            ],
        ];
        $this->checkIcon($storage, '.account-status-none');
    }

    /**
     * Check cases that change the account icon to a happy bell
     *
     * @return void
     */
    public function testIconGood()
    {
        $this->login();
        $storage = [
            // Holds available
            ['holds' => ['in_transit' => 0, 'available' => 1]],
            // ILL Requests available
            ['illRequests' => ['in_transit' => 0, 'available' => 1]],
            // Storage Retrievals available
            ['storageRetrievalRequests' => ['in_transit' => 0, 'available' => 1]],
        ];
        $this->checkIcon($storage, '.account-status-good');
    }

    /**
     * Check cases that change the account icon to a concerned bell
     *
     * @return void
     */
    public function testIconWarning()
    {
        $this->login();
        $storage = [
            // Checked out due soon
            ['checkedOut' => ['warn' => 1]],
        ];
        $this->checkIcon($storage, '.account-status-warning');
    }

    /**
     * Check cases that change the account icon to an alarming triangle
     *
     * @return void
     */
    public function testIconDanger()
    {
        $this->login();
        $storage = [
            // User has fines
            ['fines' => ['value' => 1000000, 'display' => '$...yikes']],
            // Checkedout overdue
            ['checkedOut' => ['overdue' => 1]],
        ];
        $this->checkIcon($storage, '.account-status-danger');
    }

    /**
     * More urgent cases should override lower cases
     *
     * Danger > Warning > Good > None
     *
     * @return void
     */
    public function testIconClashes()
    {
        $this->login();
        // Danger overrides warning
        $this->checkIcon(
            [['checkedOut' => ['warn' => 2, 'overdue' => 1]]],
            '.account-status-danger'
        );
        // Danger overrides good
        $this->checkIcon(
            [
                [
                    'checkedOut' => ['overdue' => 1],
                    'holds' => ['available' => 1],
                ],
            ],
            '.account-status-danger'
        );
        // Warning overrides good
        $this->checkIcon(
            [
                [
                    'checkedOut' => ['warn' => 1],
                    'holds' => ['available' => 1],
                ],
            ],
            '.account-status-warning'
        );
        // Good overrides none
        $this->checkIcon(
            [
                [
                    'holds' => ['available' => 1],
                    'fines' => ['total' => 0, 'display' => 'none'],
                ],
            ],
            '.account-status-good'
        );
    }

    /**
     * Standard teardown method.
     *
     * @return void
     */
    public static function tearDownAfterClass(): void
    {
        static::removeUsers(['username1']);
    }
}
