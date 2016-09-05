<?php
/**
 * PermissionManager Test Class
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
 * @author   Oliver Goldschmidt <@o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
namespace VuFindTest\Role;
use VuFind\PermissionDeniedManager;

/**
 * PermissionManager Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Oliver Goldschmidt <@o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class PermissionDeniedManagerTest extends \VuFindTest\Unit\TestCase
{
    /**
     * Sample configuration with varios config options.
     *
     * @var array
     */
    protected $permissionDeniedConfig = [
        'permissionDeniedTemplate' => [
            'permissionDeniedDisplayLogic' => "showTemplate:record/displayLogicTest:param1=noValue",
            'permissionDeniedAction' => "showTemplate:record/ActionTest:param1=noValue"
        ],
        'permissionDeniedTemplateNoParams' => [
            'permissionDeniedDisplayLogic' => "showTemplate:record/displayLogicTest",
            'permissionDeniedAction' => "showTemplate:record/ActionTest"
        ],
        'permissionDeniedMessage' => [
            'permissionDeniedDisplayLogic' => "showMessage:dl_translatable_test",
            'permissionDeniedAction' => "showTemplate:action_translatable_test"
        ],
        'permissionDeniedLogin' => [
            'permissionDeniedAction' => "promptlogin"
        ],
        'permissionDeniedException' => [
            'permissionDeniedAction' => "exception:ForbiddenException:exception_message"
        ],
        'permissionDeniedNonExistentException' => [
            'permissionDeniedAction' => "exception:NonExistentException:exception_message"
        ],
        'permissionDeniedNothing' => [
        ],
    ];

    /**
     * Test a correctly configured template
     *
     * @return void
     */
    public function testTemplateConfig()
    {
        $expected = [
            'action' => 'showTemplate',
            'value' => 'record/ActionTest',
        ];
        $expectedParams = [
            'param1' => 'noValue'
        ];
        $expectedNoParams = [
            'action' => 'showTemplate',
            'value' => 'record/ActionTest'
        ];
        $pm = new PermissionDeniedManager($this->permissionDeniedConfig);

        $this->assertEquals($expected, $pm->getActionLogic('permissionDeniedTemplate'));
        $this->assertEquals($expectedNoParams, $pm->getActionLogic('permissionDeniedTemplateNoParams'));

        $this->assertEquals($expectedParams, $pm->getActionLogicParameters('permissionDeniedTemplate'));
        $this->assertEquals([], $pm->getActionLogicParameters('permissionDeniedTemplateNoParams'));
    }

    /**
     * Test a correctly configured exception
     *
     * @return void
     */
    public function testExceptionConfig()
    {
        $expected = [
            'action' => 'exception',
            'value' => 'ForbiddenException',
            'exceptionMessage' => 'exception_message'
        ];
        $pm = new PermissionDeniedManager($this->permissionDeniedConfig);

        $this->assertEquals($expected, $pm->getActionLogic('permissionDeniedException'));
        $this->assertEquals([], $pm->getActionLogicParameters('permissionDeniedException'));
    }

    /**
     * Test an empty permission section
     * getActionLogic should return false as the PermissionDeniedManager
     * has nothing to do
     *
     * @return void
     */
    public function testEmptyConfig()
    {
        $expected = [
            'action' => 'promptlogin'
        ];
        $pm = new PermissionDeniedManager($this->permissionDeniedConfig);

        $this->assertEquals($expected, $pm->getActionLogic('permissionDeniedNothing'));
    }

    /**
     * Test a non existent permission section
     * getActionLogic should return false as the PermissionDeniedManager
     * has nothing to do
     *
     * @return void
     */
    public function testNonExistentConfig()
    {
        $expected = [
            'action' => 'promptlogin'
        ];
        $pm = new PermissionDeniedManager($this->permissionDeniedConfig);

        $this->assertEquals($expected, $pm->getActionLogic('garbage'));
    }

}
