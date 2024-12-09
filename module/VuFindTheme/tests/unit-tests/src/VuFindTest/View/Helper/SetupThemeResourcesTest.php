<?php

/**
 * SetupThemeResources view helper Test Class
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\View\Helper;

use VuFindTheme\ResourceContainer;
use VuFindTheme\View\Helper\SetupThemeResources;

/**
 * SetupThemeResources view helper Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SetupThemeResourcesTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ReflectionTrait;

    /**
     * Test the helper.
     *
     * @return void
     */
    public function testHelper()
    {
        $helper = new SetupThemeResources($this->getResourceContainer());
        $helper->setView($this->getMockView());
        $helper();
    }

    /**
     * Test configuration parsing.
     *
     * @return void
     */
    public function testConfigParsing()
    {
        $tests = [
            'foo:bar:baz' => ['foo', 'bar', 'baz'],
            'http://foo/bar:baz:xyzzy' => ['http://foo/bar', 'baz', 'xyzzy'],
        ];
        foreach ($tests as $test => $expected) {
            $this->assertEquals(
                $expected,
                $this->callMethod($this->getResourceContainer(), 'parseSetting', [$test])
            );
        }
    }

    /**
     * Get a populated resource container for testing.
     *
     * @return ResourceContainer
     */
    protected function getResourceContainer()
    {
        $rc = new ResourceContainer();
        $rc->setEncoding('utf-8');
        $rc->setGenerator('fake-generator');
        return $rc;
    }

    /**
     * Get a fake view object.
     *
     * @return \Laminas\View\Renderer\PhpRenderer
     */
    protected function getMockView()
    {
        $view = new \Laminas\View\Renderer\PhpRenderer();
        $container = new \VuFindTest\Container\MockViewHelperContainer($this);
        $container->set('headMeta', $this->getMockHeadMeta());
        $container->set('headLink', $this->getMockHeadLink());
        $container->set('headScript', $this->getMockHeadScript());
        $view->setHelperPluginManager($container);
        return $view;
    }

    /**
     * Get a fake HeadMeta helper.
     *
     * @return \PHPUnit\Framework\MockObject\MockObject&\VuFindTheme\View\Helper\HeadMeta
     */
    protected function getMockHeadMeta()
    {
        $mock = $this->getMockBuilder(\Laminas\View\Helper\HeadMeta::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['__invoke'])
            // These are side effects of __call and need to be added for mocking:
            ->addMethods(['prependHttpEquiv', 'appendName'])
            ->getMock();
        $mock->expects($this->any())->method('__invoke')->will($this->returnValue($mock));
        $mock->expects($this->once())->method('prependHttpEquiv')
            ->with($this->equalTo('Content-Type'), $this->equalTo('text/html; charset=utf-8'));
        $mock->expects($this->once())->method('appendName')
            ->with($this->equalTo('Generator'), $this->equalTo('fake-generator'));
        return $mock;
    }

    /**
     * Get a fake HeadLink helper.
     *
     * @return \Laminas\View\Helper\HeadLink
     */
    protected function getMockHeadLink()
    {
        $mock = $this->getMockBuilder(\VuFindTheme\View\Helper\HeadLink::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mock->expects($this->any())->method('__invoke')->will($this->returnValue($mock));
        return $mock;
    }

    /**
     * Get a fake HeadScript helper.
     *
     * @return \Laminas\View\Helper\HeadScript
     */
    protected function getMockHeadScript()
    {
        $mock = $this->getMockBuilder(\VuFindTheme\View\Helper\HeadScript::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mock->expects($this->any())->method('__invoke')->will($this->returnValue($mock));
        return $mock;
    }
}
