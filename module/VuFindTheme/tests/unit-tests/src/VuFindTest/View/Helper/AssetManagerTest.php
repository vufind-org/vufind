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

use Laminas\View\Helper\HeadLink;
use Laminas\View\Helper\HeadStyle;
use Laminas\View\Helper\InlineScript;
use Laminas\View\Renderer\PhpRenderer;
use VuFind\Escaper\Escaper;
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
            '<script type="text/javascript" src="foo.js"></script>',
        ];
        yield 'arbitrary attribute' => [
            ['data-foo' => 'bar'],
            '<script type="text/javascript" data-foo="bar" src="foo.js"></script>',
        ];
        yield 'arbitrary MIME type' => [
            ['type' => 'mime/type'],
            '<script type="mime/type" src="foo.js"></script>',
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
                <script type="text/javascript">
                    //<!--
                    foo
                    //-->
                </script>
                EOT,
        ];
        yield 'arbitrary attribute' => [
            ['data-foo' => 'bar'],
            <<<EOT
                <script type="text/javascript" data-foo="bar">
                    //<!--
                    foo
                    //-->
                </script>
                EOT,
        ];
        yield 'arbitrary MIME type' => [
            ['type' => 'mime/type'],
            <<<EOT
                <script type="mime/type">
                    //<!--
                    foo
                    //-->
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

        $manager = $this->getMockBuilder(AssetManager::class)
            ->setConstructorArgs([
                $themeInfo,
                $pipeline,
                '/',
                new \Laminas\View\Helper\HeadLink(),
                new HeadStyle(),
                new InlineScript(),
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

        $manager = $this->getMockBuilder(AssetManager::class)
            ->setConstructorArgs([
                $themeInfo,
                $pipeline,
                '/',
                $this->getHeadLink(),
                $this->getHeadStyle(),
                $this->getInlineScript(),
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
                <style type="text/css" media="screen">
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
                <link href="xyzzy.css" media="print" rel="stylesheet" type="text/css" a="1" b="2">
                <style type="text/css" media="screen">
                foo
                </style>
                <style type="text/css" media="screen">
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
        $escaper = new Escaper();
        $renderer = new PhpRenderer();
        $headLink = new HeadLink();
        $headLink->setEscaper($escaper);
        $headLink->setView($renderer);
        return $headLink;
    }

    /**
     * Get HeadStyle helper.
     *
     * @return HeadStyle
     */
    protected function getHeadStyle(): HeadStyle
    {
        $escaper = new Escaper();
        $renderer = new PhpRenderer();
        $headStyle = new HeadStyle();
        $headStyle->setEscaper($escaper);
        $headStyle->setView($renderer);
        return $headStyle;
    }

    /**
     * Get InlineScript helper.
     *
     * @return InlineScript
     */
    protected function getInlineScript(): InlineScript
    {
        $escaper = new Escaper();
        $renderer = new PhpRenderer();
        $inlineScript = new InlineScript();
        $inlineScript->setEscaper($escaper);
        $inlineScript->setView($renderer);
        return $inlineScript;
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
