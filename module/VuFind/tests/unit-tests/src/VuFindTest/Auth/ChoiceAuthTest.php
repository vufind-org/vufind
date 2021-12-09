<?php
/**
 * ChoiceAuth test class.
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

use Laminas\Config\Config;
use Laminas\Http\PhpEnvironment\Request;
use VuFind\Auth\ChoiceAuth;
use VuFind\Auth\PluginManager;
use VuFind\Db\Row\User as UserRow;

/**
 * ChoiceAuth test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class ChoiceAuthTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test config validation
     *
     * @return void
     */
    public function testBadConfiguration()
    {
        $this->expectException(\VuFind\Exception\Auth::class);
        $this->expectExceptionMessage('One or more ChoiceAuth parameters are missing.');

        $ca = new ChoiceAuth($this->getSessionContainer());
        $ca->setConfig(new Config([]));
    }

    /**
     * Test default getPluginManager behavior
     *
     * @return void
     */
    public function testMissingPluginManager()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Plugin manager missing.');

        $ca = new ChoiceAuth($this->getSessionContainer());
        $ca->getPluginManager();
    }

    /**
     * Test successful login
     *
     * @return void
     */
    public function testAuthenticate()
    {
        $request = new Request();
        $request->getPost()->set('auth_method', 'Database');
        $user = $this->getMockUser();
        $pm = $this->getMockPluginManager();
        $db = $pm->get('Database');
        $db->expects($this->once())->method('authenticate')->with($this->equalTo($request))->will($this->returnValue($user));
        $ca = $this->getChoiceAuth($pm);
        $this->assertEquals($user, $ca->authenticate($request));
        $this->assertEquals('Database', $ca->getSelectedAuthOption());
    }

    /**
     * Test authentication failure.
     *
     * @return void
     */
    public function testAuthenticationFailure()
    {
        $request = new Request();
        $request->getPost()->set('auth_method', 'Database');
        $exception = new \VuFind\Exception\Auth('boom');
        $pm = $this->getMockPluginManager();
        $db = $pm->get('Database');
        $db->expects($this->once())->method('authenticate')->with($this->equalTo($request))->will($this->throwException($exception));
        $ca = $this->getChoiceAuth($pm);
        try {
            $ca->authenticate($request);
            $this->fail('Expected exception not thrown.');
        } catch (\VuFind\Exception\Auth $e) {
            $this->assertEquals($exception, $e);
            $this->assertEquals(false, $ca->getSelectedAuthOption());
        }
    }

    /**
     * Test successful account creation
     *
     * @return void
     */
    public function testCreate()
    {
        $request = new Request();
        $request->getPost()->set('auth_method', 'Database');
        $user = $this->getMockUser();
        $pm = $this->getMockPluginManager();
        $db = $pm->get('Database');
        $db->expects($this->once())->method('create')->with($this->equalTo($request))->will($this->returnValue($user));
        $ca = $this->getChoiceAuth($pm);
        $this->assertEquals($user, $ca->create($request));
        $this->assertEquals('Database', $ca->getSelectedAuthOption());
    }

    /**
     * Test getSelectableAuthOptions
     *
     * @return void
     */
    public function testGetSelectableAuthOptions()
    {
        $this->assertEquals(['Database', 'Shibboleth'], $this->getChoiceAuth()->getSelectableAuthOptions());
    }

    /**
     * Test logout
     *
     * @return void
     */
    public function testLogout()
    {
        $session = $this->getSessionContainer('Shibboleth');
        $pm = $this->getMockPluginManager();
        $shib = $pm->get('Shibboleth');
        $shib->expects($this->once())->method('logout')->with($this->equalTo('http://foo'))->will($this->returnValue('http://bar'));
        $ca = $this->getChoiceAuth($pm, $session);
        $this->assertEquals('http://bar', $ca->logout('http://foo'));
    }

    /**
     * Test update password
     *
     * @return void
     */
    public function testUpdatePassword()
    {
        $request = new Request();
        $request->getQuery()->set('auth_method', 'Database');
        $user = $this->getMockUser();
        $pm = $this->getMockPluginManager();
        $db = $pm->get('Database');
        $db->expects($this->once())->method('updatePassword')->with($this->equalTo($request))->will($this->returnValue($user));
        $ca = $this->getChoiceAuth($pm);
        $this->assertEquals($user, $ca->updatePassword($request));
        $this->assertEquals('Database', $ca->getSelectedAuthOption());
    }

    /**
     * Test an illegal auth method
     *
     * @return void
     */
    public function testIllegalMethod()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Illegal setting: foo');

        $request = new Request();
        $request->getQuery()->set('auth_method', 'foo');
        $ca = $this->getChoiceAuth();
        $ca->updatePassword($request);
    }

    /**
     * Test that methods return false when no context is set.
     *
     * @return void
     */
    public function testContextFreeBehavior()
    {
        $ca = $this->getChoiceAuth();
        $this->assertFalse($ca->getSessionInitiator('http://foo'));
        $this->assertFalse($ca->supportsPasswordChange());
    }

    /**
     * Get a dummy session container.
     *
     * @param string $method Auth method to set in container (null for none).
     *
     * @return \Laminas\Session\Container
     */
    protected function getSessionContainer($method = null)
    {
        $mock = $this->getMockBuilder(\Laminas\Session\Container::class)
            ->onlyMethods(['__get', '__isset', '__set', '__unset'])
            ->disableOriginalConstructor()->getMock();
        if ($method) {
            $mock->expects($this->any())->method('__isset')->with($this->equalTo('auth_method'))->will($this->returnValue(true));
            $mock->expects($this->any())->method('__get')->with($this->equalTo('auth_method'))->will($this->returnValue($method));
        }
        return $mock;
    }

    /**
     * Get a ChoiceAuth object.
     *
     * @param PluginManager           $pm         Plugin manager
     * @param \Laminas\Session\Container $session    Session container
     * @param string                  $strategies Strategies setting
     *
     * @return ChoiceAuth
     */
    protected function getChoiceAuth($pm = null, $session = null, $strategies = 'Database,Shibboleth')
    {
        $ca = new ChoiceAuth($session ?: $this->getSessionContainer());
        $ca->setConfig(
            new Config(['ChoiceAuth' => ['choice_order' => $strategies]])
        );
        $ca->setPluginManager($pm ?: $this->getMockPluginManager());
        return $ca;
    }

    /**
     * Get a mock plugin manager.
     *
     * @return PluginManager
     */
    protected function getMockPluginManager()
    {
        $container = new \VuFindTest\Container\MockContainer($this);
        $pm = new PluginManager($container);
        $mockDb = $container->get(\VuFind\Auth\Database::class);
        $mockShib = $container->get(\VuFind\Auth\Shibboleth::class);
        $pm->setService(\VuFind\Auth\Database::class, $mockDb);
        $pm->setService(\VuFind\Auth\Shibboleth::class, $mockShib);
        return $pm;
    }

    /**
     * Get a mock user object
     *
     * @return UserRow
     */
    protected function getMockUser()
    {
        return $this->getMockBuilder(\VuFind\Db\Row\User::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * Get a mock request object
     *
     * @return \Laminas\Http\PhpEnvironment\Request
     */
    protected function getMockRequest()
    {
        return $this->getMockBuilder(\Laminas\Http\PhpEnvironment\Request::class)
            ->disableOriginalConstructor()
            ->getMock();
    }
}
