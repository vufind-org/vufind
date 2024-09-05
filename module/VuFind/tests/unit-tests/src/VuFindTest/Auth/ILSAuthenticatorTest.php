<?php

/**
 * ILS Authenticator Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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

namespace VuFindTest\Auth;

use PHPUnit\Framework\MockObject\MockObject;
use VuFind\Auth\EmailAuthenticator;
use VuFind\Auth\ILSAuthenticator;
use VuFind\Auth\Manager;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\UserCardService;
use VuFind\Db\Service\UserCardServiceInterface;
use VuFind\Db\Service\UserServiceInterface;
use VuFind\ILS\Connection as ILSConnection;
use VuFindTest\Container\MockDbServicePluginManager;

/**
 * ILS Authenticator Manager Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ILSAuthenticatorTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test new catalog login success.
     *
     * @return void
     */
    public function testNewCatalogLoginSuccess(): void
    {
        $user = $this->getMockUser();
        $manager = $this->getMockManager(['getUserObject', 'updateSession']);
        $manager->expects($this->any())->method('getUserObject')->willReturn($user);
        $manager->expects($this->once())->method('updateSession')->with($this->equalTo($user));
        $details = ['foo' => 'bar'];
        $connection = $this->getMockConnection(['patronLogin']);
        $connection->expects($this->once())->method('patronLogin')
            ->with($this->equalTo('user'), $this->equalTo('pass'))->will($this->returnValue($details));
        $auth = $this->getAuthenticator($manager, $connection);
        $mockServices = new MockDbServicePluginManager($this);
        $userService = $this->createMock(UserServiceInterface::class);
        $userService->expects($this->once())->method('persistEntity')->with($user);
        $mockServices->set(UserServiceInterface::class, $userService);
        $userCardService = $this->createMock(UserCardService::class);
        $userCardService->expects($this->once())->method('synchronizeUserLibraryCardData')->with($user);
        $mockServices->set(UserCardServiceInterface::class, $userCardService);
        $auth->setDbServiceManager($mockServices);
        $this->assertEquals($details, $auth->newCatalogLogin('user', 'pass'));
    }

    /**
     * Test new catalog login failure.
     *
     * @return void
     */
    public function testNewCatalogFailure(): void
    {
        $manager = $this->getMockManager(['getUserObject']);
        $manager->expects($this->any())->method('getUserObject')->willReturn(null);
        $details = false;
        $connection = $this->getMockConnection(['patronLogin']);
        $connection->expects($this->once())->method('patronLogin')
            ->with($this->equalTo('user'), $this->equalTo('pass'))->will($this->returnValue($details));
        $auth = $this->getAuthenticator($manager, $connection);
        $this->assertEquals($details, $auth->newCatalogLogin('user', 'pass'));
    }

    /**
     * Test new catalog login failure (caused by exception).
     *
     * @return void
     */
    public function testNewCatalogFailureByException(): void
    {
        $this->expectException(\VuFind\Exception\ILS::class);
        $this->expectExceptionMessage('kaboom');

        $manager = $this->getMockManager();
        $connection = $this->getMockConnection(['patronLogin']);
        $connection->expects($this->once())->method('patronLogin')
            ->with($this->equalTo('user'), $this->equalTo('pass'))
            ->will($this->throwException(new \VuFind\Exception\ILS('kaboom')));
        $auth = $this->getAuthenticator($manager, $connection);
        $auth->newCatalogLogin('user', 'pass');
    }

    /**
     * Test stored catalog login attempt with logged out user.
     *
     * @return void
     */
    public function testLoggedOutStoredLoginAttempt(): void
    {
        $manager = $this->getMockManager(['getUserObject']);
        $manager->expects($this->any())->method('getUserObject')->willReturn(null);
        $auth = $this->getAuthenticator($manager);
        $this->assertEquals(false, $auth->storedCatalogLogin());
    }

    /**
     * Test a successful stored login attempt.
     *
     * @return void
     */
    public function testSuccessfulStoredLoginAttempt(): void
    {
        $user = $this->getMockUser();
        $user->expects($this->any())->method('getCatUsername')->willReturn('user');
        $user->expects($this->any())->method('getRawCatPassword')->willReturn('pass');
        $manager = $this->getMockManager(['getUserObject']);
        $manager->expects($this->any())->method('getUserObject')->willReturn($user);
        $details = ['foo' => 'bar'];
        $connection = $this->getMockConnection(['patronLogin']);
        $connection->expects($this->once())->method('patronLogin')
            ->with($this->equalTo('user'), $this->equalTo('pass'))->willReturn($details);
        $auth = $this->getAuthenticator($manager, $connection);
        $this->assertEquals($details, $auth->storedCatalogLogin());

        // Log in a second time to be sure internal caching works (if it doesn't, the
        // once() assertion above will be violated and we'll get a failure).
        $this->assertEquals($details, $auth->storedCatalogLogin());
    }

    /**
     * Test an unsuccessful stored login attempt.
     *
     * @return void
     */
    public function testUnsuccessfulStoredLoginAttempt(): void
    {
        $user = $this->getMockUser();
        $user->expects($this->any())->method('getCatUsername')->willReturn('user');
        $user->expects($this->any())->method('getRawCatPassword')->willReturn('pass');
        $user->expects($this->once())->method('setCatUsername')->with(null)->willReturn($user);
        $user->expects($this->once())->method('setRawCatPassword')->with(null)->willReturn($user);
        $user->expects($this->once())->method('setCatPassEnc')->with(null)->willReturn($user);
        $manager = $this->getMockManager(['getUserObject']);
        $manager->expects($this->any())->method('getUserObject')->willReturn($user);
        $connection = $this->getMockConnection(['patronLogin']);
        $connection->expects($this->once())->method('patronLogin')
            ->with($this->equalTo('user'), $this->equalTo('pass'))->willReturn(false);
        $auth = $this->getAuthenticator($manager, $connection);
        $this->assertEquals(false, $auth->storedCatalogLogin());
    }

    /**
     * Test an exception during stored login attempt.
     *
     * @return void
     */
    public function testExceptionDuringStoredLoginAttempt(): void
    {
        $this->expectException(\VuFind\Exception\ILS::class);
        $this->expectExceptionMessage('kaboom');

        $user = $this->getMockUser();
        $user->expects($this->any())->method('getCatUsername')->willReturn('user');
        $user->expects($this->any())->method('getRawCatPassword')->willReturn('pass');
        $manager = $this->getMockManager(['getUserObject']);
        $manager->expects($this->any())->method('getUserObject')->willReturn($user);
        $connection = $this->getMockConnection(['patronLogin']);
        $connection->expects($this->once())
            ->method('patronLogin')
            ->with($this->equalTo('user'), $this->equalTo('pass'))
            ->will($this->throwException(new \VuFind\Exception\ILS('kaboom')));
        $auth = $this->getAuthenticator($manager, $connection);
        $auth->storedCatalogLogin();
    }

    /**
     * Test encryption and decryption of a string.
     *
     * @return void
     */
    public function testStringEncryptionAndDecryption(): void
    {
        $string = 'gobbledygook';
        $auth = $this->getAuthenticator(config: $this->getAuthConfig());
        $encrypted = $auth->encrypt($string);
        $this->assertNotEquals($string, $encrypted);
        $this->assertEquals($string, $auth->decrypt($encrypted));
    }

    /**
     * Test encryption and decryption of null.
     *
     * @return void
     */
    public function testNullEncryptionAndDecryption(): void
    {
        $auth = $this->getAuthenticator(config: $this->getAuthConfig());
        $this->assertNull($auth->encrypt(null));
        $this->assertNull($auth->decrypt(null));
    }

    /**
     * Get authentication-specific configuration.
     *
     * @return array
     */
    protected function getAuthConfig(): array
    {
        return [
            'Authentication' => [
                'ils_encryption_key' => 'foo',
                'ils_encryption_algo' => 'aes',
            ],
        ];
    }

    /**
     * Get an authenticator
     *
     * @param Manager            $manager    Auth manager (null for default mock)
     * @param ILSConnection      $connection ILS connection (null for default mock)
     * @param EmailAuthenticator $emailAuth  Email authenticator (null for default mock)
     * @param array              $config     Configuration (null for empty)
     *
     * @return ILSAuthenticator
     */
    protected function getAuthenticator(
        Manager $manager = null,
        ILSConnection $connection = null,
        EmailAuthenticator $emailAuth = null,
        array $config = []
    ): ILSAuthenticator {
        if (null === $manager) {
            $manager = $this->getMockManager();
        }
        if (null === $connection) {
            $connection = $this->getMockConnection();
        }
        return new ILSAuthenticator(
            function () use ($manager) {
                return $manager;
            },
            $connection,
            $emailAuth ?? $this->createMock(EmailAuthenticator::class),
            new \Laminas\Config\Config($config)
        );
    }

    /**
     * Get a mock user object
     *
     * @return MockObject&UserEntityInterface
     */
    protected function getMockUser(): MockObject&UserEntityInterface
    {
        return $this->createMock(UserEntityInterface::class);
    }

    /**
     * Get a mock auth manager
     *
     * @param array $methods Methods to mock
     *
     * @return MockObject&Manager
     */
    protected function getMockManager(array $methods = []): MockObject&Manager
    {
        return $this->getMockBuilder(\VuFind\Auth\Manager::class)
            ->disableOriginalConstructor()
            ->onlyMethods($methods)
            ->getMock();
    }

    /**
     * Get a mock ILS connection
     *
     * @param array $methods Methods to mock
     *
     * @return MockObject&ILSConnection
     */
    protected function getMockConnection(array $methods = []): MockObject&ILSConnection
    {
        // We need to use addMethods here instead of onlyMethods, because
        // we're generally mocking behavior that gets handled by __call
        // instead of by real methods on the Connection class.
        return $this->getMockBuilder(\VuFind\ILS\Connection::class)
            ->disableOriginalConstructor()
            ->addMethods($methods)
            ->getMock();
    }
}
