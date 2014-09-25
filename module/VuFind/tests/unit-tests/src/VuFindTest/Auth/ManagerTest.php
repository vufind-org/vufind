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
use VuFind\Auth\Manager, VuFind\Auth\PluginManager, VuFind\Db\Table\User as UserTable,
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
        $manager = $this->getManager(array(), null, null, $pm);
        $this->assertEquals('bar', $manager->getSessionInitiator('foo'));
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
        $config = array('Authentication' => array('hideLogin' => true));
        $this->assertFalse($this->getManager($config)->loginEnabled());
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
    protected function getManager($config = array(), $userTable = null, $sessionManager = null, $pm = null)
    {
        $config = new Config($config);
        if (null === $userTable) {
            $userTable = $this->getMockBuilder('VuFind\Db\Table\User')
                ->disableOriginalConstructor()
                ->getMock();
        }
        if (null === $sessionManager) {
            $sessionManager = $this->getMockBuilder('Zend\Session\SessionManager')
                ->disableOriginalConstructor()
                ->getMock();
        }
        if (null === $pm) {
            $pm = $this->getMockPluginManager();
        }
        return new Manager($config, $userTable, $sessionManager, $pm);
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
        $pm->setService('Database', $mockDb);
        return $pm;
    }
}