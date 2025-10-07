<?php

/**
 * LDAP authentication test class.
 *
 * PHP version 8
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Auth;

use Laminas\Http\Request;
use VuFind\Auth\LDAP;
use VuFind\Config\Config;

/**
 * LDAP authentication test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class LDAPTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ReflectionTrait;

    /**
     * Get an authentication object.
     *
     * @param ?array $config Configuration to use (null for default)
     *
     * @return LDAP
     */
    public function getAuthObject(?array $config = null): LDAP
    {
        $obj = new LDAP($this->createMock(\VuFind\Auth\ILSAuthenticator::class));
        $obj->setConfig(new Config($config ?? $this->getAuthConfig()));
        return $obj;
    }

    /**
     * Get a working configuration for the LDAP object
     *
     * @return array
     */
    public function getAuthConfig(): array
    {
        $ldapConfig = [
            'host' => 'localhost',
            'port' => 1234,
            'basedn' => 'basedn',
            'username' => 'username',
        ];
        return ['LDAP' => $ldapConfig];
    }

    /**
     * Data provider for testWithMissingConfiguration.
     *
     * @return void
     */
    public static function configKeyProvider(): array
    {
        return [
            'missing host' => ['host'],
            'missing port' => ['port'],
            'missing basedn' => ['basedn'],
            'missing username' => ['username'],
        ];
    }

    /**
     * Verify that missing configuration causes failure.
     *
     * @param string $key Configuration key to exclude
     *
     * @return void
     *
     * @dataProvider configKeyProvider
     */
    public function testWithMissingConfiguration(string $key): void
    {
        $this->expectException(\VuFind\Exception\Auth::class);

        $config = $this->getAuthConfig();
        unset($config['LDAP'][$key]);
        $this->getAuthObject($config)->getConfig();
    }

    /**
     * Test case normalization of parameters.
     *
     * @return void
     */
    public function testCaseNormalization(): void
    {
        $config = $this->getAuthConfig();
        $config['LDAP']['username'] = 'UPPER';
        $config['LDAP']['basedn'] = 'MixedCase';
        $auth = $this->getAuthObject($config);
        // username should be lowercased:
        $this->assertEquals(
            'upper',
            $this->callMethod($auth, 'getSetting', ['username'])
        );
        // basedn should not:
        $this->assertEquals(
            'MixedCase',
            $this->callMethod($auth, 'getSetting', ['basedn'])
        );
    }

    /**
     * Test account creation is disallowed.
     *
     * @return void
     */
    public function testCreateIsDisallowed(): void
    {
        $this->assertFalse($this->getAuthObject()->supportsCreation());
    }

    /**
     * Support method -- get parameters to log into an account (but allow override of
     * individual parameters so we can test different scenarios).
     *
     * @param array $overrides Associative array of parameters to override.
     *
     * @return Request
     */
    protected function getLoginRequest(array $overrides = []): Request
    {
        $post = $overrides + [
            'username' => 'testuser', 'password' => 'testpass',
        ];
        $request = new Request();
        $request->setPost(new \Laminas\Stdlib\Parameters($post));
        return $request;
    }

    /**
     * Test login with blank username.
     *
     * @return void
     */
    public function testLoginWithBlankUsername(): void
    {
        $this->expectException(\VuFind\Exception\Auth::class);

        $request = $this->getLoginRequest(['username' => '']);
        $this->getAuthObject()->authenticate($request);
    }

    /**
     * Test login with blank password.
     *
     * @return void
     */
    public function testLoginWithBlankPassword(): void
    {
        $this->expectException(\VuFind\Exception\Auth::class);

        $request = $this->getLoginRequest(['password' => '']);
        $this->getAuthObject()->authenticate($request);
    }
}
