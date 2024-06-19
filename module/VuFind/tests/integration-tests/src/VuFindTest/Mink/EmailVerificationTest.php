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
     * Standard teardown method.
     *
     * @return void
     */
    public static function tearDownAfterClass(): void
    {
        static::removeUsers(['username1']);
    }
}
