<?php
/**
 * Shibboleth authentication test class.
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
 * @link https://vufind.org Main Page
 */
namespace VuFindTest\Auth;

use Laminas\Config\Config;
use Laminas\Http\Headers;
use VuFind\Auth\ShibbolethWithWAYF;

/**
 * ShibbolethWithWAYF authentication test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Vaclav Rosecky <vaclav.rosecky@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link https://vufind.org Main Page
 */
class ShibbolethWithWAYFTest extends \VuFindTest\Unit\DbTestCase
{
    use \VuFindTest\Unit\UserCreationTrait;

    protected $user1 = [
        'Shib-Identity-Provider' => 'https://idp1.example.org/',
        'username' => 'testuser1',
        'userLibraryId' => 'testuser1',
        'mail' => 'testuser1@example.org',
    ];

    protected $user2 = [
        'Shib-Identity-Provider' => 'https://idp2.example.org/',
        'eppn' => 'testuser2',
        'userLibraryId' => 'testuser2',
        'mail' => 'testuser2@example.org',
    ];

    protected $proxyUser = [
        'HTTP_SHIB_IDENTITY_PROVIDER' => 'https://idp1.example.org/',
        'HTTP_USERNAME' => 'testuser3',
        'HTTP_USERLIBRARYID' => 'testuser3',
        'HTTP_MAIL' => 'testuser3@example.org',
    ];

    /**
     * Standard setup method.
     *
     * @return void
     */
    public static function setUpBeforeClass(): void
    {
        static::failIfUsersExist();
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
     * Get an authentication object.
     *
     * @param bool   $proxy  shibboleth is behind HTTP proxy
     * @param Config $config Configuration to use (null for default)
     * @param Config $shibConfig Shibboleth configuration to use (null for default)
     *
     * @return ShibbolethWithWAYF
     */
    public function getAuthObject($proxy = false, $config = null, $shibConfig = null)
    {
        if (null === $config) {
            $config = $this->getAuthConfig($proxy);
        }
        if (null === $shibConfig) {
            $shibConfig = $this->getShibbolethConfig();
        }
        $obj = new ShibbolethWithWAYF(
            $this->createMock(\Laminas\Session\ManagerInterface::class),
            $shibConfig
        );
        $initializer = new \VuFind\ServiceManager\ServiceInitializer();
        $initializer($this->getServiceManager(), $obj);
        $obj->setConfig($config);
        return $obj;
    }

    /**
     * Get a working configuration for the LDAP object
     *
     * @param bool   $proxy  shibboleth is behind HTTP proxy
     *
     * @return Config
     */
    public function getAuthConfig($proxy = false)
    {
        $config = new Config(
            [
                'login' => 'http://localhost/Shibboleth.sso/Login',
                'username' => 'username',
                'email' => 'email',
                'proxy' => $proxy,
            ], true
        );
        return new Config(['Shibboleth' => $config], true);
    }

    /**
     * Get a working configuration for the Shibboleth object
     *
     * @return Config
     */
    public function getShibbolethConfig()
    {
        $example1 = new Config(
            [
                'entityId' => 'https://idp1.example.org/',
                'username' => 'username',
                'email' => 'email',
                'cat_username' => 'userLibraryId',
            ], true
        );
        $example2 = new Config(
            [
                'entityId' => 'https://idp2.example.org/',
                'username' => 'eppn',
                'email' => 'email',
                'cat_username' => 'userLibraryId',
            ], true
        );
        $config = [
            'example1' => $example1,
            'example2' => $example2,
        ];
        return new Config($config, true);
    }

    /**
     * Support method -- get parameters to log into an account (but allow override of
     * individual parameters so we can test different scenarios).
     *
     * @param array $overrides Associative array of parameters to override.
     *
     * @return \Laminas\Http\Request
     */
    protected function getLoginRequest($user, $proxy)
    {
        $request = new \Laminas\Http\PhpEnvironment\Request();
        if ($proxy) {
            $headers = new Headers();
            $headers->addHeaders($user);
            $request->setHeaders($headers);
        } else {
            $request->setServer(new \Laminas\Stdlib\Parameters($user));
        }
        return $request;
    }

    public function testLogin1()
    {
        $user = $this->getAuthObject()->authenticate($this->getLoginRequest($this->user1, false));
        $this->assertEquals($user->cat_username, 'example1.testuser1');
        $this->assertEquals($user->username, 'testuser1');
    }

    public function testLogin2()
    {
        $user = $this->getAuthObject()->authenticate($this->getLoginRequest($this->user2, false));
        $this->assertEquals($user->cat_username, 'example2.testuser2');
        $this->assertEquals($user->username, 'testuser2');
    }

    public function testProxyLogin()
    {
        $user = $this->getAuthObject(true)->authenticate($this->getLoginRequest($this->proxyUser, true));
        $this->assertEquals($user->cat_username, 'example1.testuser3');
        $this->assertEquals($user->username, 'testuser3');
    }

    /**
     * Standard teardown method.
     *
     * @return void
     */
    public static function tearDownAfterClass(): void
    {
        static::removeUsers(['testuser1', 'testuser2', 'testuser3']);
    }
}
