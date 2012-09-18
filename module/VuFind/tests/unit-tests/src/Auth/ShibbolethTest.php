<?php
/**
 * Shibboleth authentication test class.
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
namespace VuFind\Tests\Auth;
use VuFind\Auth\Shibboleth, VuFind\Db\Table\User, Zend\Config\Config;

/**
 * Shibboleth authentication test class.
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class ShibbolethTest extends \VuFind\Tests\DbTestCase
{
    /**
     * Standard setup method.
     *
     * @return void
     */
    public static function setUpBeforeClass()
    {
        // Fail if there are already users in the database (we don't want to run this
        // on a real system -- it's only meant for the continuous integration server)
        $test = new ShibbolethTest();
        $userTable = $test->getTable('User');
        if (count($userTable->select()) > 0) {
            throw new \Exception('Test cannot run with pre-existing user data!');
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
        $obj = clone($this->getAuthManager()->get('Shibboleth'));
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
            array(
                'login' => 'http://myserver',
                'username' => 'username',
                'email' => 'email',
                'userattribute_1' => 'password',
                'userattribute_value_1' => 'testpass'
            ), true
        );
        return new Config(array('Shibboleth' => $ldapConfig), true);
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
    protected function getLoginRequest($overrides = array())
    {
        $server = $overrides + array(
            'username' => 'testuser', 'email' => 'user@test.com',
            'password' => 'testpass'
        );
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
        $this->setExpectedException('VuFind\Exception\Auth');
        $request = $this->getLoginRequest(array('username' => ''));
        $this->getAuthObject()->authenticate($request);
    }

    /**
     * Test login with blank username.
     *
     * @return void
     */
    public function testLoginWithBlankPassword()
    {
        $this->setExpectedException('VuFind\Exception\Auth');
        $request = $this->getLoginRequest(array('password' => ''));
        $this->getAuthObject()->authenticate($request);
    }

    /**
     * Test a configuration with a missing attribute value.
     *
     * @return void
     */
    public function testWithMissingAttributeValue()
    {
        $this->setExpectedException('VuFind\Exception\Auth');
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
        $this->setExpectedException('VuFind\Exception\Auth');
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
        $this->setExpectedException('VuFind\Exception\Auth');
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
            'http://myserver?target=http%3A%2F%2Ftarget',
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
    public static function tearDownAfterClass()
    {
        // Delete test user
        $test = new ShibbolethTest();
        $userTable = $test->getTable('User');
        $user = $userTable->getByUsername('testuser', false);
        if (empty($user)) {
            throw new \Exception('Problem deleting expected user.');
        }
        $user->delete();
    }
}