<?php
/**
 * HeadThemeResources view helper Test Class
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
namespace VuFindTest\View\Helper;
use VuFindTheme\ResourceContainer, VuFindTheme\View\Helper\HeadThemeResources;

/**
 * HeadThemeResources view helper Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class HeadThemeResourcesTest extends \VuFindTest\Unit\TestCase
{
    /**
     * Test the helper.
     *
     * @return void
     */
    public function testHelper()
    {
        $helper = new HeadThemeResources($this->getResourceContainer());
        $helper->setView($this->getMockView());
        $helper->__invoke();
    }

    /**
     * Test configuration parsing.
     *
     * @return void
     */
    public function testConfigParsing()
    {
        $helper = new HeadThemeResources($this->getResourceContainer());
        $tests = [
            'foo:bar:baz' => ['foo', 'bar', 'baz'],
            'http://foo/bar:baz:xyzzy' => ['http://foo/bar', 'baz', 'xyzzy']
        ];
        foreach ($tests as $test => $expected) {
            $this->assertEquals(
                $expected, $this->callMethod($helper, 'parseSetting', [$test])
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
     * @return \Zend\View\Renderer\PhpRenderer
     */
    protected function getMockView()
    {
        $view = $this->getMock('Zend\View\Renderer\PhpRenderer');
        $view->expects($this->at(0))->method('plugin')
            ->with($this->equalTo('headmeta'))
            ->will($this->returnValue($this->getMockHeadMeta()));
        $view->expects($this->at(1))->method('plugin')
            ->with($this->equalTo('headlink'))
            ->will($this->returnValue($this->getMockHeadLink()));
        $view->expects($this->at(2))->method('plugin')
            ->with($this->equalTo('headscript'))
            ->will($this->returnValue($this->getMockHeadScript()));
        return $view;
    }

    /**
     * Get a fake HeadMeta helper.
     *
     * @return \Zend\View\Helper\HeadMeta
     */
    protected function getMockHeadMeta()
    {
        $mock = $this->getMockBuilder('VuFindTheme\View\Helper\HeadMeta')
            ->disableOriginalConstructor()
            ->setMethods(['__invoke', 'prependHttpEquiv', 'appendName'])
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
     * @return \Zend\View\Helper\HeadLink
     */
    protected function getMockHeadLink()
    {
        $mock = $this->getMockBuilder('VuFindTheme\View\Helper\HeadLink')
            ->disableOriginalConstructor()
            ->getMock();
        $mock->expects($this->any())->method('__invoke')->will($this->returnValue($mock));
        return $mock;
    }

    /**
     * Get a fake HeadScript helper.
     *
     * @return \Zend\View\Helper\HeadScript
     */
    protected function getMockHeadScript()
    {
        $mock = $this->getMockBuilder('VuFindTheme\View\Helper\HeadScript')
            ->disableOriginalConstructor()
            ->getMock();
        $mock->expects($this->any())->method('__invoke')->will($this->returnValue($mock));
        return $mock;
    }
}
