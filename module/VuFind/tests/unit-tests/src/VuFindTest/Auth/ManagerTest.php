<?php
/**
 * Authentication manager test class.
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
use VuFind\Auth\Manager, VuFind\Auth\PluginManager,
    VuFind\Db\Row\User as UserRow, VuFind\Db\Table\User as UserTable,
    Zend\Config\Config, Zend\Session\SessionManager;

/**
 * Authentication manager test class.
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class ManagerTest extends \VuFindTest\Unit\TestCase
{
    /**
     * Test that database is the default method.
     *
     * @return void
     */
    public function testDefaultConfig()
    {
        $this->assertEquals('Database', $this->getManager()->getAuthMethod());
    }

    /**
     * Test getSessionInitiator
     *
     * @return void
     */
    public function testGetSessionInitiator()
    {
        $pm = $this->getMockPluginManager();
        $db = $pm->get('Database');
        $db->expects($this->once())->method('getSessionInitiator')->with($this->equalTo('foo'))->will($this->returnValue('bar'));
        $manager = $this->getManager([], null, null, $pm);
        $this->assertEquals('bar', $manager->getSessionInitiator('foo'));
    }

    /**
     * Test getSelectableAuthOptions
     *
     * @return void
     */
    public function testGetSelectableAuthOptions()
    {
        // Simple case -- default Database helper.
        $this->assertEquals(['Database'], $this->getManager()->getSelectableAuthOptions());

        // Advanced case -- ChoiceAuth.
        $config = ['Authentication' => ['method' => 'ChoiceAuth']];
        $manager = $this->getManager($config);
        $this->assertEquals(['Database', 'Shibboleth'], $manager->getSelectableAuthOptions());

        // Advanced case -- ChoiceAuth's getSelectableAuthOptions returns false.
        $pm = $this->getMockPluginManager();
        $mockChoice = $this->getMockBuilder('VuFind\Auth\ChoiceAuth')
            ->disableOriginalConstructor()
            ->getMock();
        $mockChoice->expects($this->any())->method('getSelectableAuthOptions')->will($this->returnValue(false));
        $pm->setService('ChoiceAuth2', $mockChoice);
        $config = ['Authentication' => ['method' => 'ChoiceAuth2']];
        $manager = $this->getManager($config, null, null, $pm);
        $this->assertEquals(['ChoiceAuth2'], $manager->getSelectableAuthOptions());
    }

    /**
     * Test getLoginTargets
     *
     * @return void
     */
    public function testGetLoginTargets()
    {
        $pm = $this->getMockPluginManager();
        $targets = ['a', 'b', 'c'];
        $multi = $pm->get('MultiILS');
        $multi->expects($this->once())->method('getLoginTargets')->will($this->returnValue($targets));
        $config = ['Authentication' => ['method' => 'MultiILS']];
        $this->assertEquals($targets, $this->getManager($config, null, null, $pm)->getLoginTargets());
    }

    /**
     * Test getDefaultLoginTarget
     *
     * @return void
     */
    public function testGetDefaultLoginTarget()
    {
        $pm = $this->getMockPluginManager();
        $target = 'foo';
        $multi = $pm->get('MultiILS');
        $multi->expects($this->once())->method('getDefaultLoginTarget')->will($this->returnValue($target));
        $config = ['Authentication' => ['method' => 'MultiILS']];
        $this->assertEquals($target, $this->getManager($config, null, null, $pm)->getDefaultLoginTarget());
    }

    /**
     * Test logout (with destruction)
     *
     * @return void
     */
    public function testLogoutWithDestruction()
    {
        $pm = $this->getMockPluginManager();
        $db = $pm->get('Database');
        $db->expects($this->once())->method('logout')->with($this->equalTo('http://foo/bar'))->will($this->returnValue('http://baz'));
        $sm = $this->getMockSessionManager();
        $sm->expects($this->once())->method('destroy');
        $manager = $this->getManager([], null, $sm, $pm);
        $this->assertEquals('http://baz', $manager->logout('http://foo/bar'));
    }

    /**
     * Test logout (without destruction)
     *
     * @return void
     */
    public function testLogoutWithoutDestruction()
    {
        $pm = $this->getMockPluginManager();
        $db = $pm->get('Database');
        $db->expects($this->once())->method('logout')->with($this->equalTo('http://foo/bar'))->will($this->returnValue('http://baz'));
        $sm = $this->getMockSessionManager();
        $sm->expects($this->exactly(0))->method('destroy');
        $manager = $this->getManager([], null, $sm, $pm);
        $this->assertEquals('http://baz', $manager->logout('http://foo/bar', false));
    }

    /**
     * Test that login is enabled by default.
     *
     * @return void
     */
    public function testLoginEnabled()
    {
        $this->assertTrue($this->getManager()->loginEnabled());
    }

    /**
     * Test that login can be disabled by configuration.
     *
     * @return void
     */
    public function testLoginDisabled()
    {
        $config = ['Authentication' => ['hideLogin' => true]];
        $this->assertFalse($this->getManager($config)->loginEnabled());
    }

    /**
     * Test security features of switching between auth options (part 1).
     *
     * @return void
     */
    public function testSwitchingSuccess()
    {
        $config = ['Authentication' => ['method' => 'ChoiceAuth']];
        $manager = $this->getManager($config);
        $this->assertEquals('ChoiceAuth', $manager->getAuthMethod());
        // The default mock object in this test is configured to allow a
        // switch from ChoiceAuth --> Database
        $manager->setAuthMethod('Database');
        $this->assertEquals('Database', $manager->getAuthMethod());
    }

    /**
     * Test security features of switching between auth options (part 2).
     *
     * @return void
     *
     * @expectedException        \Exception
     * @expectedExceptionMessage Illegal authentication method: MultiILS
     */
    public function testSwitchingFailure()
    {
        $config = ['Authentication' => ['method' => 'ChoiceAuth']];
        $manager = $this->getManager($config);
        $this->assertEquals('ChoiceAuth', $manager->getAuthMethod());
        // The default mock object in this test is NOT configured to allow a
        // switch from ChoiceAuth --> MultiILS
        $manager->setAuthMethod('MultiILS');
    }

    /**
     * Test supportsCreation
     *
     * @return void
     */
    public function testSupportsCreation()
    {
        $config = ['Authentication' => ['method' => 'ChoiceAuth']];
        $pm = $this->getMockPluginManager();
        $db = $pm->get('Database');
        $db->expects($this->once())->method('supportsCreation')->will($this->returnValue(true));
        $shib = $pm->get('Shibboleth');
        $shib->expects($this->once())->method('supportsCreation')->will($this->returnValue(false));
        $manager = $this->getManager($config, null, null, $pm);
        $this->assertTrue($manager->supportsCreation('Database'));
        $this->assertFalse($manager->supportsCreation('Shibboleth'));
    }

    /**
     * Test supportsRecovery
     *
     * @return void
     */
    public function testSupportsRecovery()
    {
        // Most common case -- no:
        $this->assertFalse($this->getManager()->supportsRecovery());

        // Less common case -- yes:
        $pm = $this->getMockPluginManager();
        $db = $pm->get('Database');
        $db->expects($this->once())->method('supportsPasswordRecovery')->will($this->returnValue(true));
        $config = ['Authentication' => ['recover_password' => true]];
        $this->assertTrue($this->getManager($config, null, null, $pm)->supportsRecovery());
    }

    /**
     * Test supportsPasswordChange
     *
     * @return void
     */
    public function testSupportsPasswordChange()
    {
        // Most common case -- no:
        $this->assertFalse($this->getManager()->supportsPasswordChange());

        // Less common case -- yes:
        $pm = $this->getMockPluginManager();
        $db = $pm->get('Database');
        $db->expects($this->once())->method('supportsPasswordChange')->will($this->returnValue(true));
        $config = ['Authentication' => ['change_password' => true]];
        $this->assertTrue($this->getManager($config, null, null, $pm)->supportsPasswordChange());
    }

    /**
     * Test getAuthClassForTemplateRendering
     *
     * @return void
     */
    public function testGetAuthClassForTemplateRendering()
    {
        // Simple default case:
        $pm = $this->getMockPluginManager();
        $this->assertEquals(get_class($pm->get('Database')), $this->getManager()->getAuthClassForTemplateRendering());

        // Complex case involving proxied authenticator in ChoiceAuth:
        $config = ['Authentication' => ['method' => 'ChoiceAuth']];
        $choice = $pm->get('ChoiceAuth');
        $choice->expects($this->once())->method('getSelectedAuthOption')->will($this->returnValue('Shibboleth'));
        $manager = $this->getManager($config, null, null, $pm);
        $this->assertEquals(get_class($pm->get('Shibboleth')), $manager->getAuthClassForTemplateRendering());
    }

    /**
     * Test userHasLoggedOut
     *
     * @return void
     */
    public function testUserHasLoggedOut()
    {
        // this won't be true in the context of a test class due to lack of cookies
        $this->assertFalse($this->getManager()->userHasLoggedOut());
    }

    /**
     * Test create
     *
     * @return void
     */
    public function testCreate()
    {
        $user = $this->getMockUser();
        $request = $this->getMockRequest();
        $pm = $this->getMockPluginManager();
        $db = $pm->get('Database');
        $db->expects($this->once())->method('create')->with($request)->will($this->returnValue($user));
        $manager = $this->getManager([], null, null, $pm);
        $this->assertFalse($manager->isLoggedIn());
        $this->assertEquals($user, $manager->create($request));
        $this->assertEquals($user, $manager->isLoggedIn());
    }

    /**
     * Test successful login
     *
     * @return void
     */
    public function testSuccessfulLogin()
    {
        $user = $this->getMockUser();
        $request = $this->getMockRequest();
        $pm = $this->getMockPluginManager();
        $db = $pm->get('Database');
        $db->expects($this->once())->method('authenticate')->with($request)->will($this->returnValue($user));
        $manager = $this->getManager([], null, null, $pm);
        $this->assertFalse($manager->isLoggedIn());
        $this->assertEquals($user, $manager->login($request));
        $this->assertEquals($user, $manager->isLoggedIn());
    }

    /**
     * Test unsuccessful login (\VuFind\Exception\PasswordSecurity)
     *
     * @return void
     *
     * @expectedException        \VuFind\Exception\PasswordSecurity
     * @expectedExceptionMessage Boom
     */
    public function testPasswordSecurityException()
    {
        $e = new \VuFind\Exception\PasswordSecurity('Boom');
        $request = $this->getMockRequest();
        $pm = $this->getMockPluginManager();
        $db = $pm->get('Database');
        $db->expects($this->once())->method('authenticate')->with($request)->will($this->throwException($e));
        $manager = $this->getManager([], null, null, $pm);
        $manager->login($request);
    }

    /**
     * Test unsuccessful login (\VuFind\Exception\Auth)
     *
     * @return void
     *
     * @expectedException        \VuFind\Exception\Auth
     * @expectedExceptionMessage Blam
     */
    public function testAuthException()
    {
        $e = new \VuFind\Exception\Auth('Blam');
        $request = $this->getMockRequest();
        $pm = $this->getMockPluginManager();
        $db = $pm->get('Database');
        $db->expects($this->once())->method('authenticate')->with($request)->will($this->throwException($e));
        $manager = $this->getManager([], null, null, $pm);
        $manager->login($request);
    }

    /**
     * Test that unexpected exceptions get mapped to technical errors.
     *
     * @return void
     *
     * @expectedException        \VuFind\Exception\Auth
     * @expectedExceptionMessage authentication_error_technical
     */
    public function testUnanticipatedException()
    {
        $e = new \Exception('It is normal to see this in the error log during testing...');
        $request = $this->getMockRequest();
        $pm = $this->getMockPluginManager();
        $db = $pm->get('Database');
        $db->expects($this->once())->method('authenticate')->with($request)->will($this->throwException($e));
        $manager = $this->getManager([], null, null, $pm);
        $manager->login($request);
    }

    /**
     * Test update password
     *
     * @return void
     */
    public function testUpdatePassword()
    {
        $user = $this->getMockUser();
        $request = $this->getMockRequest();
        $pm = $this->getMockPluginManager();
        $db = $pm->get('Database');
        $db->expects($this->once())->method('updatePassword')->with($request)->will($this->returnValue($user));
        $manager = $this->getManager([], null, null, $pm);
        $this->assertEquals($user, $manager->updatePassword($request));
        $this->assertEquals($user, $manager->isLoggedIn());
    }

    /**
     * Test checkForExpiredCredentials
     *
     * @return void
     */
    public function testCheckForExpiredCredentials()
    {
        // Simple case -- none found:
        $this->assertFalse($this->getManager()->checkForExpiredCredentials());

        // Complex case -- found (we'll simulate creating a user to set up the environment):
        $user = $this->getMockUser();
        $request = $this->getMockRequest();
        $pm = $this->getMockPluginManager();
        $db = $pm->get('Database');
        $db->expects($this->once())->method('create')->with($request)->will($this->returnValue($user));
        $db->expects($this->once())->method('isExpired')->will($this->returnValue(true));
        $manager = $this->getManager([], null, null, $pm);
        $manager->create($request);
        $this->assertTrue($manager->checkForExpiredCredentials());
    }

    /**
     * Test the persistence of a user account in the session.
     *
     * @return void
     */
    public function testUserLoginFromSession()
    {
        $table = $this->getMockUserTable();
        $user = $this->getMockUser();
        $userArray = new \ArrayObject();
        $userArray->append($user);
        $table->expects($this->once())->method('select')->with($this->equalTo(['id' => 'foo']))->will($this->returnValue($userArray->getIterator()));
        $manager = $this->getManager([], $table);
        $session = $this->getProperty($manager, 'session');
        $session->userId = 'foo';
        $this->assertEquals($user, $manager->isLoggedIn());
    }

    /**
     * Get a manager object to test with.
     *
     * @param array          $config         Configuration
     * @param UserTable      $userTable      User table gateway
     * @param SessionManager $sessionManager Session manager
     * @param PluginManager  $pm             Authentication plugin manager
     *
     * @return Manager
     */
    protected function getManager($config = [], $userTable = null, $sessionManager = null, $pm = null)
    {
        $config = new Config($config);
        if (null === $userTable) {
            $userTable = $this->getMockUserTable();
        }
        if (null === $sessionManager) {
            $sessionManager = $this->getMockSessionManager();
        }
        if (null === $pm) {
            $pm = $this->getMockPluginManager();
        }
        $cookies = new \VuFind\Cookie\CookieManager([]);
        return new Manager($config, $userTable, $sessionManager, $pm, $cookies);
    }

    /**
     * Get a mock user table.
     *
     * @return UserTable
     */
    protected function getMockUserTable()
    {
        return $this->getMockBuilder('VuFind\Db\Table\User')
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * Get a mock session manager.
     *
     * @return SessionManager
     */
    protected function getMockSessionManager()
    {
        return $this->getMockBuilder('Zend\Session\SessionManager')
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * Get a mock plugin manager.
     *
     * @return PluginManager
     */
    protected function getMockPluginManager()
    {
        $pm = new PluginManager();
        $mockChoice = $this->getMockBuilder('VuFind\Auth\ChoiceAuth')
            ->disableOriginalConstructor()
            ->getMock();
        $mockChoice->expects($this->any())->method('getSelectableAuthOptions')->will($this->returnValue(['Database', 'Shibboleth']));
        $mockDb = $this->getMockBuilder('VuFind\Auth\Database')
            ->disableOriginalConstructor()
            ->getMock();
        $mockMulti = $this->getMockBuilder('VuFind\Auth\MultiILS')
            ->disableOriginalConstructor()
            ->getMock();
        $mockShib = $this->getMockBuilder('VuFind\Auth\Shibboleth')
            ->disableOriginalConstructor()
            ->getMock();
        $pm->setService('ChoiceAuth', $mockChoice);
        $pm->setService('Database', $mockDb);
        $pm->setService('MultiILS', $mockMulti);
        $pm->setService('Shibboleth', $mockShib);
        return $pm;
    }

    /**
     * Get a mock user object
     *
     * @return UserRow
     */
    protected function getMockUser()
    {
        return $this->getMockBuilder('VuFind\Db\Row\User')
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * Get a mock request object
     *
     * @return \Zend\Http\PhpEnvironment\Request
     */
    protected function getMockRequest()
    {
        return $this->getMockBuilder('Zend\Http\PhpEnvironment\Request')
            ->disableOriginalConstructor()
            ->getMock();
    }
}