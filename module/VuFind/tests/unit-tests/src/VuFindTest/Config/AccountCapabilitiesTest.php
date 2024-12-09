<?php

/**
 * Account Capabilities Test Class
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
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Config;

use Laminas\Config\Config;
use VuFind\Auth\Manager;
use VuFind\Config\AccountCapabilities;

/**
 * Account Capabilities Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class AccountCapabilitiesTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Get an AccountCapabilities object to test.
     *
     * @param array    $config Configuration
     * @param ?Manager $auth   Optional auth manager (if omitted, a mock will be created)
     *
     * @return AccountCapabilities
     */
    protected function getCapabilities(array $config = [], ?Manager $auth = null): AccountCapabilities
    {
        $auth ??= $this->createMock(Manager::class);
        $getAuth = function () use ($auth) {
            return $auth;
        };
        return new AccountCapabilities(new Config($config), $getAuth);
    }

    /**
     * Data provider for testGetEmailActionSettings().
     *
     * @return array[]
     */
    public static function emailActionSettingsProvider(): array
    {
        return [
            'email_action setting' => [['email_action' => 'foo'], 'foo'],
            'legacy require_login true' => [['require_login' => true], 'require_login'],
            'legacy require_login false' => [['require_login' => false], 'enabled'],
            'default (no config)' => [[], 'require_login'],
        ];
    }

    /**
     * Test getEmailActionSettings()
     *
     * @param array  $mailConfig Settings for Mail configuration section
     * @param string $expected   Expected return value
     *
     * @return void
     *
     * @dataProvider emailActionSettingsProvider
     */
    public function testGetEmailActionSettings(array $mailConfig, string $expected): void
    {
        $capabilities = $this->getCapabilities(['Mail' => $mailConfig]);
        $this->assertEquals($expected, $capabilities->getEmailActionSetting());
    }

    /**
     * Data provider for testIsEmailActionAvailable()
     *
     * @return array[]
     */
    public static function emailActionAvailableProvider(): array
    {
        return [
            'disabled, login' => ['disabled', true, false],
            'disabled, no login' => ['disabled', false, false],
            'enabled, login' => ['enabled', true, true],
            'enabled, no login' => ['enabled', false, true],
            'require_login, login' => ['require_login', true, true],
            'require_login, no login' => ['require_login', false, false],
        ];
    }

    /**
     * Test isEmailActionAvailable()
     *
     * @param string $mailSetting  The email_action config setting
     * @param bool   $loginEnabled Is login enabled?
     * @param bool   $expected     The expected result
     *
     * @return void
     *
     * @dataProvider emailActionAvailableProvider
     */
    public function testIsEmailActionAvailable(string $mailSetting, bool $loginEnabled, bool $expected): void
    {
        $config = ['Mail' => ['email_action' => $mailSetting]];
        $auth = $this->createMock(Manager::class);
        $auth->method('loginEnabled')->willReturn($loginEnabled);
        $capabilities = $this->getCapabilities($config, $auth);
        $this->assertEquals($expected, $capabilities->isEmailActionAvailable());
    }
}
