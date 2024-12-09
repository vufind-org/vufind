<?php

/**
 * Mink PasswordAccess authentication test class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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

use Behat\Mink\Element\Element;

/**
 * Mink PasswordAccess authentication test class.
 *
 * Class must be final due to use of "new static()" by LiveDatabaseTrait.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
final class PasswordAccessTest extends \VuFindTest\Integration\MinkTestCase
{
    use \VuFindTest\Feature\LiveDatabaseTrait;

    /**
     * Get config.ini override settings for testing SSO login.
     *
     * @return array
     */
    public function getConfigIniOverrides(): array
    {
        return [
            'config' => [
                'Authentication' => [
                    'method' => 'PasswordAccess',
                ],
                'PasswordAccess' => [
                    'access_user' => [
                        'username' => 'password',
                    ],
                ],
            ],
        ];
    }

    /**
     * Test logging in with a password.
     *
     * @return void
     */
    public function testLogin(): void
    {
        // Set up configs
        $this->changeConfigs($this->getConfigIniOverrides());
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl());
        $page = $session->getPage();

        // Open login dialog
        $this->clickCss($page, '#loginOptions a');
        $this->waitForPageLoad($page);

        // Try bad password
        $this->findCssAndSetValue($page, '#login_PasswordAccess_password', 'bad');
        $this->clickCss($page, '.modal-content input[type="submit"]');
        $this->waitForPageLoad($page);
        $this->assertEquals(
            'Invalid login -- please try again.',
            $this->findCssAndGetText($page, '.modal-content .alert-danger')
        );

        // Try good password
        $this->findCssAndSetValue($page, '#login_PasswordAccess_password', 'password');
        $this->clickCss($page, '.modal-content input[type="submit"]');
        $this->waitForPageLoad($page);

        // Log out
        $this->logoutAndAssertSuccess($page);
    }

    /**
     * Test logging in when no password is set.
     *
     * @return void
     */
    public function testLoginWithMissingConfiguration(): void
    {
        // Set up configs
        $configs = $this->getConfigIniOverrides();
        unset($configs['config']['PasswordAccess']);
        $this->changeConfigs($configs);
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl());
        $page = $session->getPage();

        // Open login dialog
        $this->clickCss($page, '#loginOptions a');
        $this->waitForPageLoad($page);

        // Try bad password
        $this->findCssAndSetValue($page, '#login_PasswordAccess_password', 'bad');
        $this->clickCss($page, '.modal-content input[type="submit"]');
        $this->waitForPageLoad($page);
        $this->assertEquals(
            'Invalid login -- please try again.',
            $this->findCssAndGetText($page, '.modal-content .alert-danger')
        );

        // Try good password (that would only be good if we hadn't unset the configuration!)
        $this->findCssAndSetValue($page, '#login_PasswordAccess_password', 'password');
        $this->clickCss($page, '.modal-content input[type="submit"]');
        $this->waitForPageLoad($page);
        $this->assertEquals(
            'Invalid login -- please try again.',
            $this->findCssAndGetText($page, '.modal-content .alert-danger')
        );
    }

    /**
     * Logs out on the current page and checks if logout was successful
     *
     * @param Element $page Current page object
     *
     * @return void
     */
    protected function logoutAndAssertSuccess(Element $page): void
    {
        // Log out
        $this->clickCss($page, '.logoutOptions a.logout');

        // Check that login link is back
        $this->assertNotEmpty($this->findCss($page, '#loginOptions a'));
    }

    /**
     * Standard teardown method.
     *
     * @return void
     */
    public static function tearDownAfterClass(): void
    {
        static::removeUsers(['username']);
    }
}
