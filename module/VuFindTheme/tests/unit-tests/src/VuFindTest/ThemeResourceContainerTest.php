<?php

/**
 * ResourceContainer Test Class
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest;

use VuFindTheme\ResourceContainer;

/**
 * ResourceContainer Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ThemeResourceContainerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test CSS add/remove.
     *
     * @return void
     */
    public function testCss()
    {
        $container = new ResourceContainer();
        $container->addCss(['a', 'b', 'c']);
        $container->addCss('c');
        $container->addCss('d');
        $container->addCss('e');
        $this->assertEquals([], array_diff(['a', 'b', 'c', 'd'], $container->getCss()));
    }

    /**
     * Test Javascript add/remove.
     *
     * @return void
     */
    public function testJs()
    {
        $container = new ResourceContainer();
        $container->addJs('a');
        $container->addJs(['file' => 'p2', 'priority' => 220]);
        $container->addJs(['b', 'c']);
        $container->addJs(['file' => 'd', 'position' => 'header']);
        $container->addJs(['file' => 'df', 'position' => 'footer']);
        $container->addJs('http://foo/bar:lt IE 7');
        $container->addJs(['file' => 'd1', 'load_after' => 'd']);
        $container->addJs(['file' => 'p1', 'priority' => 110]);
        $container->addJs(['file' => 'd2', 'load_after' => 'd1']);
        $container->addJs([]);

        $expectedResult = [
            ['file' => 'p1', 'priority' => 110, 'position' => 'header'],
            ['file' => 'p2', 'priority' => 220, 'position' => 'header'],
            ['file' => 'a', 'position' => 'header'],
            ['file' => 'b', 'position' => 'header'],
            ['file' => 'c', 'position' => 'header'],
            ['file' => 'd', 'position' => 'header'],
            ['file' => 'd1', 'load_after' => 'd', 'position' => 'header'],
            ['file' => 'd2', 'load_after' => 'd1', 'position' => 'header'],
            ['file' => 'df', 'position' => 'footer'],
            [
                'file' => 'http://foo/bar',
                'position' => 'header',
                'attributes' => ['conditional' => 'lt IE 7']
            ],
        ];
        $this->assertEquals($expectedResult, $container->getJs());

        $expectedHeaderResult = [
            ['file' => 'p1', 'priority' => 110, 'position' => 'header'],
            ['file' => 'p2', 'priority' => 220, 'position' => 'header'],
            ['file' => 'a', 'position' => 'header'],
            ['file' => 'b', 'position' => 'header'],
            ['file' => 'c', 'position' => 'header'],
            ['file' => 'd', 'position' => 'header'],
            ['file' => 'd1', 'load_after' => 'd', 'position' => 'header'],
            ['file' => 'd2', 'load_after' => 'd1', 'position' => 'header'],
            [
                'file' => 'http://foo/bar',
                'position' => 'header',
                'attributes' => ['conditional' => 'lt IE 7']
            ],
        ];
        $this->assertEquals(
            $expectedHeaderResult,
            array_values($container->getJs('header'))
        );

        $expectedFooterResult = [
            ['file' => 'df', 'position' => 'footer'],
        ];
        $this->assertEquals(
            $expectedFooterResult,
            array_values($container->getJs('footer'))
        );
    }

    /**
     * Test disabling JS.
     *
     * @return void
     */
    public function testJsDisabling()
    {
        $container = new ResourceContainer();
        $container->addJs(['a', 'b', 'c']);
        $container->addJs(['file' => 'b', 'disabled' => true]);
        $this->assertEquals(
            [
                ['file' => 'a', 'position' => 'header'],
                ['file' => 'c', 'position' => 'header'],
            ],
            array_values($container->getJs('header'))
        );
    }

    /**
     * Test Exception for priority + load_after in same js entry.
     *
     * @return void
     */
    public function testJsException()
    {
        $jsEntry = ['file' => 'test', 'priority' => 100, 'load_after' => 'a'];
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'Using "priority" as well as "load_after" in the same entry '
                . 'is not supported: "' . $jsEntry['file'] . '"'
        );

        $container = new ResourceContainer();
        $container->addJs($jsEntry);
    }

    /**
     * Test Encoding set/get.
     *
     * @return void
     */
    public function testEncoding()
    {
        $container = new ResourceContainer();
        $container->setEncoding('fake');
        $this->assertEquals('fake', $container->getEncoding());
    }

    /**
     * Test Favicon set/get.
     *
     * @return void
     */
    public function testFavicon()
    {
        $container = new ResourceContainer();
        $container->setFavicon('fake');
        $this->assertEquals('fake', $container->getFavicon());
    }

    /**
     * Test Generator set/get.
     *
     * @return void
     */
    public function testGenerator()
    {
        $container = new ResourceContainer();
        $container->setGenerator('fake');
        $this->assertEquals('fake', $container->getGenerator());
    }

    /**
     * Test configuration parsing.
     *
     * @return void
     */
    public function testConfigParsing()
    {
        $container = new ResourceContainer();
        $tests = [
            'foo:bar:baz' => ['foo', 'bar', 'baz'],
            'http://foo/bar:baz:xyzzy' => ['http://foo/bar', 'baz', 'xyzzy']
        ];
        foreach ($tests as $test => $expected) {
            $this->assertEquals(
                $expected,
                $container->parseSetting($test)
            );
        }
    }
}
