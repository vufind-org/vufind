<?php
/**
 * LDAP authentication test class.
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

namespace VuFindTest\Auth;

use Laminas\Config\Config;
use VuFind\Auth\LDAP;

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
     * @param Config $config Configuration to use (null for default)
     *
     * @return LDAP
     */
    public function getAuthObject($config = null)
    {
        if (null === $config) {
            $config = $this->getAuthConfig();
        }
        $authManager = new \VuFind\Auth\PluginManager(
            new \VuFindTest\Container\MockContainer($this)
        );
        $obj = $authManager->get('LDAP');
        $obj->setConfig($config);
        return $obj;
    }

    /**
     * Get a working configuration for the LDAP object
     *
     * @return Config
     */
    public function getAuthConfig()
    {
        $ldapConfig = new Config(
            [
                'host' => 'localhost',
                'port' => 1234,
                'basedn' => 'basedn',
                'username' => 'username'
            ],
            true
        );
        return new Config(['LDAP' => $ldapConfig], true);
    }

    /**
     * Verify that missing host causes failure.
     *
     * @return void
     */
    public function testWithMissingHost()
    {
        $this->expectException(\VuFind\Exception\Auth::class);

        $config = $this->getAuthConfig();
        unset($config->LDAP->host);
        $this->getAuthObject($config)->getConfig();
    }

    /**
     * Verify that missing port causes failure.
     *
     * @return void
     */
    public function testWithMissingPort()
    {
        $this->expectException(\VuFind\Exception\Auth::class);

        $config = $this->getAuthConfig();
        unset($config->LDAP->port);
        $this->getAuthObject($config)->getConfig();
    }

    /**
     * Verify that missing baseDN causes failure.
     *
     * @return void
     */
    public function testWithMissingBaseDN()
    {
        $this->expectException(\VuFind\Exception\Auth::class);

        $config = $this->getAuthConfig();
        unset($config->LDAP->basedn);
        $this->getAuthObject($config)->getConfig();
    }

    /**
     * Verify that missing UID causes failure.
     *
     * @return void
     */
    public function testWithMissingUid()
    {
        $this->expectException(\VuFind\Exception\Auth::class);

        $config = $this->getAuthConfig();
        unset($config->LDAP->username);
        $this->getAuthObject($config)->getConfig();
    }

    /**
     * Test case normalization of parameters.
     *
     * @return void
     */
    public function testCaseNormalization()
    {
        $config = $this->getAuthConfig();
        $config->LDAP->username = 'UPPER';
        $config->LDAP->basedn = 'MixedCase';
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
    public function testCreateIsDisallowed()
    {
        $this->assertFalse($this->getAuthObject()->supportsCreation());
    }

    /**
     * Support method -- get parameters to log into an account (but allow override of
     * individual parameters so we can test different scenarios).
     *
     * @param array $overrides Associative array of parameters to override.
     *
     * @return \Laminas\Http\Request
     */
    protected function getLoginRequest($overrides = [])
    {
        $post = $overrides + [
            'username' => 'testuser', 'password' => 'testpass'
        ];
        $request = new \Laminas\Http\Request();
        $request->setPost(new \Laminas\Stdlib\Parameters($post));
        return $request;
    }

    /**
     * Test login with blank username.
     *
     * @return void
     */
    public function testLoginWithBlankUsername()
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
    public function testLoginWithBlankPassword()
    {
        $this->expectException(\VuFind\Exception\Auth::class);

        $request = $this->getLoginRequest(['password' => '']);
        $this->getAuthObject()->authenticate($request);
    }
}
