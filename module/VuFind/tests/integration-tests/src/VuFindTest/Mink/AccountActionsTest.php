<?php
/**
 * Mink account actions test class.
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
 * Mink account actions test class.
 *
 * Class must be final due to use of "new static()" by LiveDatabaseTrait.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 * @retry    4
 */
final class AccountActionsTest extends \VuFindTest\Integration\MinkTestCase
{
    use \VuFindTest\Feature\LiveDatabaseTrait;
    use \VuFindTest\Feature\UserCreationTrait;

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
     * Standard setup method.
     *
     * @return void
     */
    public function setUp(): void
    {
        // Give up if we're not running in CI:
        if (!$this->continuousIntegrationRunning()) {
            $this->markTestSkipped('Continuous integration not running.');
            return;
        }
    }

    /**
     * Test changing a password.
     *
     * @retryCallback tearDownAfterClass
     *
     * @return void
     */
    public function testChangePassword()
    {
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl());
        $page = $session->getPage();

        // Create account
        $this->clickCss($page, '#loginOptions a');
        $this->clickCss($page, '.modal-body .createAccountLink');
        $this->fillInAccountForm($page);
        $this->clickCss($page, '.modal-body .btn.btn-primary');

        // Log out
        $this->clickCss($page, '.logoutOptions a.logout');

        // Go to profile page:
        $session->visit($this->getVuFindUrl('/MyResearch/Profile'));

        // Log back in
        $this->clickCss($page, '#loginOptions a');
        $this->fillInLoginForm($page, 'username1', 'test');
        $this->clickCss($page, '.modal-body .btn.btn-primary');
        $this->waitForPageLoad($page);

        // Now click change password button:
        $this->findAndAssertLink($page, 'Change Password')->click();

        // Change the password (but get the old password wrong)
        $this->fillInChangePasswordForm($page, 'bad', 'good');
        $this->clickCss($page, '#newpassword .btn.btn-primary');
        $this->assertEquals(
            'Invalid login -- please try again.',
            $this->findCss($page, '.alert-danger')->getText()
        );

        // Change the password successfully:
        $this->fillInChangePasswordForm($page, 'test', 'good');
        $this->clickCss($page, '#newpassword .btn.btn-primary');
        $this->assertEquals(
            'Your password has successfully been changed',
            $this->findCss($page, '.alert-success')->getText()
        );

        // Log out
        $this->clickCss($page, '.logoutOptions a.logout');
        $this->waitForPageLoad($page);

        // Log back in (using old credentials, which should now fail):
        $this->clickCss($page, '#loginOptions a');
        $this->fillInLoginForm($page, 'username1', 'test');
        $this->clickCss($page, '.modal-body .btn.btn-primary');
        $this->assertLightboxWarning($page, 'Invalid login -- please try again.');

        // Now log in successfully:
        $this->fillInLoginForm($page, 'username1', 'good');
        $this->clickCss($page, '.modal-body .btn.btn-primary');
        $this->waitForPageLoad($page);

        // One final log out (to confirm that log in really worked).
        $this->clickCss($page, '.logoutOptions a.logout');
    }

    /**
     * Test that changing email is disabled by default.
     *
     * @depends testChangePassword
     *
     * @return void
     */
    public function testChangeEmailDisabledByDefault()
    {
        // Go to profile page:
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl('/MyResearch/Profile'));
        $page = $session->getPage();

        // Log in
        $this->clickCss($page, '#loginOptions a');
        $this->fillInLoginForm($page, 'username1', 'good');
        $this->clickCss($page, '.modal-body .btn.btn-primary');
        $this->waitForPageLoad($page);

        // Now confirm that email button is absent:
        $link = $page->findLink('Change Email Address');
        $this->assertFalse(is_object($link));
    }

    /**
     * Test changing an email.
     *
     * @depends testChangePassword
     *
     * @return void
     */
    public function testChangeEmail()
    {
        // Turn on email change option:
        $this->changeConfigs(
            [
                'config' => [
                    'Authentication' => [
                        'change_email' => true,
                    ]
                ]
            ]
        );

        // Go to profile page:
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl('/MyResearch/Profile'));
        $page = $session->getPage();

        // Log in
        $this->clickCss($page, '#loginOptions a');
        $this->fillInLoginForm($page, 'username1', 'good');
        $this->clickCss($page, '.modal-body .btn.btn-primary');
        $this->waitForPageLoad($page);

        // Now click change email button:
        $this->findAndAssertLink($page, 'Change Email Address')->click();
        $this->waitForPageLoad($page);

        // Change the email:
        $this->findCssAndSetValue($page, '[name="email"]', 'new@email.com');
        $this->clickCss($page, '[name="submit"]');
        $this->waitForPageLoad($page);
        $this->assertEquals(
            'Your email address has been changed successfully',
            $this->findCss($page, '.alert-success')->getText()
        );

        // Now go to profile page and confirm that email has changed:
        $session->visit($this->getVuFindUrl('/MyResearch/Profile'));
        $this->assertEquals(
            'First Name: Tester Last Name: McTestenson Email: new@email.com',
            $this->findCss($page, '.table-striped')->getText()
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
