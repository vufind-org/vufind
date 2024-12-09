<?php

/**
 * Shibboleth authentication test class.
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
use Laminas\Http\Headers;
use VuFind\Auth\Shibboleth;
use VuFind\Auth\Shibboleth\MultiIdPConfigurationLoader;
use VuFind\Auth\Shibboleth\SingleIdPConfigurationLoader;

/**
 * Shibboleth authentication test class.
 *
 * Class must be final due to use of "new static()" by LiveDatabaseTrait.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
final class ShibbolethTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\LiveDatabaseTrait;
    use \VuFindTest\Feature\LiveDetectionTrait;

    protected $user1 = [
        'Shib-Identity-Provider' => 'https://idp1.example.org/',
        'username' => 'testuser1',
        'userLibraryId' => 'testuser1',
        'mail' => 'testuser1@example.org',
    ];

    protected $user2 = [
        'Shib-Identity-Provider' => 'https://idp2.example.org/',
        'eppn' => 'testuser2',
        'alephId' => '12345',
        'mail' => 'testuser2@example.org',
        'eduPersonScopedAffiliation' => 'member@example.org',
    ];

    protected $user3 = [
        'Shib-Identity-Provider' => 'https://idp2.example.org/',
        'eppn' => 'testuser3',
        'alephId' => 'testuser3',
        'mail' => 'testuser3@example.org',
    ];

    protected $proxyUser = [
        'Shib-Identity-Provider' => 'https://idp1.example.org/',
        'username' => 'testuser3',
        'userLibraryId' => 'testuser3',
        'mail' => 'testuser3@example.org',
    ];

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
     * @param Config  $config             Configuration to use (null for default)
     * @param Config  $shibConfig         Configuration with IdP
     * @param boolean $useHeaders         use HTTP headers instead of environment variables
     * @param boolean $requiredAttributes required attributes
     *
     * @return Shibboleth
     */
    public function getAuthObject($config = null, $shibConfig = null, $useHeaders = false, $requiredAttributes = true)
    {
        if (null === $config) {
            $config = $this->getAuthConfig($useHeaders, $requiredAttributes);
        }
        $loader = ($shibConfig == null)
            ? new SingleIdPConfigurationLoader($config)
            : new MultiIdPConfigurationLoader($config, $shibConfig);
        $obj = new Shibboleth(
            $this->createMock(\Laminas\Session\ManagerInterface::class),
            $loader,
            $this->createMock(\Laminas\Http\PhpEnvironment\Request::class),
            $this->createMock(\VuFind\Auth\ILSAuthenticator::class)
        );
        $obj->setDbServiceManager($this->getLiveDbServiceManager());
        $obj->setDbTableManager($this->getLiveTableManager());
        $obj->setConfig($config);
        return $obj;
    }

    /**
     * Get a working configuration for the Shibboleth object
     *
     * @param bool $useHeaders         Value for use_headers config setting
     * @param bool $requiredAttributes Should we include a required attribute in config?
     *
     * @return Config
     */
    public function getAuthConfig($useHeaders = false, $requiredAttributes = true)
    {
        $config = [
            'login' => 'http://myserver',
            'username' => 'username',
            'email' => 'email',
            'use_headers' => $useHeaders,
        ];
        if ($requiredAttributes) {
            $config += [
                'userattribute_1' => 'password',
                'userattribute_value_1' => 'testpass',
            ];
        }
        $shibConfig = new Config($config, true);
        return new Config(['Shibboleth' => $shibConfig], true);
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
            ],
            true
        );
        $example2 = new Config(
            [
                'entityId' => 'https://idp2.example.org/',
                'username' => 'eppn',
                'email' => 'email',
                'cat_username' => 'alephId',
                'userattribute_1' => 'eduPersonScopedAffiliation',
                'userattribute_value_1' => 'member@example.org',
            ],
            true
        );
        $config = [
            'example1' => $example1,
            'example2' => $example2,
        ];
        return new Config($config, true);
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
     * @param array   $overrides  Associative array of parameters to override.
     * @param boolean $useHeaders Use headers instead of environment variables
     *
     * @return \Laminas\Http\Request
     */
    protected function getLoginRequest($overrides = [], $useHeaders = false)
    {
        $server = $overrides + [
            'username' => 'testuser', 'email' => 'user@test.com',
            'password' => 'testpass',
        ];
        $request = new \Laminas\Http\PhpEnvironment\Request();
        if ($useHeaders) {
            $headers = new Headers();
            $headers->addHeaders($server);
            $request->setHeaders($headers);
        } else {
            $request->setServer(new \Laminas\Stdlib\Parameters($server));
        }
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
     * Test login with blank username.
     *
     * @return void
     */
    public function testLoginWithBlankPassword()
    {
        $this->expectException(\VuFind\Exception\Auth::class);

        $request = $this->getLoginRequest(['password' => '']);
        $this->getAuthObject()->authenticate($request);
    }

    /**
     * Test a configuration with a missing attribute value.
     *
     * @return void
     */
    public function testWithMissingAttributeValue()
    {
        $this->expectException(\VuFind\Exception\Auth::class);

        $config = $this->getAuthConfig();
        unset($config->Shibboleth->userattribute_value_1);
        $this->getAuthObject($config)->authenticate($this->getLoginRequest());
    }

    /**
     * Test a configuration with missing username.
     *
     * @return void
     */
    public function testWithoutUsername()
    {
        $this->expectException(\VuFind\Exception\Auth::class);

        $config = $this->getAuthConfig();
        unset($config->Shibboleth->username);
        $this->getAuthObject($config)->authenticate($this->getLoginRequest());
    }

    /**
     * Test a configuration with missing login setting.
     *
     * @return void
     */
    public function testWithoutLoginSetting()
    {
        $this->expectException(\VuFind\Exception\Auth::class);

        $config = $this->getAuthConfig();
        unset($config->Shibboleth->login);
        $this->getAuthObject($config)->getSessionInitiator('http://target');
    }

    /**
     * Test session initiator
     *
     * @return void
     */
    public function testSessionInitiator()
    {
        $this->assertEquals(
            'http://myserver?target=http%3A%2F%2Ftarget%3Fauth_method%3DShibboleth',
            $this->getAuthObject()->getSessionInitiator('http://target')
        );
    }

    /**
     * Test successful login.
     *
     * @return void
     */
    public function testLogin()
    {
        $user = $this->getAuthObject()->authenticate($this->getLoginRequest());
        $this->assertEquals('testuser', $user->username);
        $this->assertEquals('user@test.com', $user->email);
    }

    /**
     * Test successful login.
     *
     * @return void
     */
    public function testLogin1()
    {
        $user = $this->getAuthObject(null, $this->getShibbolethConfig())
            ->authenticate($this->getLoginRequest($this->user1, false));
        $this->assertEquals($user->cat_username, 'example1.testuser1');
        $this->assertEquals($user->username, 'testuser1');
    }

    /**
     * Test successful login.
     *
     * @return void
     */
    public function testLogin2()
    {
        $user = $this->getAuthObject(null, $this->getShibbolethConfig())
            ->authenticate($this->getLoginRequest($this->user2, false));
        $this->assertEquals($user->cat_username, 'example2.12345');
        $this->assertEquals($user->username, 'testuser2');
    }

    /**
     * Test failed login.
     *
     * @return void
     */
    public function testFailedLogin()
    {
        $this->expectException(\VuFind\Exception\Auth::class);
        $this->getAuthObject(null, $this->getShibbolethConfig())
            ->authenticate($this->getLoginRequest($this->user3, false));
    }

    /**
     * Test login using attributes passed in headers.
     *
     * @return void
     */
    public function testProxyLogin()
    {
        $user = $this->getAuthObject(null, $this->getShibbolethConfig(), true, false)
            ->authenticate($this->getLoginRequest($this->proxyUser, true));
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
        static::removeUsers(['testuser', 'testuser1', 'testuser2', 'testuser3']);
    }
}
