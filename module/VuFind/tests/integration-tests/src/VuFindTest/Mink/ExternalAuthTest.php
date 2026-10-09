<?php

/**
 * External authentication test class.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2026.
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

declare(strict_types=1);

namespace VuFindTest\Mink;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * External authentication test class.
 *
 * Class must be final due to use of "new static()" by LiveDatabaseTrait.
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
final class ExternalAuthTest extends \VuFindTest\Integration\MinkTestCase
{
    use \VuFindTest\Feature\DemoDriverTestTrait;
    use \VuFindTest\Feature\HttpRequestTrait;
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
     * Get config.ini override settings for testing ILS functions.
     *
     * @param bool    $anonymousTicket Use anonymous ticket?
     * @param ?string $hashMethod      Secret hash method
     * @param string  $ezproxyUrl      EZproxy base URL
     *
     * @return array
     */
    protected function getConfigIniOverrides(bool $anonymousTicket, ?string $hashMethod, string $ezproxyUrl): array
    {
        $ezproxyConfig = [
            'host' => $ezproxyUrl,
            'secret' => 'chocolate',
            'anonymous_ticket' => $anonymousTicket,
        ];
        if (null !== $hashMethod) {
            $ezproxyConfig['secret_hash_method'] = $hashMethod;
        }
        return [
            'Catalog' => [
                'driver' => 'Demo',
            ],
            'EZproxy' => $ezproxyConfig,
        ];
    }

    /**
     * Data provider for testEZproxyAuthorization.
     *
     * @return \Iterator
     */
    public static function ezproxyAuthorizationProvider(): \Iterator
    {
        yield 'with identity' => [
            false,
        ];
        yield 'anonymous' => [
            true,
        ];
        yield 'explicit SHA512' => [
            false,
            'SHA512',
        ];
        yield 'SHA1' => [
            false,
            'SHA1',
        ];
        yield 'missing permission' => [
            false,
            null,
            true,
        ];
    }

    /**
     * Test EZproxy authorization.
     *
     * @param bool    $anonymousTicket   Use anonymous ticket?
     * @param ?string $hashMethod        Secret hash method
     * @param bool    $missingPermission Permission missing?
     *
     * @return void
     */
    #[DataProvider('ezproxyAuthorizationProvider')]
    public function testEZproxyAuthorization(
        bool $anonymousTicket,
        ?string $hashMethod = null,
        bool $missingPermission = false
    ): void {
        // Bogus login URL, but it doesn't matter since the page won't handle the authorization response:
        $ezproxyUrl = $this->getVuFindUrl() . '/MyResearch';

        $configs = [
            'config' => $this->getConfigIniOverrides($anonymousTicket, $hashMethod, $ezproxyUrl),
            'Demo' => $this->getDemoIniOverrides(),
        ];
        if ($missingPermission) {
            $configs['permissions'] = [
                'ezproxy.authorized' => [
                    'permission' => 'foo',
                ],
            ];
        }

        $this->changeConfigs($configs);

        static::removeUsers(['username1']);

        // Go to authorization screen:
        $params = [
            'url' => $this->getVuFindUrl(), // Target URL (won't be reached)
        ];
        $session = $this->getMinkSession();
        $loginUrl = $this->getVuFindUrl('/ExternalAuth/EZproxyLogin') . '?' . http_build_query($params);
        $session->visit($loginUrl);
        $page = $session->getPage();

        $this->assertSame(
            'Login to access licensed material',
            $this->findCssAndGetText($page, '.flash-message')
        );

        // Set up user account:
        $this->clickCss($page, '.createAccountLink');
        $this->fillInAccountForm($page);
        $this->clickCss($page, 'input.btn.btn-primary');

        // Check results:
        if ($missingPermission) {
            $this->assertSame($loginUrl, $session->getCurrentUrl());
            $this->findCss($page, '.unauthorized-description');
            $this->assertSame(
                'Your login method does not provide access to licensed material. Please log out and then log in using'
                . ' another method.',
                $this->findCssAndGetText($page, '.unauthorized-description')
            );
        } else {
            [$host] = explode('?', $session->getCurrentUrl());
            $this->assertSame($ezproxyUrl . '/login', $host);

            $expectedUsername = $anonymousTicket ? 'anonymous' : 'username1';
            parse_str(parse_url($session->getCurrentUrl(), PHP_URL_QUERY), $queryParams);
            $this->assertArrayHasKey('user', $queryParams);
            $this->assertArrayHasKey('ticket', $queryParams);
            $this->assertArrayHasKey('url', $queryParams);
            $this->assertSame($expectedUsername, $queryParams['user']);
            $this->assertSame($this->getVuFindUrl(), $queryParams['url']);

            // Check ticket:
            $this->assertSame(1, preg_match('/(.*)\\$u(\d+)\\$e$/', $queryParams['ticket'], $matches));
            $receivedTicketBody = $matches[1];
            $time = $matches[2];

            $ticketBody = 'chocolate' . $expectedUsername . '$u' . $time . '$e';
            $ticketBody = hash($hashMethod ?? 'SHA512', $ticketBody);
            $this->assertSame($ticketBody, $receivedTicketBody);
        }
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
