<?php

/**
 * Email verification test class.
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

/**
 * Email verification test class.
 *
 * Class must be final due to use of "new static()" by LiveDatabaseTrait.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
final class EmailVerificationTest extends \VuFindTest\Integration\MinkTestCase
{
    use \VuFindTest\Feature\EmailTrait;
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
     * Test the email verification process.
     *
     * @return void
     */
    public function testEmailVerification(): void
    {
        // Set up configs, session and message logging:
        $this->changeConfigs(
            [
                'config' => [
                    'Authentication' => [
                        'verify_email' => true,
                    ],
                    'Mail' => [
                        'testOnly' => true,
                        'message_log' => $this->getEmailLogPath(),
                    ],
                ],
            ]
        );

        $this->resetEmailLog();
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl());
        $page = $session->getPage();

        // Create account
        $this->clickCss($page, '#loginOptions a');
        $this->clickCss($page, '.modal-body .createAccountLink');
        $this->fillInAccountForm($page);
        $this->clickCss($page, '.modal-body .btn.btn-primary');
        $this->assertEquals(
            'Email address verification instructions have been sent to the email address registered with this account.',
            $this->findCssAndGetText($page, '.alert-info')
        );

        // Extract the link from the provided message:
        $email = file_get_contents($this->getEmailLogPath());
        preg_match('/You can verify your email address with this link: <(http.*)>/', $email, $matches);
        $verifyLink = $matches[1];

        // Follow the verification link:
        $session->visit($verifyLink);
        $this->assertEquals(
            'Your email address has been verified successfully.',
            $this->findCssAndGetText($page, '.alert-info')
        );

        // Confirm that we can log in successfully:
        $this->fillInLoginForm($page, 'username1', 'test', false);
        $this->submitLoginForm($page, false);

        // Log out (we can't log out unless we successfully logged in):
        $this->clickCss($page, '.logoutOptions a.logout');

        // Clean up the email log:
        $this->resetEmailLog();
    }

    /**
     * Test changing email address.
     *
     * @return void
     *
     * @depends testEmailVerification
     */
    public function testEmailAddressChange(): void
    {
        // Set up configs, session and message logging:
        $this->changeConfigs(
            [
                'config' => [
                    'Authentication' => [
                        'change_email' => true,
                        'verify_email' => true,
                    ],
                    'Mail' => [
                        'testOnly' => true,
                        'message_log' => $this->getEmailLogPath(),
                    ],
                ],
            ]
        );

        $this->resetEmailLog();
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl('/MyResearch/Profile'));
        $page = $session->getPage();

        // Log back in
        $this->clickCss($page, '#loginOptions a');
        $this->fillInLoginForm($page, 'username1', 'test');
        $this->clickCss($page, '.modal-body .btn.btn-primary');
        $this->waitForPageLoad($page);

        // Request the email change:
        $this->clickCss($page, '.fa-envelope');
        $this->assertEquals(
            'Submitting this form will send an email to the new address; '
            . 'you will have to click on a link in the email before the change will take effect.',
            $this->findCssAndGetText($page, '.alert-info')
        );
        $this->findCssAndSetValue($page, 'input[name="email"]', 'changed@example.com');
        $this->clickCss($page, '#newemail .btn-primary');

        // Confirm that the email hasn't changed yet (not yet verified):
        $session->visit($this->getVuFindUrl('/MyResearch/Profile'));
        $this->assertStringContainsString(
            'username1@ignore.com',
            $this->findCssAndGetText($page, 'table.table-striped')
        );

        // Confirm that messages went to both new and old email addresses, and extract the verify link:
        $email = file_get_contents($this->getEmailLogPath());
        $this->assertStringContainsString('To: changed@example.com', $email);
        $this->assertStringContainsString('To: username1@ignore.com', $email);
        $this->assertStringContainsString(
            'A request was just made to change your email address at Library Catalog.',
            $email
        );
        preg_match('/You can verify your email address with this link: <(http.*)>/', $email, $matches);
        $verifyLink = $matches[1];

        // Follow the verification link:
        $session->visit($verifyLink);
        $this->assertEquals(
            'Your email address has been verified successfully.',
            $this->findCssAndGetText($page, '.alert-info')
        );

        // Confirm that the email has now changed:
        $session->visit($this->getVuFindUrl('/MyResearch/Profile'));
        $this->assertStringContainsString(
            'changed@example.com',
            $this->findCssAndGetText($page, 'table.table-striped')
        );

        // Clean up the email log:
        $this->resetEmailLog();
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
