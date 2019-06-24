<?php
/**
 * Mink account ajax menu test class.
 *
 * PHP version 7
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
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 * @retry    4
 */
class AccountMenuTest extends \VuFindTest\Unit\MinkTestCase
{
    use \VuFindTest\Unit\AutoRetryTrait;
    use \VuFindTest\Unit\UserCreationTrait;
    use \VuFindTest\Unit\DemoDriverTestTrait;

    /**
     * Standard setup method.
     *
     * @return mixed
     */
    public static function setUpBeforeClass()
    {
        return static::failIfUsersExist();
    }

    /**
     * Standard setup + login
     *
     * @return void
     */
    public function setUp()
    {
        // Give up if we're not running in CI:
        if (!$this->continuousIntegrationRunning()) {
            return $this->markTestSkipped('Continuous integration not running.');
        }
        // Setup config
        $this->changeConfigs([
            'Demo' => $this->getDemoIniOverrides(),
            'config' => [
                'Catalog' => ['driver' => 'Demo'],
                'Authentication' => [
                    'enableAjax' => true,
                    'enableDropdown' => false
                ]
            ]
        ]);
    }

    /**
     * Create a specific state in the account ajax storage.
     *
     * Cleared when browser closes.
     * If run multiple times in one test function, manually clear cache.
     *
     * @return void
     */
    protected function setJSStorage($states)
    {
        $session = $this->getMinkSession();
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
     * Test changing a password.
     *
     * @retryCallback tearDownAfterClass
     *
     * @return void
     */
    public function testMenuOff()
    {
        // Create user
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl());
        $page = $session->getPage();
        $this->clickCss($page, '#loginOptions a');
        $this->snooze();
        $this->clickCss($page, '.modal-body .createAccountLink');
        $this->snooze();
        $this->fillInAccountForm($page);
        $this->clickCss($page, '.modal-body .btn.btn-primary');
        $this->snooze();

        // Seed some fines
        $this->setJSStorage(['fines' => ['value' => 30.5, 'display' => '$30.50']]);

        // enableAjax => true, enableDropdown => false
        $session->reload();
        $this->snooze();
        $session = $this->getMinkSession();
        $page = $session->getPage();
        $menu = $page->findAll('css', '#login-dropdown');
        $this->assertEquals(0, count($menu));
        $stati = $page->findAll('css', '.account-menu .fines-status.hidden');
        $this->assertEquals(0, count($stati));

        // Nothing on
        $this->changeConfigs(
            [
                'config' => [
                    'Authentication' => [
                        'enableAjax' => false,
                        'enableDropdown' => false
                    ]
                ]
            ]
        );
        $session->reload();
        $page = $session->getPage();
        $this->snooze();
        $menu = $page->findAll('css', '#login-dropdown');
        $this->assertEquals(0, count($menu));
        $stati = $page->findAll('css', '.account-menu .fines-status.hidden');
        $this->assertEquals(1, count($stati));

        // Menu off, dropdown on
        $this->changeConfigs(
            [
                'config' => [
                    'Authentication' => [
                        'enableAjax' => false,
                        'enableDropdown' => true
                    ]
                ]
            ]
        );
        $session->reload();
        $this->snooze();
        $page = $session->getPage();
        $menu = $page->findAll('css', '#login-dropdown');
        $this->assertEquals(1, count($menu));
        $stati = $page->findAll('css', '.account-menu .fines-status.hidden');
        $this->assertEquals(2, count($stati)); // one in menu, one in dropdown

        // Reset all on
        $this->changeConfigs(
            [
                'config' => [
                    'Authentication' => [
                        'enableAjax' => true,
                        'enableDropdown' => true
                    ]
                ]
            ]
        );
        $session->reload();
        $this->snooze();
        $page = $session->getPage();
        $menu = $page->findAll('css', '#login-dropdown');
        $this->assertEquals(1, count($menu));
        $stati = $page->findAll('css', '.account-menu .fines-status.hidden');
        $this->assertEquals(0, count($stati));
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
        $this->snooze();
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
        $storage = $this->getJSStorage();
        $this->assertNotNull($storage['fines']);
        // Clear all cache
        $session->evaluateScript('VuFind.account.clearCache();');
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
        $this->snooze();
        $this->fillInLoginForm($page, 'username1', 'test');
        $this->clickCss($page, '.modal-body .btn.btn-primary');
        $this->snooze();
        return $session;
    }

    /**
     * Abstracted test to set storage and check if the icon is correct
     *
     * @return void
     */
    protected function checkIcon($storage, $checkClass)
    {
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl());
        foreach ($storage as $item) {
            $this->snooze();
            $this->setJSStorage($item);
            $session->reload();
            $page = $session->getPage();
            $this->findCss($page, '#account-icon' . $checkClass);
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
            ['fines' => ['value' => 0, 'display' => 'ZILTCH']],
            // Holds in transit only
            ['holds' => ['in_transit' => 1, 'available' => 0]],
            // ILL Requests in transit only
            ['illRequests' => ['in_transit' => 1, 'available' => 0]],
            // Storage Retrievals in transit only
            ['storageRetrievalRequests' => ['in_transit' => 1, 'available' => 0]]
        ];
        $this->checkIcon($storage, '.fa-user-circle');
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
            ['storageRetrievalRequests' => ['in_transit' => 0, 'available' => 1]]
        ];
        $this->checkIcon($storage, '.fa-bell.text-success');
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
            ['checkedOut' => ['warn' => 1]]
        ];
        $this->checkIcon($storage, '.fa-bell.text-warning');
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
        $this->checkIcon($storage, '.fa-exclamation-triangle');
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
            '.fa-exclamation-triangle'
        );
        // Danger overrides good
        $this->checkIcon(
            [
                [
                    'checkedOut' => ['overdue' => 1],
                    'holds' => ['available' => 1]
                ]
            ],
            '.fa-exclamation-triangle'
        );
        // Warning overrides good
        $this->checkIcon(
            [
                [
                    'checkedOut' => ['warn' => 1],
                    'holds' => ['available' => 1]
                ]
            ],
            '.fa-bell.text-warning'
        );
        // Good overrides none
        $this->checkIcon(
            [
                [
                    'holds' => ['available' => 1],
                    'fines' => ['value' => 0, 'display' => 'none']
                ]
            ],
            '.fa-bell.text-success'
        );
    }

    /**
     * Standard teardown method.
     *
     * @return void
     */
    public static function tearDownAfterClass()
    {
        static::removeUsers(['username1']);
    }
}
