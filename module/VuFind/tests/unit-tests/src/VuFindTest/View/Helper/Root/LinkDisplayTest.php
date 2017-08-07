<?php
/**
 * LinkDisplay view helper Test Class
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
use VuFind\View\Helper\Root\LinkDisplay;

/**
 * LinkDisplay view helper Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class LinkDisplayTest  extends \VuFindTest\Unit\ViewHelperTestCase
{
    /**
     * Sample configuration with varios config options.
     *
     * @var array
     */
    protected $permissionDeniedConfig = [
        'permissionDeniedTemplate' => [
            'deniedTemplateBehavior' => "showTemplate:record/displayLogicTest:param1=noValue",
            'deniedControllerBehavior' => "showTemplate:record/ActionTest:param1=noValue"
        ],
        'permissionDeniedTemplateNoParams' => [
            'deniedTemplateBehavior' => "showTemplate:record/displayLogicTest",
            'deniedControllerBehavior' => "showTemplate:record/ActionTest"
        ],
        'permissionDeniedMessage' => [
            'deniedTemplateBehavior' => "showMessage:dl_translatable_test",
            'deniedControllerBehavior' => "showTemplate:action_translatable_test"
        ],
        'permissionDeniedLogin' => [
            'deniedControllerBehavior' => "promptLogin"
        ],
        'permissionDeniedException' => [
            'deniedControllerBehavior' => "exception:ForbiddenException:exception_message"
        ],
        'permissionDeniedNonExistentException' => [
            'deniedControllerBehavior' => "exception:NonExistentException:exception_message"
        ],
        'permissionDeniedNothing' => [
        ],
    ];

    /**
     * Standard setup method
     *
     * @return void
     */
    public function setUp()
    {
    }

    /**
     * Standard teardown method
     *
     * @return void
     */
    public function tearDown()
    {
    }

    /**
     * Test the message display
     *
     * @return void
     */
    public function testMessageDisplay()
    {
        $mockPmdMessage = $this->getMockPmd([
                'deniedTemplateBehavior' => [
                    'action' => 'showMessage',
                    'value' => 'dl_translatable_test',
                    'params' => [],
                ],
            ]);

        $translator = new \VuFind\View\Helper\Root\Translate();
        $realView = $this->getPhpRenderer(['translate' => $translator]);

        $linkDisplayHelper = new LinkDisplay($this->getMockPm(false), $mockPmdMessage);
        $linkDisplayHelper->setView($realView);

        $displayBlock = $linkDisplayHelper->getDisplayBlock('permissionDeniedMessage');
        $this->assertEquals('dl_translatable_test', $displayBlock);
    }

    /**
     * Test the template display
     *
     * @return void
     */
    public function testTemplateDisplay()
    {
        // Template does not exist, expect an exception, though
        $this->setExpectedException('Zend\View\Exception\RuntimeException');

        $mockPmd = $this->getMockPmd([
                'deniedTemplateBehavior' => [
                    'action' => 'showTemplate',
                    'value' => 'record/displayLogicTest',
                    'params' => [],
                ],
            ]);

        $translate = new \VuFind\View\Helper\Root\Translate();
        $context = new \VuFind\View\Helper\Root\Context();
        $realView = $this->getPhpRenderer(
            ['translate' => $translate, 'context' => $context]
        );

        $linkDisplayHelper = new LinkDisplay($this->getMockPm(false), $mockPmd);
        $linkDisplayHelper->setView($realView);

        $displayBlock = $linkDisplayHelper->getDisplayBlock('permissionDeniedTemplate');
    }

    /**
     * Test the template display with an existing template
     *
     * @return void
     */
    public function testExistingTemplateDisplay()
    {
        // This test does not work properly at the moment.
        // The problem is, if the template contains a transEsc function call
        $this->markTestSkipped();

        $mockPmd = $this->getMockPmd([
                'deniedTemplateBehavior' => [
                    'action' => 'showTemplate',
                    'value' => 'ajax/status-available.phtml',
                    'params' => [],
                ],
            ]);

        $escaper = new \Zend\View\Helper\EscapeHtml();
        $translator = new \VuFind\View\Helper\Root\Translate();
        $transEsc = new \VuFind\View\Helper\Root\TransEsc();
        $context = new \VuFind\View\Helper\Root\Context();
        $realView = $this->getPhpRenderer(
            ['translate' => $translator, 'escapehtml' => $escaper, 'transesc' => $transEsc, 'context' => $context]
        );

        $linkDisplayHelper = new LinkDisplay($this->getMockPm(false), $mockPmd);
        $linkDisplayHelper->setView($realView);

        $displayBlock = $linkDisplayHelper->getDisplayBlock('permissionDeniedTemplate');
    }

    /**
     * Get mock driver that returns a deniedTemplateBehavior.
     *
     * @param array $config Config containing DeniedTemplateBehavior to return
     *
     * @return \VuFind\Role\PermissionDeniedManager
     */
    protected function getMockPmd($config = false) {
        $mockPmd = $this->getMockBuilder('\VuFind\Role\PermissionDeniedManager')
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
    protected function getMockPm($isAuthorized = false) {
        $mockPm = $this->getMockBuilder('\VuFind\Role\PermissionManager')
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
        return $this->getMockBuilder('VuFind\View\Helper\Root\Context')
            ->disableOriginalConstructor()->getMock();
    }
}
