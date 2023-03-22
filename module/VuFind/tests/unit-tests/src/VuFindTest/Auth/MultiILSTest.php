<?php

/**
 * MultiILS authentication test class.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2022.
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

namespace VuFindTest\Auth;

use VuFind\Auth\ILSAuthenticator;
use VuFind\Auth\MultiILS;
use VuFind\ILS\Driver\MultiBackend;
use VuFindTest\Container\MockDbTablePluginManager;

/**
 * MultiILS authentication test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class MultiILSTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ConfigPluginManagerTrait;

    /**
     * Container for building mocks.
     *
     * @var \VuFindTest\Container\MockContainer
     */
    protected $container;

    /**
     * Standard setup method.
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->container = new \VuFindTest\Container\MockContainer($this);
    }

    /**
     * Test account creation is disallowed.
     *
     * @return void
     */
    public function testCreateIsDisallowed()
    {
        $this->assertFalse($this->getMultiILS()->supportsCreation());
    }

    /**
     * Test login with empty invalid target.
     *
     * @return void
     */
    public function testLoginWithEmptyTarget()
    {
        $this->expectException(\VuFind\Exception\Auth::class);

        $request = $this->getLoginRequest(['target' => '']);
        $this->getMultiILS()->authenticate($request);
    }

    /**
     * Test login with invalid target.
     *
     * @return void
     */
    public function testLoginWithInvalidTarget()
    {
        $this->expectException(\VuFind\Exception\Auth::class);

        $request = $this->getLoginRequest(['target' => 'bad']);
        $this->getMultiILS()->authenticate($request);
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
        $this->getMultiILS()->authenticate($request);
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
        $this->getMultiILS()->authenticate($request);
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
        $driver = $this->getMockMultiBackend();
        $driver->expects($this->once())->method('patronLogin')
            ->with($this->equalTo('ils1.testuser'), $this->equalTo('testpass'))
            ->will($this->returnValue($response));
        $this->getMultiILS($driver)->authenticate($this->getLoginRequest());
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
        $driver = $this->getMockMultiBackend();
        $driver->expects($this->once())->method('patronLogin')
            ->with($this->equalTo('ils1.testuser'), $this->equalTo('testpass'))
            ->will($this->returnValue($response));
        $user = $this->getMultiILS($driver)->authenticate($this->getLoginRequest());
        $this->assertEquals('ils1.testuser', $user->username);
        $this->assertEquals('user@test.com', $user->email);
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
        $driver = $this->getMockMultiBackend();
        $driver->expects($this->once())->method('patronLogin')
            ->with($this->equalTo('ils1.testuser'), $this->equalTo('testpass'))
            ->will($this->returnValue($response));
        $auth = $this->getMultiILS($driver);
        // Configure the authenticator to look for a cat_id; since there is no
        // cat_id in the response above, this will throw an exception.
        $config = ['Authentication' => ['ILS_username_field' => 'cat_id']];
        $auth->setConfig(new \Laminas\Config\Config($config));
        $auth->authenticate($this->getLoginRequest());
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
            'username' => 'testuser', 'password' => 'testpass', 'target' => 'ils1',
        ];
        $request = new \Laminas\Http\Request();
        $request->setPost(new \Laminas\Stdlib\Parameters($post));
        return $request;
    }

    /**
     * Get mock ILS authenticator
     *
     * @param array $patron Logged in patron to simulate (null for none).
     *
     * @return ILSAuthenticator
     */
    protected function getMockILSAuthenticator($patron = null): ILSAuthenticator
    {
        $mock = $this->getMockBuilder(ILSAuthenticator::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['storedCatalogLogin'])
            ->getMock();
        $mock->expects($this->any())->method('storedCatalogLogin')
            ->willReturn($patron);
        return $mock;
    }

    /**
     * Get a mock MultiBackend driver to test.
     *
     * @param array $onlyMethods Existing methods to mock (in addition to
     * supportsMethod)
     * @param array $addMethods  New methods to mock (in addition to
     * getLoginDrivers)
     *
     * @return MockObject&MultiBackend
     */
    protected function getMockMultiBackend(
        $onlyMethods = [],
        $addMethods = ['patronLogin']
    ): MultiBackend {
        $onlyMethods[] = 'supportsMethod';
        $onlyMethods[] = 'getLoginDrivers';
        $onlyMethods[] = 'getConfig';
        $configLoader = $this->getMockBuilder(\VuFind\Config\PluginManager::class)
            ->setConstructorArgs([$this->container])
            ->getMock();
        $ilsAuth = $this->getMockBuilder(\VuFind\Auth\ILSAuthenticator::class)
            ->disableOriginalConstructor()
            ->getMock();
        $driverManager
            = $this->getMockBuilder(\VuFind\ILS\Driver\PluginManager::class)
            ->setConstructorArgs([$this->container])
            ->getMock();
        $driver = $this->getMockBuilder(\VuFind\ILS\Driver\MultiBackend::class)
            ->setConstructorArgs([$configLoader, $ilsAuth, $driverManager])
            ->onlyMethods($onlyMethods)
            ->addMethods($addMethods)
            ->getMock();
        $driver->expects($this->any())
            ->method('getLoginDrivers')
            ->willReturn(['ils1']);
        $driver->expects($this->any())
            ->method('supportsMethod')
            ->willReturn(true);
        $driver->expects($this->any())
            ->method('getConfig')
            ->willReturn(new \Laminas\Config\Config([]));

        return $driver;
    }

    /**
     * Get the object to test.
     *
     * @param ?MultiBackend $driver Mock MultiBackend driver to test with.
     * @param ?array        $patron Logged in patron for mock
     * authenticator (null for none)
     *
     * @return MultiILS
     */
    protected function getMultiILS(
        MultiBackend $driver = null,
        array $patron = null
    ): MultiILS {
        if (empty($driver)) {
            $driver = $this->getMockMultiBackend();
        }
        $mockAuthenticator = $this->getMockILSAuthenticator($patron);
        $mockUser = $this->getMockBuilder(\VuFind\Db\Row\User::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['saveCredentials', 'updateEmail'])
            ->getMock();
        $mockUser->username = 'ils1.testuser';
        $mockUser->expects($this->any())
            ->method('updateEmail')
            ->willReturnCallback(
                function ($email) use (&$mockUser) {
                    $mockUser->email = $email;
                }
            );
        $mockUserTable = $this->getMockBuilder(\VuFind\Db\Table\User::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockUserTable->expects($this->any())
            ->method('getByUsername')
            ->willReturn($mockUser);
        $mockTableManager = new MockDbTablePluginManager($this);
        $mockTableManager->set('User', $mockUserTable);
        $this->container
            ->set(\VuFind\Db\Table\PluginManager::class, $mockTableManager);
        $driverManager = new \VuFind\ILS\Driver\PluginManager($this->container);
        $parts = explode('\\', get_class($driver));
        $driverClass = end($parts);
        $mockConfigReader = $this->getMockConfigPluginManager(
            [
                $driverClass => [
                    'Drivers' => [
                        'ils1' => 'Sample',
                    ],
                    'Login' => [
                        'drivers' => ['ils1'],
                    ],
                ],
            ],
        );
        $connection = new \VuFind\ILS\Connection(
            new \Laminas\Config\Config(['driver' => 'MultiBackend']),
            $driverManager,
            $mockConfigReader
        );
        $connection->setDriver($driver);

        $auth = new \VuFind\Auth\MultiILS($connection, $mockAuthenticator);
        $auth->setDbTableManager($mockTableManager);
        return $auth;
    }
}
