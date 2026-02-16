<?php

/**
 * Permission view helper Test Class
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\View\Helper\Root;

use VuFind\Role\PermissionDeniedManager;
use VuFind\Role\PermissionManager;
use VuFind\View\Helper\Root\Permission;

/**
 * Permission view helper Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class PermissionTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ViewTrait;

    /**
     * Sample configuration with various config options.
     *
     * @var array
     */
    protected $permissionDeniedConfig = [
        'permissionDeniedTemplate' => [
            'deniedTemplateBehavior' => 'showTemplate:record/displayLogicTest:param1=noValue',
            'deniedControllerBehavior' => 'showTemplate:record/ActionTest:param1=noValue',
        ],
        'permissionDeniedTemplateNoParams' => [
            'deniedTemplateBehavior' => 'showTemplate:record/displayLogicTest',
            'deniedControllerBehavior' => 'showTemplate:record/ActionTest',
        ],
        'permissionDeniedMessage' => [
            'deniedTemplateBehavior' => 'showMessage:dl_translatable_test',
            'deniedControllerBehavior' => 'showTemplate:action_translatable_test',
        ],
        'permissionDeniedLogin' => [
            'deniedControllerBehavior' => 'promptLogin',
        ],
        'permissionDeniedException' => [
            'deniedControllerBehavior' => 'exception:ForbiddenException:exception_message',
        ],
        'permissionDeniedNonExistentException' => [
            'deniedControllerBehavior' => 'exception:NonExistentException:exception_message',
        ],
        'permissionDeniedNothing' => [
        ],
    ];

    /**
     * Test the message display
     *
     * @return void
     */
    public function testMessageDisplay()
    {
        $mockPdmMessage = $this->getMockPdm(
            [
                'deniedTemplateBehavior' => [
                    'action' => 'showMessage',
                    'value' => 'dl_translatable_test',
                    'params' => [],
                ],
            ]
        );

        $helper = new Permission($this->getMockPm(false), $mockPdmMessage);
        $helper->setView($this->getMockView());

        $displayBlock = $helper->getAlternateContent('permissionDeniedMessage');
        $this->assertEquals('dl_translatable_test', $displayBlock);
    }

    /**
     * Test the template display
     *
     * @return void
     */
    public function testTemplateDisplay()
    {
        $this->expectException(\Laminas\View\Exception\RuntimeException::class);

        // Template does not exist, expect an exception, though
        $mockPdm = $this->getMockPdm(
            [
                'deniedTemplateBehavior' => [
                    'action' => 'showTemplate',
                    'value' => 'record/displayLogicTest',
                    'params' => [],
                ],
            ]
        );

        $helper = new Permission($this->getMockPm(false), $mockPdm);
        $helper->setView($this->getMockView());

        $helper->getAlternateContent('permissionDeniedTemplate');
    }

    /**
     * Test the template display with an existing template
     *
     * @return void
     */
    public function testExistingTemplateDisplay()
    {
        $mockPdm = $this->getMockPdm(
            [
                'deniedTemplateBehavior' => [
                    'action' => 'showTemplate',
                    'value' => 'ajax/status-available.phtml',
                    'params' => [],
                ],
            ]
        );

        $helper = new Permission($this->getMockPm(false), $mockPdm);
        $helper->setView($this->getMockView());

        $this->assertSame(
            '<span class="label label-success">Available</span>',
            trim($helper->getAlternateContent('permissionDeniedTemplate'))
        );
    }

    /**
     * Get mock driver that returns a deniedTemplateBehavior.
     *
     * @param array $config Config containing DeniedTemplateBehavior to return
     *
     * @return PermissionDeniedManager
     */
    protected function getMockPdm($config = false): PermissionDeniedManager
    {
        $mockPdm = $this->getMockBuilder(PermissionDeniedManager::class)
            ->setConstructorArgs([$this->permissionDeniedConfig])
            ->getMock();
        $mockPdm->method('getDeniedTemplateBehavior')->willReturn($config['deniedTemplateBehavior']);
        return $mockPdm;
    }

    /**
     * Get mock permission manager
     *
     * @param array $isAuthorized isAuthorized value to return
     *
     * @return PermissionManager
     */
    protected function getMockPm($isAuthorized = false)
    {
        $mockPm = $this->createMock(PermissionManager::class);
        $mockPm->method('isAuthorized')->willReturn($isAuthorized);
        $mockPm->method('permissionRuleExists')->willReturn(true);

        return $mockPm;
    }

    /**
     * Return a view object populated for these test cases.
     *
     * @return \Laminas\View\Renderer\PhpRenderer
     */
    protected function getMockView()
    {
        $escapehtml = new \Laminas\View\Helper\EscapeHtml();
        $translate = new \VuFind\View\Helper\Root\Translate();
        $transEsc = new \VuFind\View\Helper\Root\TransEsc($translate, $escapehtml);
        $context = new \VuFind\View\Helper\Root\Context();
        $realView = $this->getPhpRenderer(
            compact('translate', 'transEsc', 'context', 'escapehtml')
        );
        return $realView;
    }
}
