<?php

/**
 * Mink account actions test class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2011.
 * Copyright (C) The National Library of Finland 2022.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Mink;

use VuFind\Db\Table\User;

use function count;

/**
 * Mink account actions test class.
 *
 * Class must be final due to use of "new static()" by LiveDatabaseTrait.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
final class AccountActionsTest extends \VuFindTest\Integration\MinkTestCase
{
    use \VuFindTest\Feature\EmailTrait;
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
     * Test changing a password.
     *
     * @return void
     */
    public function testChangePassword(): void
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
            $this->findCssAndGetText($page, '.alert-danger')
        );

        // Change the password successfully:
        $this->fillInChangePasswordForm($page, 'test', 'good');
        $this->clickCss($page, '#newpassword .btn.btn-primary');
        $this->assertEquals(
            'Your password has successfully been changed',
            $this->findCssAndGetText($page, '.alert-success')
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
     * Test username case-insensitivity.
     *
     * @depends testChangePassword
     *
     * @return void
     */
    public function testCaseInsensitiveUsername(): void
    {
        $session = $this->getMinkSession();
        $page = $session->getPage();

        // Go to profile page:
        $session->visit($this->getVuFindUrl('/MyResearch/Profile'));

        // Log back in using UPPERCASE version of username (it was created in lowercase above).
        $this->clickCss($page, '#loginOptions a');
        $this->fillInLoginForm($page, 'USERNAME1', 'good');
        $this->clickCss($page, '.modal-body .btn.btn-primary');
        $this->waitForPageLoad($page);

        // Confirm that we logged in based on the presence of a "change password" link.
        $this->findAndAssertLink($page, 'Change Password');
    }

    /**
     * Data provider for testLoginWithSessionSettings().
     *
     * @return array
     */
    public static function sessionSettingsProvider(): array
    {
        return [
            'unencrypted file' => ['File', false],
            'encrypted file' => ['File', true],
            'unencrypted database' => ['Database', false],
            'encrypted database' => ['Database', true],
        ];
    }

    /**
     * Test that we can log in successfully using various session settings.
     *
     * @param string $type   Session handler to use
     * @param bool   $secure Should we enable secure session mode?
     *
     * @return void
     *
     * @depends testChangePassword
     *
     * @dataProvider sessionSettingsProvider
     */
    public function testLoginWithSessionSettings(string $type, bool $secure): void
    {
        // Adjust session settings:
        $this->changeConfigs(
            [
                'config' => [
                    'Session' => compact('type', 'secure'),
                ],
            ]
        );

        // Go to profile page:
        $session = $this->getMinkSession();
        $page = $session->getPage();
        $session->visit($this->getVuFindUrl('/MyResearch/Profile'));

        // Log in
        $this->clickCss($page, '#loginOptions a');
        $this->fillInLoginForm($page, 'username1', 'good');
        $this->clickCss($page, '.modal-body .btn.btn-primary');
        $this->waitForPageLoad($page);

        // Confirm that we logged in based on the presence of a "change password" link.
        $this->findAndAssertLink($page, 'Change Password');
    }

    /**
     * Test that changing email is disabled by default.
     *
     * @depends testChangePassword
     *
     * @return void
     */
    public function testChangeEmailDisabledByDefault(): void
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
        $this->assertIsNotObject($link);
    }

    /**
     * Test changing an email.
     *
     * @depends testChangePassword
     *
     * @return void
     */
    public function testChangeEmail(): void
    {
        // Turn on email change option:
        $this->changeConfigs(
            [
                'config' => [
                    'Authentication' => [
                        'change_email' => true,
                    ],
                ],
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
        $this->clickCss($page, '[name="submitButton"]');
        $this->waitForPageLoad($page);
        $this->assertEquals(
            'Your email address has been changed successfully',
            $this->findCssAndGetText($page, '.alert-success')
        );

        // Now go to profile page and confirm that email has changed:
        $session->visit($this->getVuFindUrl('/MyResearch/Profile'));
        $this->assertEquals(
            'First Name: Tester Last Name: McTestenson Email: new@email.com',
            $this->findCssAndGetText($page, '.table-striped')
        );
    }

    /**
     * Test default pick up location
     *
     * @return void
     */
    public function testDefaultPickUpLocation(): void
    {
        // Setup config
        $this->changeConfigs(
            [
                'Demo' => [
                    'Users' => ['catuser' => 'catpass'],
                ],
                'config' => [
                    'Catalog' => ['driver' => 'Demo'],
                ],
            ]
        );

        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl());
        $page = $session->getPage();

        // Create account
        $this->clickCss($page, '#loginOptions a');
        $this->clickCss($page, '.modal-body .createAccountLink');
        $this->fillInAccountForm(
            $page,
            [
                'username' => 'username2',
                'email' => 'username2@ignore.com',
            ]
        );
        $this->clickCss($page, '.modal-body .btn.btn-primary');

        // Go to profile page:
        $this->waitForPageLoad($page);
        $session->visit($this->getVuFindUrl('/MyResearch/Profile'));

        // Do patron login:
        $this->submitCatalogLoginForm($page, 'catuser', 'catpass');

        // Check the default library and possible values:
        $userTable = $this->getTable(User::class);
        $this->assertSame('', $userTable->getByUsername('username2')->getHomeLibrary());
        $this->assertEquals(
            '',
            $this->findCssAndGetValue($page, '#home_library')
        );
        $expectedChoices = ['', ' ** ', 'A', 'B', 'C'];
        foreach ($expectedChoices as $i => $expected) {
            $this->assertEquals(
                $expected,
                $this->findCssAndGetValue($page, '#home_library option', null, $i)
            );
        }
        // Make sure there are no more pick up locations:
        $this->unFindCss(
            $page,
            '#home_library option',
            null,
            count($expectedChoices)
        );

        // Change the default and verify:
        $this->findCssAndSetValue($page, '#home_library', 'B');
        $this->clickCss($page, '#profile_form .btn');
        $this->waitForPageLoad($page);
        $this->assertEquals('B', $this->findCssAndGetValue($page, '#home_library'));
        $this->assertEquals(
            'B',
            $userTable->getByUsername('username2')->getHomeLibrary()
        );

        // Change to "Always ask me":
        $this->findCssAndSetValue($page, '#home_library', ' ** ');
        $this->clickCss($page, '#profile_form .btn');
        $this->waitForPageLoad($page);
        $this->assertEquals(
            ' ** ',
            $this->findCssAndGetValue($page, '#home_library')
        );
        $this->assertNull($userTable->getByUsername('username2')->getHomeLibrary());

        // Back to default:
        $this->findCssAndSetValue($page, '#home_library', '');
        $this->clickCss($page, '#profile_form .btn');
        $this->waitForPageLoad($page);
        $this->assertEquals(
            '',
            $this->findCssAndGetValue($page, '#home_library')
        );
        $this->assertSame('', $userTable->getByUsername('username2')->getHomeLibrary());
    }

    /**
     * Test ILS authentication.
     *
     * @return void
     */
    public function testILSAuthentication(): void
    {
        // Setup config
        $this->changeConfigs(
            [
                'Demo' => [
                    'Users' => ['username3' => 'catpass'],
                ],
                'config' => [
                    'Catalog' => ['driver' => 'Demo'],
                    'Authentication' => ['method' => 'ILS'],
                ],
            ]
        );
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl('/MyResearch/Profile'));
        $page = $session->getPage();

        // Log in
        $this->findCssAndSetValue($page, '#login_ILS_username', 'username3');
        $this->findCssAndSetValue($page, '#login_ILS_password', 'catpass');
        $this->clickCss($page, 'input.btn.btn-primary');

        // Check that profile page is displayed
        $this->findCss($page, '#home_library');

        // Log out
        $this->clickCss($page, '.logoutOptions a.logout');
    }

    /**
     * Test account deletion.
     *
     * @return void
     *
     * @depends testDefaultPickUpLocation
     */
    public function testAccountDeletion(): void
    {
        $this->changeConfigs(
            [
                'config' => [
                    'Authentication' => ['account_deletion' => true],
                ],
            ]
        );
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl('/MyResearch/Profile'));
        $page = $session->getPage();

        // Log in
        $this->fillInLoginForm($page, 'username2', 'test', false);
        $this->submitLoginForm($page, false);
        $this->waitForPageLoad($page);

        // Delete the account
        $this->clickCss($page, '.fa-trash-o');
        $this->clickCss($page, '.modal #delete-account-submit');
        $this->waitForPageLoad($page);

        // Try to log back in; it shouldn't work:
        $session->visit($this->getVuFindUrl('/MyResearch/Profile'));
        $page = $session->getPage();
        $this->fillInLoginForm($page, 'username2', 'test', false);
        $this->submitLoginForm($page, false);
        $this->waitForPageLoad($page);
        $this->assertEquals('Invalid login -- please try again.', $this->findCssAndGetText($page, '.alert-danger'));
    }

    /**
     * Test recovering a password by username.
     *
     * @return void
     *
     * @depends testChangePassword
     */
    public function testRecoverPasswordByUsername(): void
    {
        $this->changeConfigs(
            [
                'config' => [
                    'Authentication' => [
                        'recover_password' => true,
                        'recover_interval' => 0,
                    ],
                    'Mail' => [
                        'testOnly' => true,
                        'message_log' => $this->getEmailLogPath(),
                    ],
                ],
            ]
        );

        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl());
        $page = $session->getPage();
        $this->resetEmailLog();

        // Recover account
        $this->clickCss($page, '#loginOptions a');
        $this->clickCss($page, '.modal-body .recover-account-link');
        $this->findCssAndSetValue($page, '#recovery_username', 'bad');
        $this->clickCss($page, '.modal-body input[type="submit"]');
        $this->assertEquals('We could not find your account', $this->findCssAndGetText($page, '.alert-danger'));
        $this->findCssAndSetValue($page, '#recovery_username', 'username1');
        $this->clickCss($page, '.modal-body input[type="submit"]');
        $this->assertEquals(
            'Password recovery instructions have been sent to the email address registered with this account.',
            $this->findCssAndGetText($page, '.alert-success')
        );

        // Extract URL from email:
        $email = file_get_contents($this->getEmailLogPath());
        preg_match('/You can reset your password at this URL: (http.*)/', $email, $matches);
        $link = $matches[1];

        // Reset the password:
        $session->visit($link);
        $this->assertEquals('username1', $this->findCssAndGetText($page, '.form-control-static'));
        $this->findCssAndSetValue($page, '#password', 'recovered');
        $this->findCssAndSetValue($page, '#password2', 'recovered');
        $this->clickCss($page, '.form-new-password .btn-primary');
        $this->assertEquals(
            'Your password has successfully been changed',
            $this->findCssAndGetText($page, '.alert-success')
        );

        $this->resetEmailLog();
    }

    /**
     * Standard teardown method.
     *
     * @return void
     */
    public static function tearDownAfterClass(): void
    {
        static::removeUsers(['username1', 'username2', 'username3']);
    }
}
