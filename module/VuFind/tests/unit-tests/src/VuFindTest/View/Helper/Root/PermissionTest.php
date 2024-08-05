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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\View\Helper\Root;

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
        $mockPmdMessage = $this->getMockPmd(
            [
                'deniedTemplateBehavior' => [
                    'action' => 'showMessage',
                    'value' => 'dl_translatable_test',
                    'params' => [],
                ],
            ]
        );

        $helper = new Permission($this->getMockPm(false), $mockPmdMessage);
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
        $mockPmd = $this->getMockPmd(
            [
                'deniedTemplateBehavior' => [
                    'action' => 'showTemplate',
                    'value' => 'record/displayLogicTest',
                    'params' => [],
                ],
            ]
        );

        $helper = new Permission($this->getMockPm(false), $mockPmd);
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
        $mockPmd = $this->getMockPmd(
            [
                'deniedTemplateBehavior' => [
                    'action' => 'showTemplate',
                    'value' => 'ajax/status-available.phtml',
                    'params' => [],
                ],
            ]
        );

        $helper = new Permission($this->getMockPm(false), $mockPmd);
        $helper->setView($this->getMockView());

        $this->assertEquals(
            '<span class="label label-success">Available</span>',
            trim($helper->getAlternateContent('permissionDeniedTemplate'))
        );
    }

    /**
     * Get mock driver that returns a deniedTemplateBehavior.
     *
     * @param array $config Config containing DeniedTemplateBehavior to return
     *
     * @return \VuFind\Role\PermissionDeniedManager
     */
    protected function getMockPmd($config = false)
    {
        $mockPmd = $this->getMockBuilder(\VuFind\Role\PermissionDeniedManager::class)
            ->setConstructorArgs([$this->permissionDeniedConfig])
            ->getMock();
        $mockPmd->expects($this->any())->method('getDeniedTemplateBehavior')
            ->will($this->returnValue($config['deniedTemplateBehavior']));
        return $mockPmd;
    }

    /**
     * Get mock permission manager
     *
     * @param array $isAuthorized isAuthorized value to return
     *
     * @return \VuFind\Role\PermissionManager
     */
    protected function getMockPm($isAuthorized = false)
    {
        $mockPm = $this->getMockBuilder(\VuFind\Role\PermissionManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockPm->expects($this->any())->method('isAuthorized')
            ->will($this->returnValue($isAuthorized));
        $mockPm->expects($this->any())->method('permissionRuleExists')
            ->will($this->returnValue(true));

        return $mockPm;
    }

    /**
     * Get mock context helper.
     *
     * @return \VuFind\View\Helper\Root\Context
     */
    protected function getMockContext()
    {
        return $this->getMockBuilder(\VuFind\View\Helper\Root\Context::class)
            ->disableOriginalConstructor()->getMock();
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
        $transEsc = new \VuFind\View\Helper\Root\TransEsc();
        $context = new \VuFind\View\Helper\Root\Context();
        $realView = $this->getPhpRenderer(
            compact('translate', 'transEsc', 'context', 'escapehtml')
        );
        $transEsc->setView($realView);
        return $realView;
    }
}
