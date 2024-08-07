<?php

/**
 * ResourceContainer Test Class
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
     * Test CSS add/remove using strings.
     *
     * @return void
     */
    public function testCssStringSupport(): void
    {
        $container = new ResourceContainer();
        $container->addCss(['a', 'b', 'c']);
        $container->addCss('c');
        $container->addCss('d');
        $container->addCss('e');
        $expectedResult = [
            ['file' => 'a'],
            ['file' => 'b'],
            ['file' => 'c'],
            ['file' => 'd'],
            ['file' => 'e'],
        ];
        $this->assertEquals($expectedResult, $container->getCss());
    }

    /**
     * Test CSS add/remove with a mix of strings/arrays (and using advanced features).
     *
     * @return void
     */
    public function testCssMixedSupport(): void
    {
        $container = new ResourceContainer();
        $container->addCss('a');
        $container->addCss(['file' => 'p2', 'priority' => 220]);
        $container->addCss(['b', 'c', 'd']);
        $container->addCss('http://foo/bar:(min-width: 768px):!IE');
        $container->addCss(['file' => 'd1', 'load_after' => 'd']);
        $container->addCss(['file' => 'p1', 'priority' => 110]);
        $container->addCss(['file' => 'd2', 'load_after' => 'd1']);
        $container->addCss([]);

        $expectedResult = [
            ['file' => 'p1', 'priority' => 110],
            ['file' => 'p2', 'priority' => 220],
            ['file' => 'a'],
            ['file' => 'b'],
            ['file' => 'c'],
            ['file' => 'd'],
            ['file' => 'd1', 'load_after' => 'd'],
            ['file' => 'd2', 'load_after' => 'd1'],
            [
                'file' => 'http://foo/bar',
                'media' => '(min-width: 768px)',
                'conditional' => '!IE',
            ],
        ];
        $this->assertEquals($expectedResult, $container->getCss());
    }

    /**
     * Test disabling CSS.
     *
     * @return void
     */
    public function testCssDisabling(): void
    {
        $container = new ResourceContainer();
        $container->addCss(['a', 'b', 'c']);
        $container->addCss(['file' => 'b', 'disabled' => true]);
        $this->assertEquals(
            [
                ['file' => 'a'],
                ['file' => 'c'],
            ],
            array_values($container->getCss())
        );
    }

    /**
     * Test Exception for priority + load_after in same CSS entry.
     *
     * @return void
     */
    public function testCsssException(): void
    {
        $cssEntry = ['file' => 'test', 'priority' => 100, 'load_after' => 'a'];
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'Using "priority" as well as "load_after" in the same entry '
                . 'is not supported: "' . $cssEntry['file'] . '"'
        );

        $container = new ResourceContainer();
        $container->addCss($cssEntry);
    }

    /**
     * Test Javascript add/remove.
     *
     * @return void
     */
    public function testJs(): void
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
                'attributes' => ['conditional' => 'lt IE 7'],
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
                'attributes' => ['conditional' => 'lt IE 7'],
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
    public function testJsDisabling(): void
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
    public function testJsException(): void
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
    public function testEncoding(): void
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
    public function testFavicon(): void
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
    public function testGenerator(): void
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
    public function testConfigParsing(): void
    {
        $container = new ResourceContainer();
        $tests = [
            'foo:bar:baz' => ['foo', 'bar', 'baz'],
            'http://foo/bar:baz:xyzzy' => ['http://foo/bar', 'baz', 'xyzzy'],
        ];
        foreach ($tests as $test => $expected) {
            $this->assertEquals(
                $expected,
                $container->parseSetting($test)
            );
        }
    }
}
