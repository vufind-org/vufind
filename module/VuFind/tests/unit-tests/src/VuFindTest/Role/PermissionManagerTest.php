<?php
/**
 * PermissionManager Test Class
 *
 * PHP version 7
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
 * @author   Oliver Goldschmidt <@o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
namespace VuFindTest\Role;

use VuFind\Role\PermissionManager;

/**
 * PermissionManager Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Oliver Goldschmidt <@o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class PermissionManagerTest extends \VuFindTest\Unit\TestCase
{
    /**
     * Sample configuration with varios config options.
     *
     * @var array
     */
    protected $permissionConfig = [
        'permission.all' => [
            'permission' => "everyone"
        ],
        'permission.nobody' => [
            'permission' => "nobody"
        ],
        'permission.empty' => [
        ],
        'permission.array' => [
            'permission' => ['everyoneArray', 'everyoneArray2']
        ]
    ];

    /**
     * Test a non existent permission section
     *
     * @return void
     */
    public function testNonExistentPermission()
    {
        $pm = new PermissionManager($this->permissionConfig);

        $this->assertEquals(false, $pm->permissionRuleExists('garbage'));
    }

    /**
     * Test an existing permission section
     *
     * @return void
     */
    public function testExistentPermission()
    {
        $pm = new PermissionManager($this->permissionConfig);

        $this->assertEquals(true, $pm->permissionRuleExists('everyone'));
    }

    /**
     * Test an existing permission section in an array
     *
     * @return void
     */
    public function testExistentPermissionInArray()
    {
        $pm = new PermissionManager($this->permissionConfig);

        $this->assertEquals(true, $pm->permissionRuleExists('everyoneArray'));
    }

    /**
     * Test a granted permission
     *
     * @return void
     */
    public function testGrantedPermission()
    {
        $pm = new PermissionManager($this->permissionConfig);
        $mockAuth = $this->getMockBuilder('ZfcRbac\Service\AuthorizationService')
            ->disableOriginalConstructor()
            ->getMock();
        $mockAuth->expects($this->any())->method('isGranted')
            ->will($this->returnValue(true));
        $pm->setAuthorizationService($mockAuth);

        $this->assertEquals(true, $pm->isAuthorized('permission.everyone'));
    }

    /**
     * Test a denied permission
     *
     * @return void
     */
    public function testDeniedPermission()
    {
        $pm = new PermissionManager($this->permissionConfig);
        $mockAuth = $this->getMockBuilder('ZfcRbac\Service\AuthorizationService')
            ->disableOriginalConstructor()
            ->getMock();
        $mockAuth->expects($this->any())->method('isGranted')
            ->will($this->returnValue(false));
        $pm->setAuthorizationService($mockAuth);

        $this->assertEquals(false, $pm->isAuthorized('permission.nobody'));
    }
}
