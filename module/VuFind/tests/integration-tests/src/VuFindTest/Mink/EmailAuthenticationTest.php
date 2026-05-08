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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Mink;

use PHPUnit\Framework\Attributes\DataProvider;

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
     * Test the (non-ILS) email authentication process.
     *
     * @return void
     */
    public function testEmailAuthentication(): void
    {
        $this->setUpDatabaseEmailConfig();

        $this->resetEmailLog();
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

        // Request login:
        $this->clickCss($page, '#loginOptions a');
        $this->findCssAndSetValue($page, '.modal-body #login_Email_username', 'username1@ignore.com');
        $this->clickCss($page, '.modal-body .btn.btn-primary', null, 1);
        $this->assertSame(
            'We have sent a login code to your email address. It may take a few moments for the code to arrive.'
            . " If you don't receive the code shortly, please check also your spam filter.",
            $this->findCssAndGetText($page, '.alert-info')
        );

        // Enter the one-time code:
        $code = $this->extractLoginCodeFromEmail('username1@ignore.com');
        $this->findCssAndSetValue($page, '#login_Email_password', $code);
        $this->clickCss($page, '.form-login .btn-primary');

        // Log out (we can't log out unless we successfully logged in):
        $this->clickCss($page, '.logoutOptions a.logout');

        // Clean up the email log:
        $this->resetEmailLog();
    }

    /**
     * Test the (non-ILS) email authentication process with invalid email address.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testEmailAuthentication')]
    public function testEmailAuthenticationBadEmail(): void
    {
        $this->setUpDatabaseEmailConfig();

        $this->resetEmailLog();
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl());
        $page = $session->getPage();

        // Request login:
        $this->clickCss($page, '#loginOptions a');
        $this->findCssAndSetValue($page, '.modal-body #login_Email_username', 'username1@foo.bar');
        $this->clickCss($page, '.modal-body .btn.btn-primary', null, 1);
        $this->assertSame(
            'We have sent a login code to your email address. It may take a few moments for the code to arrive.'
            . " If you don't receive the code shortly, please check also your spam filter.",
            $this->findCssAndGetText($page, '.alert-info')
        );

        $this->expectExceptionMessage('No serialized email message data found');
        $this->getLoggedEmail();
    }

    /**
     * Data provider for testEmailAuthenticationAttemptLimit.
     *
     * @return \Iterator
     */
    public static function emailAuthenticationAttemptLimitProvider(): \Iterator
    {
        yield [null, 3];
        yield [0, 1];
        yield [1, 1];
        yield [4, 4];
    }

    /**
     * Test the email authentication maximum attempt limit.
     *
     * @param ?int $configuredLimit Limit to set in configuration, or null for default
     * @param int  $limit           Limit to test for
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testEmailAuthentication')]
    #[DataProvider('emailAuthenticationAttemptLimitProvider')]
    public function testEmailAuthenticationAttemptLimit(?int $configuredLimit, int $limit): void
    {
        $config = null === $configuredLimit
            ? []
            : [
                'Authentication' => [
                    'otp_max_attempts' => $configuredLimit,
                ],
            ];
        $this->setUpDatabaseEmailConfig($config);

        $this->resetEmailLog();
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl());
        $page = $session->getPage();

        // Request login:
        $this->clickCss($page, '#loginOptions a');
        $this->findCssAndSetValue($page, '.modal-body #login_Email_username', 'username1@ignore.com');
        $this->clickCss($page, '.modal-body .btn.btn-primary', null, 1);
        $this->assertSame(
            'We have sent a login code to your email address. It may take a few moments for the code to arrive.'
            . " If you don't receive the code shortly, please check also your spam filter.",
            $this->findCssAndGetText($page, '.alert-info')
        );

        for ($attempt = 1; $attempt <= $limit + 1; $attempt++) {
            // Enter an invalid code:
            $this->findCssAndSetValue($page, '#login_Email_password', '123');
            $this->clickCss($page, '.form-login .btn-primary');
            $expectedError = $attempt === $limit + 1
                ? 'The authentication request has expired.'
                : 'Invalid login -- please try again.';
            $this->assertSame(
                $expectedError,
                $this->findCssAndGetText($page, '.alert-danger')
            );
        }

        // Clean up the email log:
        $this->resetEmailLog();
    }

    /**
     * Data provider for testILSEmailAuthentication.
     *
     * @return \Iterator
     */
    public static function ilsEmailAuthenticationProvider(): \Iterator
    {
        yield 'ILS' => [false];
        yield 'MultiILS' => [true];
    }

    /**
     * Test the ILS email authentication process.
     *
     * @param bool $multiIls Use MultiILS driver?
     *
     * @return void
     */
    #[DataProvider('ilsEmailAuthenticationProvider')]
    public function testILSEmailAuthentication(bool $multiIls): void
    {
        // Set up configs, session and message logging:
        $configs = $multiIls ? [
            'config' => [
                'Authentication' => [
                    'method' => 'MultiILS',
                    'recover_interval' => 0,
                ],
                'Catalog' => [
                    'driver' => 'MultiBackend',
                ],
            ],
            'MultiBackend' => [
                'Drivers' => [
                    'ils1' => 'Demo',
                    'ils2' => 'Demo',
                ],
                'Login' => [
                    'default_driver' => 'ils1',
                    'drivers' => [
                        'ils1',
                        'ils2',
                    ],
                ],
            ],
            'Demo:ils1' => [
                'Catalog' => [
                    'loginMethod' => 'email',
                ],
            ],
            'Demo:ils2' => [],
        ] : [
            'config' => [
                'Authentication' => [
                    'method' => 'ILS',
                    'recover_interval' => 0,
                ],
                'Catalog' => [
                    'driver' => 'Demo',
                ],
            ],
            'Demo' => [
                'Catalog' => [
                    'loginMethod' => 'email',
                ],
            ],
        ];
        $configs['config']['Mail'] = [
            'testOnly' => true,
            'message_log' => $this->getEmailLogPath(),
            'message_log_format' => $this->getEmailLogFormat(),
            'default_from' => 'noreply@vufind.org',
        ];

        $this->changeConfigs($configs);

        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl());
        $page = $session->getPage();

        // Request login three times to ensure that repeated requests work:
        for ($i = 1; $i <= 3; $i++) {
            $this->resetEmailLog();
            $this->clickCss($page, '#loginOptions a');
            $this->findCssAndSetValue($page, '.modal-body [name="username"]', 'catuser@vufind.org');
            $this->clickCss($page, '.modal-body .btn.btn-primary');
            $this->assertSame(
                'We have sent a login code to your email address. It may take a few moments for the code to arrive.'
                . " If you don't receive the code shortly, please check also your spam filter.",
                $this->findCssAndGetText($page, '.alert-info')
            );
            if ($i < 3) {
                $this->clickCss($page, '.modal-body .cancel-link');
                $this->waitForPageLoad($page);
            }
        }

        // Try wrong code first:
        $passwordField = $multiIls ? '#login_MultiILS_password' : '#login_ILS_password';
        $this->findCssAndSetValue($page, $passwordField, '123');
        $this->clickCss($page, '.form-login .btn-primary');
        $this->assertSame(
            'Invalid login -- please try again.',
            $this->findCssAndGetText($page, '.modal .alert-danger')
        );

        // Enter the one-time code:
        $code = $this->extractLoginCodeFromEmail('catuser@vufind.org');
        $this->findCssAndSetValue($page, $passwordField, $code);
        $this->clickCss($page, '.form-login .btn-primary');

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
        static::removeUsers(['username1', 'catuser@vufind.org', 'ils1.catuser@vufind.org']);
    }

    /**
     * Set up configuration for Database+Email authentication.
     *
     * @param array $config Any configuration to augment or override defaults
     *
     * @return void
     */
    protected function setUpDatabaseEmailConfig(array $config = []): void
    {
        // Set up configs, session and message logging:
        $this->changeConfigs(
            [
                'config' => array_merge_recursive(
                    [
                        'Authentication' => [
                            'method' => 'ChoiceAuth',
                        ],
                        'ChoiceAuth' => [
                            'choice_order' => 'Database,Email',
                        ],
                        'Mail' => [
                            'testOnly' => true,
                            'message_log' => $this->getEmailLogPath(),
                            'message_log_format' => $this->getEmailLogFormat(),
                            'default_from' => 'noreply@vufind.org',
                        ],
                    ],
                    $config
                ),
            ]
        );
    }
}
