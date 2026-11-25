<?php

/**
 * AssetManager view helper Test Class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\View\Helper;

use Laminas\View\Helper\Doctype;
use Laminas\View\Helper\HeadStyle;
use Laminas\View\Helper\InlineScript;
use VuFind\Escaper\Escaper;
use VuFind\View\Helper\Root\HeadLink;
use VuFindTheme\AssetPipeline;
use VuFindTheme\ThemeInfo;
use VuFindTheme\View\Helper\AssetManager;

/**
 * AssetManager view helper Test Class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class AssetManagerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Data provider for testOutputInlineScriptLink().
     *
     * @return \Iterator
     */
    public static function outputInlineScriptLinkProvider(): \Iterator
    {
        yield 'default settings' => [
            [],
            '<script src="foo.js"></script>',
        ];
        yield 'arbitrary attribute' => [
            ['data-foo' => 'bar'],
            '<script data-foo="bar" src="foo.js"></script>',
        ];
        yield 'arbitrary MIME type' => [
            ['type' => 'mime/type'],
            '<script src="foo.js" type="mime/type"></script>',
        ];
    }

    /**
     * Test that outputInlineScriptLink() behaves as expected.
     *
     * @param array  $attrs    Attributes array
     * @param string $expected Expected result
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('outputInlineScriptLinkProvider')]
    public function testOutputInlineScriptLink(array $attrs, string $expected): void
    {
        $script = 'foo.js';
        $assetManager = $this->getAssetManager();
        $this->assertSame($expected, $assetManager->outputInlineScriptLink($script, $attrs));
    }

    /**
     * Data provider for testOutputInlineScriptString().
     *
     * @return \Iterator
     */
    public static function outputInlineScriptStringProvider(): \Iterator
    {
        yield 'default settings' => [
            [],
            <<<EOT
                <script>
                foo
                </script>
                EOT,
        ];
        yield 'arbitrary attribute' => [
            ['data-foo' => 'bar'],
            <<<EOT
                <script data-foo="bar">
                foo
                </script>
                EOT,
        ];
        yield 'arbitrary MIME type' => [
            ['type' => 'mime/type'],
            <<<EOT
                <script type="mime/type">
                foo
                </script>
                EOT,
        ];
    }

    /**
     * Test that outputInlineScriptString() behaves as expected.
     *
     * @param array  $attrs    Attributes array
     * @param string $expected Expected output
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('outputInlineScriptStringProvider')]
    public function testOutputInlineScriptString(array $attrs, string $expected): void
    {
        $script = 'foo';
        $assetManager = $this->getAssetManager();
        $this->assertSame($expected, $assetManager->outputInlineScriptString($script, $attrs));
    }

    /**
     * Test manipulation of the script list.
     *
     * @return void
     */
    public function testScriptListManipulation(): void
    {
        $themeInfo = $this->createMock(ThemeInfo::class);
        $pipeline = $this->createMock(AssetPipeline::class);
        $pipeline->method('process')->willReturnCallback(function (array $scripts, string $type): array {
            $this->assertSame('js', $type);
            return $scripts;
        });

        $escaper = new Escaper();
        $doctype = new Doctype();
        $manager = $this->getMockBuilder(AssetManager::class)
            ->setConstructorArgs([
                $themeInfo,
                $pipeline,
                '/',
                new HeadLink(new \Laminas\View\Helper\HeadLink($escaper, $doctype)),
                new HeadStyle($escaper, $doctype),
                new InlineScript($escaper, $doctype),
            ])
            ->onlyMethods(['outputInlineScriptLink', 'outputInlineScriptString', 'outputStyleAssets'])
            ->getMock();
        $manager->method('outputInlineScriptLink')
            ->willReturnCallback(function (string $src, array $attrs): string {
                return $src . '/' . implode('|', $attrs);
            });
        $manager->method('outputInlineScriptString')
            ->willReturnCallback(function (string $script, array $attrs): string {
                return $script . '/' . implode('|', $attrs);
            });
        $manager->method('outputStyleAssets')->willReturn('');

        $manager->appendScriptString('foo')
            ->appendScriptLink('foo.js')
            ->prependScriptString('bar', ['attr']);
        $this->assertSame("bar/attr\nfoo/\nfoo.js/", trim($manager->outputHeaderAssets()));

        $manager->forcePrependScriptLink('bar.js')
            ->forcePrependScriptLink('foo.js', ['attr1']);
        $this->assertSame("foo.js/attr1\nbar.js/\nbar/attr\nfoo/", trim($manager->outputHeaderAssets()));

        $manager->appendScriptString('foot1', position: 'footer')
            ->prependScriptString('foot0', position: 'footer')
            ->appendScriptLink('foot.js', position: 'footer')
            ->forcePrependScriptLink('pre-foot.js', position: 'footer');
        $this->assertSame("pre-foot.js/\nfoot0/\nfoot1/\nfoot.js/", trim($manager->outputFooterAssets()));

        $manager->clearScriptList()
            ->appendScriptString('xyzzy', ['foo']);
        $this->assertSame('xyzzy/foo', trim($manager->outputHeaderAssets()));

        $this->assertSame('', trim($manager->outputFooterAssets()));
    }

    /**
     * Test manipulation of the style list.
     *
     * @return void
     */
    public function testStyleListManipulation(): void
    {
        $themeInfo = $this->createMock(ThemeInfo::class);
        $pipeline = $this->createMock(AssetPipeline::class);
        $pipeline->method('process')->willReturnCallback(function (array $styles, string $type): array {
            $this->assertSame('css', $type);
            return $styles;
        });

        $escaper = new Escaper();
        $doctype = new Doctype();
        $manager = $this->getMockBuilder(AssetManager::class)
            ->setConstructorArgs([
                $themeInfo,
                $pipeline,
                '/',
                new HeadLink(new \Laminas\View\Helper\HeadLink($escaper, $doctype)),
                new HeadStyle($escaper, $doctype),
                new InlineScript($escaper, $doctype),
            ])
            ->onlyMethods(['outputScriptAssets'])
            ->getMock();
        $manager->method('outputScriptAssets')->willReturn('');
        $manager->appendStyleString('foo')
            ->appendStyleLink('foo.css')
            ->forcePrependStyleLink('bar.css');
        $this->assertSame(
            <<<EOT
                <link href="bar.css" media="screen" rel="stylesheet" type="text/css">
                <link href="foo.css" media="screen" rel="stylesheet" type="text/css">
                <style>
                foo
                </style>
                EOT,
            trim($manager->outputHeaderAssets())
        );
        $manager->clearStyleList()
            ->appendStyleLink('xyzzy.css', 'print', 'cond', ['a' => '1', 'b' => '2'])
            ->appendStyleString('baz', ['c' => '3', 'd' => '4'])
            ->forcePrependStyleLink('pre.css', 'odd', 'oop', ['z' => '5']);
        $this->assertSame(
            <<<EOT
                <link href="bar.css" media="screen" rel="stylesheet" type="text/css">
                <link href="foo.css" media="screen" rel="stylesheet" type="text/css">
                <link href="pre.css" media="odd" rel="stylesheet" type="text/css" z="5">
                <link a="1" b="2" href="xyzzy.css" media="print" rel="stylesheet" type="text/css">
                <style>
                foo
                </style>
                <style c="3" d="4">
                baz
                </style>
                EOT,
            trim($manager->outputHeaderAssets())
        );
    }

    /**
     * Get HeadLink helper.
     *
     * @return HeadLink
     */
    protected function getHeadLink(): HeadLink
    {
        return new HeadLink(new \Laminas\View\Helper\HeadLink(new Escaper(), new Doctype()));
    }

    /**
     * Get HeadStyle helper.
     *
     * @return HeadStyle
     */
    protected function getHeadStyle(): HeadStyle
    {
        return new HeadStyle(new Escaper(), new Doctype());
    }

    /**
     * Get InlineScript helper.
     *
     * @return InlineScript
     */
    protected function getInlineScript(): InlineScript
    {
        return new InlineScript(new Escaper(), new Doctype());
    }

    /**
     * Get AssetManager.
     *
     * @return AssetManager
     */
    protected function getAssetManager(): AssetManager
    {
        return new AssetManager(
            $this->createMock(ThemeInfo::class),
            $this->createMock(AssetPipeline::class),
            '/',
            $this->getHeadLink(),
            $this->getHeadStyle(),
            $this->getInlineScript()
        );
    }
}
