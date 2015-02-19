<?php
/**
 * Dynamic Role Provider Test Class
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
namespace VuFindTest\Role;
use VuFind\Role\DynamicRoleProvider, VuFind\Role\PermissionProvider\PluginManager;

/**
 * Dynamic Role Provider Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class DynamicRoleProviderTest extends \VuFindTest\Unit\TestCase
{
    /**
     * Test that configurations get processed correctly
     *
     * @return void
     */
    public function testConfiguration()
    {
        $config = [
            'testRequireAny' => [
                'require' => 'any',
                'a' => 'foo',
                'b' => 'bar',
                'permission' => 'perm1',
            ],
            'testRequireAll' => [
                'require' => 'all',
                'a' => 'foo',
                'b' => 'bar',
                'permission' => 'perm2',
            ],
            'testAcceptArray' => [
                'c' => [1, 2, 3],
                'permission' => 'perm3',
            ],
        ];
        $pm = $this->getFakePluginManager();
        $pm->get('a')->expects($this->any())->method('getPermissions')->with($this->equalTo('foo'))->will($this->returnValue([]));
        $pm->get('b')->expects($this->any())->method('getPermissions')->with($this->equalTo('bar'))->will($this->returnValue(['role']));
        $pm->get('c')->expects($this->any())->method('getPermissions')->with($this->equalTo([1, 2, 3]))->will($this->returnValue(['role']));
        $result = $this->getDynamicRoleProvider($pm, $config)->getRoles(['role']);
        $this->assertEquals(1, count($result));
        $this->assertEquals('role', $result[0]->getName());
        $this->assertTrue($result[0]->hasPermission('perm1'));
        $this->assertFalse($result[0]->hasPermission('perm2'));
        $this->assertTrue($result[0]->hasPermission('perm3'));
    }

    /**
     * Get the DynamicRoleProvider to test.
     *
     * @param PluginManager $pluginManager Permission provider plugin manager
     * @param array         $config        Configuration
     *
     * @return DynamicRoleProvider
     */
    protected function getDynamicRoleProvider($pluginManager = null, $config = [])
    {
        if (null === $pluginManager) {
            $pluginManager = $this->getFakePluginManager();
        }
        return new DynamicRoleProvider($pluginManager, $config);
    }

    /**
     * Get a plugin manager populated with fake services to test.
     *
     * @return PluginManager
     */
    protected function getFakePluginManager()
    {
        $pm = new PluginManager();
        foreach (['a', 'b', 'c'] as $name) {
            $pm->setService($name, $this->getMock('VuFind\Role\PermissionProvider\PermissionProviderInterface'));
        }
        return $pm;
    }
}