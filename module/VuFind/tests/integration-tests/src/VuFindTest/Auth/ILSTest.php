<?php

/**
 * ILS authentication test class.
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

/**
 * ILS authentication test class.
 *
 * Class must be final due to use of "new static()" by LiveDatabaseTrait.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
final class ILSTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ConfigPluginManagerTrait;
    use \VuFindTest\Feature\LiveDatabaseTrait;
    use \VuFindTest\Feature\LiveDetectionTrait;

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
     * Get a mock ILS driver to test.
     *
     * @param string $type    Driver type to mock (default = Sample)
     * @param array  $methods Methods to mock
     *
     * @return \VuFind\ILS\Driver\Sample
     */
    protected function getMockDriver($type = 'Sample', $methods = ['patronLogin'])
    {
        return $this->getMockBuilder('VuFind\ILS\Driver\\' . $type)
            ->disableOriginalConstructor()
            ->onlyMethods($methods)
            ->getMock();
    }

    /**
     * Get the object to test.
     *
     * @param \VuFind\ILS\Driver\AbstractBase $driver Mock ILS driver to test with.
     * @param array                           $patron Logged in patron for mock
     * authenticator (null for none)
     *
     * @return \VuFind\Auth\ILS
     */
    protected function getAuth($driver = null, $patron = null)
    {
        if (empty($driver)) {
            $driver = $this->getMockDriver();
        }
        $authenticator = $this->getMockILSAuthenticator($patron);
        $driverManager = new \VuFind\ILS\Driver\PluginManager(
            new \VuFindTest\Container\MockContainer($this)
        );
        $driverManager->setService('Sample', $driver);
        $mockConfigReader = $this->getMockConfigPluginManager([]);
        $auth = new \VuFind\Auth\ILS(
            new \VuFind\ILS\Connection(
                new \Laminas\Config\Config(['driver' => 'Sample']),
                $driverManager,
                $mockConfigReader
            ),
            $authenticator
        );
        $auth->setDbServiceManager($this->getLiveDbServiceManager());
        $auth->getCatalog()->setDriver($driver);
        return $auth;
    }

    /**
     * Test account creation is disallowed.
     *
     * @return void
     */
    public function testCreateIsDisallowed()
    {
        $this->assertFalse($this->getAuth()->supportsCreation());
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
            'username' => 'testuser', 'password' => 'testpass',
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
        $this->getAuth()->authenticate($request);
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
        $this->getAuth()->authenticate($request);
    }

    /**
     * Test login with technical error.
     *
     * @return void
     */
    public function testBadLoginResponse()
    {
        $this->expectException(\VuFind\Exception\Auth::class);

        // VuFind requires the ILS driver to return a value in cat_username
        // by default -- if that is missing, we should fail.
        $response = [];
        $driver = $this->getMockDriver();
        $driver->expects($this->once())->method('patronLogin')
            ->with($this->equalTo('testuser'), $this->equalTo('testpass'))
            ->will($this->returnValue($response));
        $this->getAuth($driver)->authenticate($this->getLoginRequest());
    }

    /**
     * Test successful login.
     *
     * @return void
     */
    public function testLogin()
    {
        $response = [
            'cat_username' => 'testuser', 'cat_password' => 'testpass',
            'email' => 'user@test.com',
        ];
        $driver = $this->getMockDriver();
        $driver->expects($this->once())->method('patronLogin')
            ->with($this->equalTo('testuser'), $this->equalTo('testpass'))
            ->will($this->returnValue($response));
        $user = $this->getAuth($driver)->authenticate($this->getLoginRequest());
        $this->assertEquals('testuser', $user->getUsername());
        $this->assertEquals('user@test.com', $user->getEmail());
    }

    /**
     * Test failure caused by missing cat_id.
     *
     * @return void
     */
    public function testLoginWithMissingCatId()
    {
        $this->expectException(\VuFind\Exception\Auth::class);
        $this->expectExceptionMessage('authentication_error_technical');

        $response = [
            'cat_username' => 'testuser', 'cat_password' => 'testpass',
            'email' => 'user@test.com',
        ];
        $driver = $this->getMockDriver();
        $driver->expects($this->once())->method('patronLogin')
            ->with($this->equalTo('testuser'), $this->equalTo('testpass'))
            ->will($this->returnValue($response));
        $auth = $this->getAuth($driver);
        // Configure the authenticator to look for a cat_id; since there is no
        // cat_id in the response above, this will throw an exception.
        $config = ['Authentication' => ['ILS_username_field' => 'cat_id']];
        $auth->setConfig(new \Laminas\Config\Config($config));
        $auth->authenticate($this->getLoginRequest());
    }

    /**
     * Test updating a user's password with mismatched new password values.
     *
     * @return void
     */
    public function testUpdateUserPasswordWithEmptyValue()
    {
        $this->expectException(\VuFind\Exception\Auth::class);
        $this->expectExceptionMessage('Password cannot be blank');

        $patron = ['cat_username' => 'testuser'];
        $request = $this->getLoginRequest(
            [
                'oldpwd' => 'foo',
                'password' => '',
                'password2' => '',
            ]
        );
        $this->getAuth(null, $patron)->updatePassword($request);
    }

    /**
     * Test updating a user's password with mismatched new password values.
     *
     * @return void
     */
    public function testUpdateUserPasswordWithoutLoggedInUser()
    {
        $this->expectException(\VuFind\Exception\Auth::class);
        $this->expectExceptionMessage('authentication_error_technical');

        $request = $this->getLoginRequest(
            [
                'oldpwd' => 'foo',
                'password' => 'bar',
                'password2' => 'bar',
            ]
        );
        $this->getAuth()->updatePassword($request);
    }

    /**
     * Test updating a user's password with mismatched new password values.
     *
     * @return void
     */
    public function testUpdateUserPasswordWithMismatch()
    {
        $this->expectException(\VuFind\Exception\Auth::class);
        $this->expectExceptionMessage('Passwords do not match');

        $request = $this->getLoginRequest(
            [
                'oldpwd' => 'foo',
                'password' => 'pass',
                'password2' => 'fail',
            ]
        );
        $patron = ['cat_username' => 'testuser'];
        $this->getAuth(null, $patron)->updatePassword($request);
    }

    /**
     * Test updating a user's password.
     *
     * @return void
     */
    public function testUpdateUserPassword()
    {
        $request = $this->getLoginRequest(
            [
                'oldpwd' => 'foo',
                'password' => 'newpass',
                'password2' => 'newpass',
            ]
        );
        $driver = $this->getMockDriver('Demo', ['changePassword']);
        $driver->expects($this->once())->method('changePassword')
            ->will($this->returnValue(['success' => true]));
        $patron = ['cat_username' => 'testuser'];
        $user = $this->getAuth($driver, $patron)->updatePassword($request);
        $this->assertEquals('testuser', $user->getUsername());
        $this->assertEquals('newpass', $user->getRawCatPassword());
    }

    /**
     * Test updating a user's password (identifying user with cat_id field).
     *
     * @return void
     */
    public function testUpdateUserPasswordUsingCatIdField()
    {
        $request = $this->getLoginRequest(
            [
                'oldpwd' => 'foo',
                'password' => 'newpass',
                'password2' => 'newpass',
            ]
        );
        $driver = $this->getMockDriver('Demo', ['changePassword']);
        $driver->expects($this->once())->method('changePassword')
            ->will($this->returnValue(['success' => true]));
        $patron = ['cat_username' => 'testuser', 'cat_id' => '1234'];
        $auth = $this->getAuth($driver, $patron);
        $config = ['Authentication' => ['ILS_username_field' => 'cat_id']];
        $auth->setConfig(new \Laminas\Config\Config($config));
        $user = $auth->updatePassword($request);
        $this->assertEquals('1234', $user->getUsername());
        $this->assertEquals('newpass', $user->getRawCatPassword());
    }

    /**
     * Standard teardown method.
     *
     * @return void
     */
    public static function tearDownAfterClass(): void
    {
        static::removeUsers(['1234', 'testuser']);
    }

    /**
     * Get mock ILS authenticator
     *
     * @param array $patron Logged in patron to simulate (null for none).
     *
     * @return \VuFind\Auth\ILSAuthenticator
     */
    protected function getMockILSAuthenticator($patron = null)
    {
        $mock = $this->getMockBuilder(\VuFind\Auth\ILSAuthenticator::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['storedCatalogLogin'])
            ->getMock();
        $mock->expects($this->any())->method('storedCatalogLogin')->willReturn($patron);
        $mock->setDbServiceManager($this->getLiveDbServiceManager());
        return $mock;
    }
}
