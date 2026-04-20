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

use Exception;
use Laminas\View\Helper\InlineScript;
use VuFindTest\Feature\ViewTrait;
use VuFindTheme\AssetPipeline;
use VuFindTheme\ThemeInfo;
use VuFindTheme\View\Helper\AssetManager;

use function is_array;

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
    use ViewTrait;

    /**
     * Data provider for testOutputInlineScriptLink() and testOutputInlineScriptString().
     *
     * @return \Iterator
     */
    public static function outputInlineScriptProvider(): \Iterator
    {
        yield 'default settings' => [[], false, 'text/javascript'];
        yield 'arbitrary attribute' => [['data-foo' => 'bar'], true, 'text/javascript'];
        yield 'arbitrary MIME type' => [['type' => 'mime/type'], false, 'mime/type'];
    }

    /**
     * Test that outputInlineScriptLink() behaves as expected.
     *
     * @param array  $attrs        Attributes array
     * @param bool   $arbitrary    Value for arbitrary attributes flag
     * @param string $expectedType The type we expect to pass to the helper
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('outputInlineScriptProvider')]
    public function testOutputInlineScriptLink(array $attrs, bool $arbitrary, string $expectedType): void
    {
        $script = 'foo.js';
        $inlineScriptHelper = $this->createMock(InlineScript::class);
        $inlineScriptHelper->method('arbitraryAttributesAllowed')->willReturn(false);
        $inlineScriptHelper
            ->expects($arbitrary ? $this->exactly(2) : $this->never())
            ->method('setAllowArbitraryAttributes');
        $expectedAttrs = isset($attrs['type']) ? array_diff($attrs, ['type' => $attrs['type']]) : $attrs;
        $inlineScriptHelper
            ->expects($this->once())
            ->method('__call')
            ->with('setFile', [$script, $expectedType, $expectedAttrs]);
        $inlineScriptHelper->method('__invoke')->willReturn('output');
        $view = $this->getPhpRenderer(['inlineScript' => $inlineScriptHelper]);
        $assetManager = new AssetManager(
            $this->createMock(ThemeInfo::class),
            $this->createMock(AssetPipeline::class),
            $view->plugin('url'),
            $view->plugin('headLink'),
            $view->plugin('headStyle'),
            $inlineScriptHelper
        );
        $options = ['allow_arbitrary_attributes' => $arbitrary];
        $this->assertSame('output', $assetManager->outputInlineScriptLink($script, $attrs, $options));
    }

    /**
     * Test that outputInlineScriptString() behaves as expected.
     *
     * @param array  $attrs        Attributes array
     * @param bool   $arbitrary    Value for arbitrary attributes flag
     * @param string $expectedType The type we expect to pass to the helper
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('outputInlineScriptProvider')]
    public function testOutputInlineScriptString(array $attrs, bool $arbitrary, string $expectedType): void
    {
        $script = 'foo';
        $inlineScriptHelper = $this->createMock(InlineScript::class);
        $inlineScriptHelper->method('arbitraryAttributesAllowed')->willReturn(false);
        $inlineScriptHelper
            ->expects($arbitrary ? $this->exactly(2) : $this->never())
            ->method('setAllowArbitraryAttributes');
        $expectedAttrs = isset($attrs['type']) ? array_diff($attrs, ['type' => $attrs['type']]) : $attrs;
        $inlineScriptHelper
            ->expects($this->once())
            ->method('__call')
            ->with('setScript', [$script, $expectedType, $expectedAttrs]);
        $inlineScriptHelper->method('__invoke')->willReturn('output');
        $view = $this->getPhpRenderer(['inlineScript' => $inlineScriptHelper]);
        $assetManager = new AssetManager(
            $this->createMock(ThemeInfo::class),
            $this->createMock(AssetPipeline::class),
            $view->plugin('url'),
            $view->plugin('headLink'),
            $view->plugin('headStyle'),
            $inlineScriptHelper
        );
        $options = ['allow_arbitrary_attributes' => $arbitrary];
        $this->assertSame('output', $assetManager->outputInlineScriptString($script, $attrs, $options));
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

        $view = $this->getPhpRenderer();
        $manager = $this->getMockBuilder(AssetManager::class)
            ->setConstructorArgs([
                $themeInfo,
                $pipeline,
                $view->plugin('url'),
                $view->plugin('headLink'),
                $view->plugin('headStyle'),
                $view->plugin('inlineScript'),
            ])
            ->onlyMethods(['outputInlineScriptLink', 'outputInlineScriptString', 'outputStyleAssets'])
            ->getMock();
        $manager->method('outputInlineScriptLink')
            ->willReturnCallback(function (string $src, array $attrs, array $arbitrary): string {
                return $src . '/' . implode('|', $attrs) . '/' . ($arbitrary ? 1 : 0);
            });
        $manager->method('outputInlineScriptString')
            ->willReturnCallback(function (string $script, array $attrs, array $arbitrary): string {
                return $script . '/' . implode('|', $attrs) . '/' . ($arbitrary ? 1 : 0);
            });
        $manager->method('outputStyleAssets')->willReturn('');
        $manager->appendScriptString('foo')
            ->appendScriptLink('foo.js')
            ->prependScriptString('bar', ['attr'], options: ['allow_arbitrary_attributes' => true]);
        $this->assertSame("bar/attr/1\nfoo//0\nfoo.js//0", trim($manager->outputHeaderAssets()));
        $manager->forcePrependScriptLink('bar.js')
            ->forcePrependScriptLink('foo.js', ['attr1'], options: ['allow_arbitrary_attributes' => true]);
        $this->assertSame("foo.js/attr1/1\nbar.js//0\nbar/attr/1\nfoo//0", trim($manager->outputHeaderAssets()));
        $manager->appendScriptString('foot1', position: 'footer')
            ->prependScriptString('foot0', position: 'footer')
            ->appendScriptLink('foot.js', position: 'footer')
            ->forcePrependScriptLink('pre-foot.js', position: 'footer');
        $this->assertSame("pre-foot.js//0\nfoot0//0\nfoot1//0\nfoot.js//0", trim($manager->outputFooterAssets()));
        $manager->clearScriptList()
            ->appendScriptString('xyzzy', ['foo'], options: ['allow_arbitrary_attributes' => true]);
        $this->assertSame('xyzzy/foo/1', trim($manager->outputHeaderAssets()));
        $this->assertSame('', trim($manager->outputFooterAssets()));
    }

    /**
     * Build a simulated version of a Laminas style helper.
     *
     * @param string $appendMethod Expected method name for append operations.
     *
     * @return object
     */
    public function getMockStyleHelper(string $appendMethod): object
    {
        $data = [];
        $baseClass = $appendMethod === 'appendStylesheet'
            ? \Laminas\View\Helper\HeadLink::class
            : \Laminas\View\Helper\HeadStyle::class;

        $mock = $this->getMockBuilder($baseClass)
            ->disableOriginalConstructor()
            ->onlyMethods(['__invoke', '__call'])
            ->getMock();

        $mock->method('__call')->willReturnCallback(
            function ($method, $args) use ($appendMethod, &$data): void {
                if ($method !== $appendMethod) {
                    throw new \Exception("Unexpected method call: $method");
                }
                $data[] = implode(
                    '/',
                    array_map(fn ($d) => is_array($d) ? implode('|', $d) : $d, $args)
                );
            }
        );

        $mock->method('__invoke')->willReturnCallback(
            function () use (&$data) {
                $str = implode("\n", $data);
                $data = [];
                return $str;
            }
        );

        return $mock;
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

        $helpers = [
            'headLink' => $this->getMockStyleHelper('appendStylesheet'),
            'headStyle' => $this->getMockStyleHelper('appendStyle'),
        ];
        $view = $this->getPhpRenderer($helpers);
        $manager = $this->getMockBuilder(AssetManager::class)
            ->setConstructorArgs([
                $themeInfo,
                $pipeline,
                $view->plugin('url'),
                $helpers['headLink'],
                $helpers['headStyle'],
                $view->plugin('inlineScript'),
            ])
            ->onlyMethods(['outputScriptAssets'])
            ->getMock();
        $manager->method('outputScriptAssets')->willReturn('');
        $manager->appendStyleString('foo')
            ->appendStyleLink('foo.css')
            ->forcePrependStyleLink('bar.css');
        $this->assertSame("bar.css/screen//\nfoo.css/screen//\nfoo/", trim($manager->outputHeaderAssets()));
        $manager->clearStyleList()
            ->appendStyleLink('xyzzy.css', 'print', 'cond', ['a', 'b'])
            ->appendStyleString('baz', ['c', 'd'])
            ->forcePrependStyleLink('pre.css', 'odd', 'oop', ['z']);
        $this->assertSame(
            "pre.css/odd/oop/z\nxyzzy.css/print/cond/a|b\nbaz/c|d",
            trim($manager->outputHeaderAssets())
        );
    }
}
