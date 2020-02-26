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
 * @link     https://vufind.org Main Page
 */
namespace VuFindTest\Auth;

use VuFind\Auth\Shibboleth;
use Zend\Config\Config;

/**
 * Shibboleth authentication test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class ShibbolethTest extends \VuFindTest\Unit\DbTestCase
{
    use \VuFindTest\Unit\UserCreationTrait;

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
     * @param Config $config Configuration to use (null for default)
     *
     * @return LDAP
     */
    public function getAuthObject($config = null)
    {
        if (null === $config) {
            $config = $this->getAuthConfig();
        }
        $obj = new Shibboleth($this->createMock(\Zend\Session\ManagerInterface::class));
        $initializer = new \VuFind\ServiceManager\ServiceInitializer();
        $initializer($this->getServiceManager(), $obj);
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
                'login' => 'http://myserver',
                'username' => 'username',
                'email' => 'email',
                'userattribute_1' => 'password',
                'userattribute_value_1' => 'testpass'
            ], true
        );
        return new Config(['Shibboleth' => $ldapConfig], true);
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
     * @return \Zend\Http\Request
     */
    protected function getLoginRequest($overrides = [])
    {
        $server = $overrides + [
            'username' => 'testuser', 'email' => 'user@test.com',
            'password' => 'testpass'
        ];
        $request = new \Zend\Http\PhpEnvironment\Request();
        $request->setServer(new \Zend\Stdlib\Parameters($server));
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
     * Standard teardown method.
     *
     * @return void
     */
    public static function tearDownAfterClass(): void
    {
        static::removeUsers('testuser');
    }
}
