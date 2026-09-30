<?php

/**
 * PermissionManager Test Class.
 *
 * PHP version 8
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Tests
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Role;

use VuFind\Exception\ConfigException;
use VuFind\Role\PermissionDeniedManager;

/**
 * PermissionManager Test Class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class PermissionDeniedManagerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Sample configuration with various config options.
     *
     * @var array
     */
    protected $permissionDeniedConfig = [
        'permissionDeniedTemplate' => [
            'deniedTemplateBehavior' => 'showTemplate:record/displayLogicTest:param1=noValue',
            'deniedActionBehavior' => 'showTemplate:record/ActionTest:param1=noValue',
        ],
        'permissionDeniedTemplateNoParams' => [
            'deniedTemplateBehavior' => 'showTemplate:record/displayLogicTest',
            'deniedActionBehavior' => 'showTemplate:record/ActionTest',
        ],
        'permissionDeniedMessage' => [
            'deniedTemplateBehavior' => 'showMessage:dl_translatable_test',
            'deniedActionBehavior' => 'showTemplate:action_translatable_test',
        ],
        'permissionDeniedLogin' => [
            'deniedActionBehavior' => 'promptLogin',
        ],
        'permissionDeniedException' => [
            'deniedActionBehavior' => 'exception:ForbiddenException:exception_message',
        ],
        'permissionDeniedNonExistentException' => [
            'deniedActionBehavior' => 'exception:NonExistentException:exception_message',
        ],
        'permissionDeniedNothing' => [
        ],
    ];

    /**
     * Test a correctly configured template.
     *
     * @return void
     */
    public function testTemplateConfig(): void
    {
        $expected = [
            'action' => 'showTemplate',
            'value' => 'record/ActionTest',
            'params' => [
                'param1' => 'noValue',
            ],
        ];
        $expectedNoParams = [
            'action' => 'showTemplate',
            'value' => 'record/ActionTest',
            'params' => [],
        ];
        $pm = new PermissionDeniedManager($this->permissionDeniedConfig);

        $this->assertEquals($expected, $pm->getDeniedActionBehavior('permissionDeniedTemplate'));
        $this->assertEquals($expectedNoParams, $pm->getDeniedActionBehavior('permissionDeniedTemplateNoParams'));
    }

    /**
     * Test a correctly configured exception.
     *
     * @return void
     */
    public function testExceptionConfig(): void
    {
        $expected = [
            'action' => 'exception',
            'value' => 'ForbiddenException',
            'exceptionMessage' => 'exception_message',
            'params' => [],
        ];
        $pm = new PermissionDeniedManager($this->permissionDeniedConfig);

        $this->assertEquals($expected, $pm->getDeniedActionBehavior('permissionDeniedException'));
    }

    /**
     * Test an empty permission section.
     *
     * The getDeniedActionBehavior method should return false as the PermissionDeniedManager has nothing to do.
     *
     * @return void
     */
    public function testEmptyConfig(): void
    {
        $expected = [
            'action' => 'promptLogin',
            'value' => false,
            'params' => [],
        ];
        $pm = new PermissionDeniedManager($this->permissionDeniedConfig);

        $this->assertEquals($expected, $pm->getDeniedActionBehavior('permissionDeniedNothing'));
    }

    /**
     * Test a non existent permission section.
     *
     * The getDeniedActionBehavior method should return false as the PermissionDeniedManager has nothing to do.
     *
     * @return void
     */
    public function testNonExistentConfig(): void
    {
        $expected = [
            'action' => 'promptLogin',
            'value' => false,
            'params' => [],
        ];
        $pm = new PermissionDeniedManager($this->permissionDeniedConfig);

        $this->assertEquals($expected, $pm->getDeniedActionBehavior('garbage'));
    }

    /**
     * Test that legacy default controller behavior configuration throws an exception.
     *
     * @return void
     */
    public function testLegacyDefaultConfigException(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage(
            'permissionBehavior configuration must not contain the legacy defaultDeniedControllerBehavior setting'
        );
        new PermissionDeniedManager([
            'global' => [
                'defaultDeniedControllerBehavior' => 'promptLogin',
            ],
        ]);
    }

    /**
     * Test that legacy permission-specific controller behavior configuration throws an exception.
     *
     * @return void
     */
    public function testLegacyPermissionSpecificConfigException(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage(
            'permissionBehavior configuration must not contain the legacy deniedControllerBehavior setting'
            . ' (found in [Foo])'
        );
        new PermissionDeniedManager([
            'Foo' => [
                'deniedControllerBehavior' => 'promptLogin',
            ],
        ]);
    }
}
