<?php
/**
 * ChoiceAuth test class.
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
use VuFind\Auth\ChoiceAuth, VuFind\Auth\PluginManager,
    VuFind\Db\Row\User as UserRow, Zend\Config\Config,
    Zend\Http\PhpEnvironment\Request;

/**
 * ChoiceAuth test class.
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class ChoiceAuthTest extends \VuFindTest\Unit\TestCase
{
    /**
     * Test config validation
     *
     * @return void
     *
     * @expectedException        \VuFind\Exception\Auth
     * @expectedExceptionMessage One or more ChoiceAuth parameters are missing.
     */
    public function testBadConfiguration()
    {
        $ca = new ChoiceAuth();
        $ca->setConfig(new Config([]));
    }

    /**
     * Test default getPluginManager behavior
     *
     * @return void
     *
     * @expectedException        \Exception
     * @expectedExceptionMessage Plugin manager missing.
     */
    public function testMissingPluginManager()
    {
        $ca = new ChoiceAuth();
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
            return $this->fail('Expected exception not thrown.');
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
        $session = new \Zend\Session\Container('ChoiceAuth');
        $session->auth_method = 'Shibboleth';
        $pm = $this->getMockPluginManager();
        $shib = $pm->get('Shibboleth');
        $shib->expects($this->once())->method('logout')->with($this->equalTo('http://foo'))->will($this->returnValue('http://bar'));
        $ca = $this->getChoiceAuth($pm);
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
     *
     * @expectedException        \Exception
     * @expectedExceptionMessage Illegal setting: foo
     */
    public function testIllegalMethod()
    {
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
     * Get a ChoiceAuth object.
     *
     * @param PluginManager $pm         Plugin manager
     * @param string        $strategies Strategies setting
     *
     * @return ChoiceAuth
     */
    protected function getChoiceAuth($pm = null, $strategies = 'Database,Shibboleth')
    {
        $ca = new ChoiceAuth();
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
        $pm = new PluginManager();
        $mockDb = $this->getMockBuilder('VuFind\Auth\Database')
            ->disableOriginalConstructor()
            ->getMock();
        $mockShib = $this->getMockBuilder('VuFind\Auth\Shibboleth')
            ->disableOriginalConstructor()
            ->getMock();
        $pm->setService('Database', $mockDb);
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