<?php
/**
 * ILS authentication test class.
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFindTest\Auth;
use VuFind\Auth\ILS, VuFind\Db\Table\User;

/**
 * ILS authentication test class.
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class ILSTest extends \VuFindTest\Unit\DbTestCase
{
    /**
     * Object to test
     *
     * @var ILS
     */
    protected $auth;

    /**
     * Mock ILS driver
     *
     * @var \VuFind\ILS\Driver\Sample
     */
    protected $driver;

    /**
     * Standard setup method.
     *
     * @return void
     */
    public static function setUpBeforeClass()
    {
        // If CI is not running, all tests were skipped, so no work is necessary:
        $test = new ILSTest();
        if (!$test->continuousIntegrationRunning()) {
            return;
        }
        // Fail if there are already users in the database (we don't want to run this
        // on a real system -- it's only meant for the continuous integration server)
        $userTable = $test->getTable('User');
        if (count($userTable->select()) > 0) {
            return self::markTestSkipped(
                'Test cannot run with pre-existing user data!'
            );
        }
    }

    /**
     * Standard setup method.
     *
     * @return void
     */
    public function setUp()
    {
        // Give up if we're not running in CI:
        if (!$this->continuousIntegrationRunning()) {
            return $this->markTestSkipped('Continuous integration not running.');
        }
        $this->driver = $this->getMock('VuFind\ILS\Driver\Sample');
        $driverManager = new \VuFind\ILS\Driver\PluginManager();
        $driverManager->setService('Sample', $this->driver);
        $mockConfigReader = $this->getMock('VuFind\Config\PluginManager');
        $mockConfigReader->expects($this->any())->method('get')
            ->will($this->returnValue(new \Zend\Config\Config([])));
        $this->auth = new \VuFind\Auth\ILS(
            new \VuFind\ILS\Connection(
                new \Zend\Config\Config(['driver' => 'Sample']),
                $driverManager, $mockConfigReader
            ),
            $this->getMockILSAuthenticator()
        );
        $this->auth->setDbTableManager(
            $this->getServiceManager()->get('VuFind\DbTablePluginManager')
        );
        $this->auth->getCatalog()->setDriver($this->driver);
    }

    /**
     * Test account creation is disallowed.
     *
     * @return void
     */
    public function testCreateIsDisallowed()
    {
        $this->assertFalse($this->auth->supportsCreation());
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
        $post = $overrides + [
            'username' => 'testuser', 'password' => 'testpass'
        ];
        $request = new \Zend\Http\Request();
        $request->setPost(new \Zend\Stdlib\Parameters($post));
        return $request;
    }

    /**
     * Test login with blank username.
     *
     * @return void
     */
    public function testLoginWithBlankUsername()
    {
        $this->setExpectedException('VuFind\Exception\Auth');
        $request = $this->getLoginRequest(['username' => '']);
        $this->auth->authenticate($request);
    }

    /**
     * Test login with blank password.
     *
     * @return void
     */
    public function testLoginWithBlankPassword()
    {
        $this->setExpectedException('VuFind\Exception\Auth');
        $request = $this->getLoginRequest(['password' => '']);
        $this->auth->authenticate($request);
    }

    /**
     * Test login with technical error.
     *
     * @return void
     */
    public function testBadLoginResponse()
    {
        // VuFind requires the ILS driver to return a value in cat_username
        // by default -- if that is missing, we should fail.
        $response = [];
        $this->driver->expects($this->once())->method('patronLogin')
            ->with($this->equalTo('testuser'), $this->equalTo('testpass'))
            ->will($this->returnValue($response));
        $this->setExpectedException('VuFind\Exception\Auth');
        $this->auth->authenticate($this->getLoginRequest());
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
            'email' => 'user@test.com'
        ];
        $this->driver->expects($this->once())->method('patronLogin')
            ->with($this->equalTo('testuser'), $this->equalTo('testpass'))
            ->will($this->returnValue($response));
        $user = $this->auth->authenticate($this->getLoginRequest());
        $this->assertEquals('testuser', $user->username);
        $this->assertEquals('user@test.com', $user->email);
    }

    /**
     * Standard teardown method.
     *
     * @return void
     */
    public static function tearDownAfterClass()
    {
        // If CI is not running, all tests were skipped, so no work is necessary:
        $test = new ILSTest();
        if (!$test->continuousIntegrationRunning()) {
            return;
        }

        // Delete test user
        $test = new ILSTest();
        $userTable = $test->getTable('User');
        $user = $userTable->getByUsername('testuser', false);
        if (empty($user)) {
            throw new \Exception('Problem deleting expected user.');
        }
        $user->delete();
    }

    /**
     * Get mock ILS authenticator
     *
     * @return \VuFind\Auth\ILSAuthenticator
     */
    protected function getMockILSAuthenticator()
    {
        return $this->getMockBuilder('VuFind\Auth\ILSAuthenticator')
            ->disableOriginalConstructor()
            ->getMock();
    }
}