<?php

/**
 * Email authentication test class.
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Mink;

/**
 * Email authentication test class.
 *
 * Class must be final due to use of "new static()" by LiveDatabaseTrait.
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
final class EmailAuthenticationTest extends \VuFindTest\Integration\MinkTestCase
{
    use \VuFindTest\Feature\EmailTrait;
    use \VuFindTest\Feature\LiveDatabaseTrait;

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
     * Test the ILS email authentication process.
     *
     * @return void
     */
    public function testILSEmailAuthentication(): void
    {
        // Set up configs, session and message logging:
        $this->changeConfigs(
            [
                'config' => [
                    'Authentication' => [
                        'method' => 'ILS',
                    ],
                    'Catalog' => [
                        'driver' => 'Demo',
                    ],
                    'Mail' => [
                        'testOnly' => true,
                        'message_log' => $this->getEmailLogPath(),
                        'default_from' => 'noreply@vufind.org',
                    ],
                ],
                'Demo' => [
                    'Catalog' => [
                        'loginMethod' => 'email',
                    ],
                ],
            ]
        );

        $this->resetEmailLog();
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl());
        $page = $session->getPage();

        // Request login:
        $this->clickCss($page, '#loginOptions a');
        $this->findCssAndSetValue($page, '.modal-body [name="username"]', 'catuser@vufind.org');
        $this->clickCss($page, '.modal-body .btn.btn-primary');
        $this->assertEquals(
            'We have sent a login link to your email address. It may take a few moments for the link to arrive.'
            . " If you don't receive the link shortly, please check also your spam filter.",
            $this->findCssAndGetText($page, '.alert-success')
        );

        // Extract the link from the provided message:
        $email = file_get_contents($this->getEmailLogPath());
        $this->assertStringContainsString('From: noreply@vufind.org', $email);
        $this->assertStringContainsString('To: catuser@vufind.org', $email);
        preg_match('/Link to login: <(http.*)>/', $email, $matches);
        $loginLink = $matches[1];

        // Follow the verification link:
        $session->visit($loginLink);

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
        static::removeUsers(['catuser@vufind.org']);
    }
}
