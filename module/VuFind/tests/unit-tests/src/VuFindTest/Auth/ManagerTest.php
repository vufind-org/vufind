<?php

/**
 * Authentication manager test class.
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
use Laminas\Http\PhpEnvironment\Request;
use Laminas\Session\SessionManager;
use PHPUnit\Framework\MockObject\MockObject;
use VuFind\Auth\Manager;
use VuFind\Auth\PluginManager;
use VuFind\Auth\UserSessionPersistenceInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\UserServiceInterface;

use function get_class;

/**
 * Authentication manager test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class ManagerTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ReflectionTrait;

    /**
     * Test that database is the default method.
     *
     * @return void
     */
    public function testDefaultConfig(): void
    {
        $this->assertEquals('Database', $this->getManager()->getAuthMethod());
    }

    /**
     * Test getSessionInitiator
     *
     * @return void
     */
    public function testGetSessionInitiator(): void
    {
        $pm = $this->getMockPluginManager();
        $db = $pm->get('Database');
        $db->expects($this->once())->method('getSessionInitiator')
            ->with($this->equalTo('foo'))->will($this->returnValue('bar'));
        $manager = $this->getManager([], null, null, $pm);
        $this->assertEquals('bar', $manager->getSessionInitiator('foo'));
    }

    /**
     * Test getSelectableAuthOptions
     *
     * @return void
     */
    public function testGetSelectableAuthOptions(): void
    {
        // Simple case -- default Database helper.
        $this->assertEquals(['Database'], $this->getManager()->getSelectableAuthOptions());

        // Advanced case -- ChoiceAuth.
        $config = ['Authentication' => ['method' => 'ChoiceAuth']];
        $manager = $this->getManager($config);
        $this->assertEquals(['Database', 'Shibboleth'], $manager->getSelectableAuthOptions());

        // Advanced case -- ChoiceAuth's getSelectableAuthOptions returns false.
        $pm = $this->getMockPluginManager();
        $mockChoice = $this->getMockBuilder(\VuFind\Auth\ChoiceAuth::class)
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
    public function testGetLoginTargets(): void
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
    public function testGetDefaultLoginTarget(): void
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
    public function testLogoutWithDestruction(): void
    {
        $pm = $this->getMockPluginManager();
        $db = $pm->get('Database');
        $db->expects($this->once())->method('logout')
            ->with($this->equalTo('http://foo/bar'))->will($this->returnValue('http://baz'));
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
    public function testLogoutWithoutDestruction(): void
    {
        $pm = $this->getMockPluginManager();
        $db = $pm->get('Database');
        $db->expects($this->once())->method('logout')
            ->with($this->equalTo('http://foo/bar'))->will($this->returnValue('http://baz'));
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
    public function testLoginEnabled(): void
    {
        $this->assertTrue($this->getManager()->loginEnabled());
    }

    /**
     * Test that login can be disabled by configuration.
     *
     * @return void
     */
    public function testLoginDisabled(): void
    {
        $config = ['Authentication' => ['hideLogin' => true]];
        $this->assertFalse($this->getManager($config)->loginEnabled());
    }

    /**
     * Test security features of switching between auth options (part 1).
     *
     * @return void
     */
    public function testSwitchingSuccess(): void
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
     */
    public function testSwitchingFailure(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Illegal authentication method: MultiILS');

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
    public function testSupportsCreation(): void
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
    public function testSupportsRecovery(): void
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
     * Test supportsEmailChange
     *
     * @return void
     */
    public function testSupportsEmailChange(): void
    {
        // Most common case -- no:
        $this->assertFalse($this->getManager()->supportsEmailChange());

        // Less common case -- yes:
        $pm = $this->getMockPluginManager();
        $config = ['Authentication' => ['change_email' => true]];
        $this->assertTrue($this->getManager($config, null, null, $pm)->supportsEmailChange());
        $config = ['Authentication' => ['change_email' => false]];
        $this->assertFalse($this->getManager($config, null, null, $pm)->supportsEmailChange());
    }

    /**
     * Test supportsPasswordChange
     *
     * @return void
     */
    public function testSupportsPasswordChange(): void
    {
        // Most common case -- no:
        $this->assertFalse($this->getManager()->supportsPasswordChange());

        // Less common case -- yes:
        $pm = $this->getMockPluginManager();
        $db = $pm->get('Database');
        $db->expects($this->any())->method('supportsPasswordChange')->will($this->returnValue(true));
        $config = ['Authentication' => ['change_password' => true]];
        $this->assertTrue($this->getManager($config, null, null, $pm)->supportsPasswordChange());
        $config = ['Authentication' => ['change_password' => false]];
        $this->assertFalse($this->getManager($config, null, null, $pm)->supportsPasswordChange());
    }

    /**
     * Test getAuthClassForTemplateRendering
     *
     * @return void
     */
    public function testGetAuthClassForTemplateRendering(): void
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
    public function testUserHasLoggedOut(): void
    {
        // this won't be true in the context of a test class due to lack of cookies
        $this->assertFalse($this->getManager()->userHasLoggedOut());
    }

    /**
     * Test create
     *
     * @return void
     */
    public function testCreate(): void
    {
        $user = $this->getMockUser();
        $request = $this->getMockRequest();
        $pm = $this->getMockPluginManager();
        $db = $pm->get('Database');
        $db->expects($this->once())->method('create')->with($request)->willReturn($user);
        $manager = $this->getManager([], null, null, $pm);
        $this->assertNull($manager->getUserObject());
        $this->assertEquals($user, $manager->create($request));
        $this->assertEquals($user, $manager->getUserObject());
    }

    /**
     * Test successful login
     *
     * @return void
     */
    public function testSuccessfulLogin(): void
    {
        $user = $this->getMockUser();
        $request = $this->getMockRequest();
        $pm = $this->getMockPluginManager();
        $db = $pm->get('Database');
        $db->expects($this->once())->method('authenticate')->with($request)->will($this->returnValue($user));
        $manager = $this->getManager([], null, null, $pm);
        $request->getPost()->set('csrf', $manager->getCsrfHash());
        $this->assertNull($manager->getUserObject());
        $this->assertEquals($user, $manager->login($request));
        $this->assertEquals($user, $manager->getUserObject());
    }

    /**
     * Test CSRF failure (same setup as successful login, but minus token)
     *
     * @return void
     */
    public function testMissingCsrf(): void
    {
        $this->expectException(\VuFind\Exception\Auth::class);
        $this->expectExceptionMessage('authentication_error_technical');

        $request = $this->getMockRequest();
        $pm = $this->getMockPluginManager();
        $manager = $this->getManager([], null, null, $pm);
        $manager->login($request);
    }

    /**
     * Test CSRF failure (same setup as successful login, but with bad token)
     *
     * @return void
     */
    public function testIncorrectCsrf(): void
    {
        $this->expectException(\VuFind\Exception\Auth::class);
        $this->expectExceptionMessage('authentication_error_technical');

        $request = $this->getMockRequest();
        $pm = $this->getMockPluginManager();
        $manager = $this->getManager([], null, null, $pm);
        $request->getPost()->set('csrf', 'junk');
        $manager->login($request);
    }

    /**
     * Test unsuccessful login (\VuFind\Exception\PasswordSecurity)
     *
     * @return void
     */
    public function testPasswordSecurityException(): void
    {
        $this->expectException(\VuFind\Exception\PasswordSecurity::class);
        $this->expectExceptionMessage('Boom');

        $e = new \VuFind\Exception\PasswordSecurity('Boom');
        $request = $this->getMockRequest();
        $pm = $this->getMockPluginManager();
        $db = $pm->get('Database');
        $db->expects($this->once())->method('authenticate')->with($request)->will($this->throwException($e));
        $manager = $this->getManager([], null, null, $pm);
        $request->getPost()->set('csrf', $manager->getCsrfHash());
        $manager->login($request);
    }

    /**
     * Test unsuccessful login (\VuFind\Exception\Auth)
     *
     * @return void
     */
    public function testAuthException(): void
    {
        $this->expectException(\VuFind\Exception\Auth::class);
        $this->expectExceptionMessage('Blam');

        $e = new \VuFind\Exception\Auth('Blam');
        $request = $this->getMockRequest();
        $pm = $this->getMockPluginManager();
        $db = $pm->get('Database');
        $db->expects($this->once())->method('authenticate')->with($request)->will($this->throwException($e));
        $manager = $this->getManager([], null, null, $pm);
        $request->getPost()->set('csrf', $manager->getCsrfHash());
        $manager->login($request);
    }

    /**
     * Test that unexpected exceptions get mapped to technical errors.
     *
     * @return void
     */
    public function testUnanticipatedException(): void
    {
        $this->expectException(\VuFind\Exception\Auth::class);
        $this->expectExceptionMessage('authentication_error_technical');

        $e = new \Exception('It is normal to see this in the error log during testing...');
        $request = $this->getMockRequest();
        $pm = $this->getMockPluginManager();
        $db = $pm->get('Database');
        $db->expects($this->once())->method('authenticate')->with($request)->will($this->throwException($e));
        $manager = $this->getManager([], null, null, $pm);
        $request->getPost()->set('csrf', $manager->getCsrfHash());
        $manager->login($request);
    }

    /**
     * Test update password
     *
     * @return void
     */
    public function testUpdatePassword(): void
    {
        $user = $this->getMockUser();
        $request = $this->getMockRequest();
        $pm = $this->getMockPluginManager();
        $db = $pm->get('Database');
        $db->expects($this->once())->method('updatePassword')->with($request)->will($this->returnValue($user));
        $manager = $this->getManager([], null, null, $pm);
        $this->assertEquals($user, $manager->updatePassword($request));
        $this->assertEquals($user, $manager->getUserObject());
    }

    /**
     * Test checkForExpiredCredentials
     *
     * @return void
     */
    public function testCheckForExpiredCredentials(): void
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
    public function testUserLoginFromSession(): void
    {
        $user = $this->getMockUser();
        $service = $this->createMock(UserSessionPersistenceInterface::class);
        $service->expects($this->once())->method('hasUserSessionData')->willReturn(true);
        $service->expects($this->once())->method('getUserFromSession')->willReturn($user);
        $manager = $this->getManager([], $service);
        $this->assertEquals($user, $manager->getUserObject());
    }

    /**
     * Confirm default setting of allowsUserIlsLogin().
     *
     * @return void
     */
    public function testAllowsUserIlsLoginDefault(): void
    {
        $this->assertTrue($this->getManager()->allowsUserIlsLogin());
    }

    /**
     * Confirm configurability of allowsUserIlsLogin().
     *
     * @return void
     */
    public function testAllowsUserIlsLoginConfiguration(): void
    {
        $config = ['Catalog' => ['allowUserLogin' => false]];
        $this->assertFalse($this->getManager($config)->allowsUserIlsLogin());
    }

    /**
     * Get a manager object to test with.
     *
     * @param array                            $config         Configuration
     * @param ?UserSessionPersistenceInterface $userSession    User session persistence service
     * @param ?SessionManager                  $sessionManager Session manager
     * @param ?PluginManager                   $pm             Authentication plugin manager
     *
     * @return Manager
     */
    protected function getManager(
        array $config = [],
        ?UserSessionPersistenceInterface $userSession = null,
        ?SessionManager $sessionManager = null,
        ?PluginManager $pm = null
    ): Manager {
        $config = new Config($config);
        $cookies = new \VuFind\Cookie\CookieManager([]);
        $csrf = new \VuFind\Validator\SessionCsrf(
            [
                'session' => new \Laminas\Session\Container('csrf', $sessionManager),
                'salt' => 'csrftest',
            ]
        );
        $loginTokenManager = $this->createMock(\VuFind\Auth\LoginTokenManager::class);
        $ils = $this->createMock(\VuFind\ILS\Connection::class);
        $ils->expects($this->any())
            ->method('loginIsHidden')
            ->willReturn(false);
        return new Manager(
            $config,
            $this->createMock(UserServiceInterface::class),
            $userSession ?? $this->createMock(UserSessionPersistenceInterface::class),
            $sessionManager ?? new SessionManager(),
            $pm ?? $this->getMockPluginManager(),
            $cookies,
            $csrf,
            $loginTokenManager,
            $ils
        );
    }

    /**
     * Get a mock session manager.
     *
     * @return MockObject&SessionManager
     */
    protected function getMockSessionManager(): MockObject&SessionManager
    {
        return $this->createMock(\Laminas\Session\SessionManager::class);
    }

    /**
     * Get a mock plugin manager.
     *
     * @return PluginManager
     */
    protected function getMockPluginManager(): PluginManager
    {
        $pm = new PluginManager(new \VuFindTest\Container\MockContainer($this));
        $mockChoice = $this->createMock(\VuFind\Auth\ChoiceAuth::class);
        $mockChoice->expects($this->any())
            ->method('getSelectableAuthOptions')->willReturn(['Database', 'Shibboleth']);
        $mockDb = $this->createMock(\VuFind\Auth\Database::class);
        $mockDb->expects($this->any())->method('needsCsrfCheck')
            ->willReturn(true);
        $mockMulti = $this->createMock(\VuFind\Auth\MultiILS::class);
        $mockShib = $this->createMock(\VuFind\Auth\Shibboleth::class);
        $pm->setService(\VuFind\Auth\ChoiceAuth::class, $mockChoice);
        $pm->setService(\VuFind\Auth\Database::class, $mockDb);
        $pm->setService(\VuFind\Auth\MultiILS::class, $mockMulti);
        $pm->setService(\VuFind\Auth\Shibboleth::class, $mockShib);
        return $pm;
    }

    /**
     * Get a mock user object
     *
     * @return MockObject&UserEntityInterface
     */
    protected function getMockUser(): MockObject&UserEntityInterface
    {
        $user = $this->createMock(UserEntityInterface::class);
        $user->method('getId')->willReturn(-1);
        return $user;
    }

    /**
     * Get a mock request object
     *
     * @return MockObject&Request
     */
    protected function getMockRequest(): MockObject&Request
    {
        $mock = $this->createMock(Request::class);
        $post = new \Laminas\Stdlib\Parameters();
        $mock->expects($this->any())->method('getPost')->willReturn($post);
        $get = new \Laminas\Stdlib\Parameters();
        $mock->expects($this->any())->method('getQuery')->willReturn($get);
        return $mock;
    }
}
