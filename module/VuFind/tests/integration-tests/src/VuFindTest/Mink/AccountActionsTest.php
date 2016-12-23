<?php
/**
 * Mink account actions test class.
 *
 * PHP version 5
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
 * Mink account actions test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class AccountActionsTest extends \VuFindTest\Unit\MinkTestCase
{
    use \VuFindTest\Unit\UserCreationTrait;

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
     * Standard setup method.
     *
     * @return void
     */
    public function setUp()
    {
        // Give up if we're not running in CI:
        if (!$this->continuousIntegrationRunning()) {
            return $this->markTestSkipped('Continuous integration not running.');
        }
    }

    /**
     * Test changing a password.
     *
     * @return void
     */
    public function testChangePassword()
    {
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl());
        $page = $session->getPage();

        // Create account
        $this->findCss($page, '#loginOptions a')->click();
        $this->snooze();
        $this->findCss($page, '.modal-body .createAccountLink')->click();
        $this->snooze();
        $this->fillInAccountForm($page);
        $this->findCss($page, '.modal-body .btn.btn-primary')->click();
        $this->snooze();

        // Log out
        $this->findCss($page, '.logoutOptions a.logout')->click();
        $this->snooze();

        // Log back in
        $this->findCss($page, '#loginOptions a')->click();
        $this->fillInLoginForm($page, 'username1', 'test');
        $this->findCss($page, '.modal-body .btn.btn-primary')->click();
        $this->snooze();

        // We should now be on account screen; go to change password page
        $this->findAndAssertLink($page, 'Change Password')->click();
        $this->snooze();

        // Change the password (but get the old password wrong)
        $this->fillInChangePasswordForm($page, 'bad', 'good');
        $this->findCss($page, '#newpassword .btn.btn-primary')->click();
        $this->snooze();
        $this->assertEquals(
            'Invalid login -- please try again.',
            $this->findCss($page, '.alert-danger')->getText()
        );

        // Change the password successfully:
        $this->fillInChangePasswordForm($page, 'test', 'good');
        $this->findCss($page, '#newpassword .btn.btn-primary')->click();
        $this->snooze();
        $this->assertEquals(
            'Your password has successfully been changed',
            $this->findCss($page, '.alert-success')->getText()
        );

        // Log out
        $this->findCss($page, '.logoutOptions a.logout')->click();
        $this->snooze();

        // Log back in (using old credentials, which should now fail):
        $this->findCss($page, '#loginOptions a')->click();
        $this->fillInLoginForm($page, 'username1', 'test');
        $this->findCss($page, '.modal-body .btn.btn-primary')->click();
        $this->snooze();
        $this->assertLightboxWarning($page, 'Invalid login -- please try again.');

        // Now log in successfully:
        $this->fillInLoginForm($page, 'username1', 'good');
        $this->findCss($page, '.modal-body .btn.btn-primary')->click();
        $this->snooze();

        // One final log out (to confirm that log in really worked).
        $this->findCss($page, '.logoutOptions a.logout')->click();
        $this->snooze();
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
