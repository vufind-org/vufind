<?php
/**
 * ILS Authenticator Test Class
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
namespace VuFindTest\Auth;
use VuFind\Auth\ILSAuthenticator, VuFind\Auth\Manager,
    VuFind\Db\Row\User, VuFind\ILS\Connection as ILSConnection;

/**
 * ILS Authenticator Manager Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class ILSAuthenticatorTest extends \VuFindTest\Unit\TestCase
{
    /**
     * Test new catalog login success.
     *
     * @return void
     */
    public function testNewCatalogLoginSuccess()
    {
        $user = $this->getMockUser(['saveCredentials']);
        $user->expects($this->once())->method('saveCredentials')->with($this->equalTo('user'), $this->equalTo('pass'));
        $manager = $this->getMockManager(['isLoggedIn', 'updateSession']);
        $manager->expects($this->any())->method('isLoggedIn')->will($this->returnValue($user));
        $manager->expects($this->once())->method('updateSession')->with($this->equalTo($user));
        $details = ['foo' => 'bar'];
        $connection = $this->getMockConnection(['patronLogin']);
        $connection->expects($this->once())->method('patronLogin')->with($this->equalTo('user'), $this->equalTo('pass'))->will($this->returnValue($details));
        $auth = $this->getAuthenticator($manager, $connection);
        $this->assertEquals($details, $auth->newCatalogLogin('user', 'pass'));
    }

    /**
     * Test new catalog login failure.
     *
     * @return void
     */
    public function testNewCatalogFailure()
    {
        $manager = $this->getMockManager(['isLoggedIn']);
        $manager->expects($this->any())->method('isLoggedIn')->will($this->returnValue(false));
        $details = false;
        $connection = $this->getMockConnection(['patronLogin']);
        $connection->expects($this->once())->method('patronLogin')->with($this->equalTo('user'), $this->equalTo('pass'))->will($this->returnValue($details));
        $auth = $this->getAuthenticator($manager, $connection);
        $this->assertEquals($details, $auth->newCatalogLogin('user', 'pass'));
    }

    /**
     * Test new catalog login failure (caused by exception).
     *
     * @return void
     */
    public function testNewCatalogFailureByException()
    {
        $manager = $this->getMockManager();
        $connection = $this->getMockConnection(['patronLogin']);
        $connection->expects($this->once())->method('patronLogin')->with($this->equalTo('user'), $this->equalTo('pass'))->will($this->throwException(new \VuFind\Exception\ILS('kaboom')));
        $auth = $this->getAuthenticator($manager, $connection);
        $this->assertEquals(false, $auth->newCatalogLogin('user', 'pass'));
    }

    /**
     * Test stored catalog login attempt with logged out user.
     *
     * @return void
     */
    public function testLoggedOutStoredLoginAttempt()
    {
        $manager = $this->getMockManager(['isLoggedIn']);
        $manager->expects($this->any())->method('isLoggedIn')->will($this->returnValue(false));
        $auth = $this->getAuthenticator($manager);
        $this->assertEquals(false, $auth->storedCatalogLogin());
    }

    /**
     * Test a successful stored login attempt.
     *
     * @return void
     */
    public function testSuccessfulStoredLoginAttempt()
    {
        $user = $this->getMockUser(['__get', '__isset', 'getCatPassword']);
        $user->expects($this->any())->method('__get')->with($this->equalTo('cat_username'))->will($this->returnValue('user'));
        $user->expects($this->any())->method('__isset')->with($this->equalTo('cat_username'))->will($this->returnValue(true));
        $user->expects($this->any())->method('getCatPassword')->will($this->returnValue('pass'));
        $manager = $this->getMockManager(['isLoggedIn']);
        $manager->expects($this->any())->method('isLoggedIn')->will($this->returnValue($user));
        $details = ['foo' => 'bar'];
        $connection = $this->getMockConnection(['patronLogin']);
        $connection->expects($this->once())->method('patronLogin')->with($this->equalTo('user'), $this->equalTo('pass'))->will($this->returnValue($details));
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
    public function testUnsuccessfulStoredLoginAttempt()
    {
        $user = $this->getMockUser(['__get', '__isset', 'clearCredentials', 'getCatPassword']);
        $user->expects($this->any())->method('__get')->with($this->equalTo('cat_username'))->will($this->returnValue('user'));
        $user->expects($this->any())->method('__isset')->with($this->equalTo('cat_username'))->will($this->returnValue(true));
        $user->expects($this->any())->method('getCatPassword')->will($this->returnValue('pass'));
        $user->expects($this->once())->method('clearCredentials');
        $manager = $this->getMockManager(['isLoggedIn']);
        $manager->expects($this->any())->method('isLoggedIn')->will($this->returnValue($user));
        $connection = $this->getMockConnection(['patronLogin']);
        $connection->expects($this->once())->method('patronLogin')->with($this->equalTo('user'), $this->equalTo('pass'))->will($this->returnValue(false));
        $auth = $this->getAuthenticator($manager, $connection);
        $this->assertEquals(false, $auth->storedCatalogLogin());
    }

    /**
     * Test an exception during stored login attempt.
     *
     * @return void
     */
    public function testExceptionDuringStoredLoginAttempt()
    {
        $user = $this->getMockUser(['__get', '__isset', 'clearCredentials', 'getCatPassword']);
        $user->expects($this->any())->method('__get')->with($this->equalTo('cat_username'))->will($this->returnValue('user'));
        $user->expects($this->any())->method('__isset')->with($this->equalTo('cat_username'))->will($this->returnValue(true));
        $user->expects($this->any())->method('getCatPassword')->will($this->returnValue('pass'));
        $user->expects($this->once())->method('clearCredentials');
        $manager = $this->getMockManager(['isLoggedIn']);
        $manager->expects($this->any())->method('isLoggedIn')->will($this->returnValue($user));
        $connection = $this->getMockConnection(['patronLogin']);
        $connection->expects($this->once())->method('patronLogin')->with($this->equalTo('user'), $this->equalTo('pass'))->will($this->throwException(new \VuFind\Exception\ILS('kaboom')));
        $auth = $this->getAuthenticator($manager, $connection);
        $this->assertEquals(false, $auth->storedCatalogLogin());
    }

    /**
     * Get an authenticator
     *
     * @param Manager       $manager    Auth manager (null for default mock)
     * @param ILSConnection $connection ILS connection (null for default mock)
     *
     * @return ILSAuthenticator
     */
    protected function getAuthenticator(Manager $manager = null, ILSConnection $connection = null)
    {
        if (null === $manager) {
            $manager = $this->getMockManager();
        }
        if (null === $connection) {
            $connection = $this->getMockConnection();
        }
        return new ILSAuthenticator($manager, $connection);
    }

    /**
     * Get a mock user object
     *
     * @param array $methods Methods to mock
     *
     * @return User
     */
    protected function getMockUser($methods = null)
    {
        return $this->getMockBuilder('VuFind\Db\Row\User')
            ->disableOriginalConstructor()
            ->setMethods($methods)
            ->getMock();
    }

    /**
     * Get a mock auth manager
     *
     * @param array $methods Methods to mock
     *
     * @return Manager
     */
    protected function getMockManager($methods = null)
    {
        return $this->getMockBuilder('VuFind\Auth\Manager')
            ->disableOriginalConstructor()
            ->setMethods($methods)
            ->getMock();
    }

    /**
     * Get a mock ILS connection
     *
     * @param array $methods Methods to mock
     *
     * @return ILSConnection
     */
    protected function getMockConnection($methods = null)
    {
        return $this->getMockBuilder('VuFind\ILS\Connection')
            ->disableOriginalConstructor()
            ->setMethods($methods)
            ->getMock();
    }
}