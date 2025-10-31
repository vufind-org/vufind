<?php

/**
 * AssetManager view helper Test Class
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
 * AssetManager view helper Test Class
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
     * @return array[]
     */
    public static function outputInlineScriptProvider(): array
    {
        return [
            'default settings' => [[], false, 'text/javascript'],
            'arbitrary attribute' => [['data-foo' => 'bar'], true, 'text/javascript'],
            'arbitrary MIME type' => [['type' => 'mime/type'], false, 'mime/type'],
        ];
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
        $assetManager = $view->plugin('assetManager');
        $options = ['allow_arbitrary_attributes' => $arbitrary];
        $this->assertEquals('output', $assetManager->outputInlineScriptLink($script, $attrs, $options));
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
        $assetManager = $view->plugin('assetManager');
        $options = ['allow_arbitrary_attributes' => $arbitrary];
        $this->assertEquals('output', $assetManager->outputInlineScriptString($script, $attrs, $options));
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
        $pipeline->method('process')->willReturnCallback(function ($scripts, $type) {
            $this->assertEquals('js', $type);
            return $scripts;
        });
        $manager = $this->getMockBuilder(AssetManager::class)
            ->setConstructorArgs([$themeInfo, $pipeline])
            ->onlyMethods(['outputInlineScriptLink', 'outputInlineScriptString', 'outputStyleAssets'])
            ->getMock();
        $manager->method('outputInlineScriptLink')->willReturnCallback(function ($src, $attrs, $arbitrary) {
            return $src . '/' . implode('|', $attrs) . '/' . ($arbitrary ? 1 : 0);
        });
        $manager->method('outputInlineScriptString')->willReturnCallback(function ($script, $attrs, $arbitrary) {
            return $script . '/' . implode('|', $attrs) . '/' . ($arbitrary ? 1 : 0);
        });
        $manager->method('outputStyleAssets')->willReturn('');
        $manager->setView($this->getPhpRenderer());
        $manager->appendScriptString('foo')
            ->appendScriptLink('foo.js')
            ->prependScriptString('bar', ['attr'], options: ['allow_arbitrary_attributes' => true]);
        $this->assertEquals("bar/attr/1\nfoo//0\nfoo.js//0", trim($manager->outputHeaderAssets()));
        $manager->forcePrependScriptLink('bar.js')
            ->forcePrependScriptLink('foo.js', ['attr1'], options: ['allow_arbitrary_attributes' => true]);
        $this->assertEquals("foo.js/attr1/1\nbar.js//0\nbar/attr/1\nfoo//0", trim($manager->outputHeaderAssets()));
        $manager->appendScriptString('foot1', position: 'footer')
            ->prependScriptString('foot0', position: 'footer')
            ->appendScriptLink('foot.js', position: 'footer')
            ->forcePrependScriptLink('pre-foot.js', position: 'footer');
        $this->assertEquals("pre-foot.js//0\nfoot0//0\nfoot1//0\nfoot.js//0", trim($manager->outputFooterAssets()));
        $manager->clearScriptList()
            ->appendScriptString('xyzzy', ['foo'], options: ['allow_arbitrary_attributes' => true]);
        $this->assertEquals('xyzzy/foo/1', trim($manager->outputHeaderAssets()));
        $this->assertEquals('', trim($manager->outputFooterAssets()));
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
        $mockHelper = new class ($appendMethod) {
            protected $data = [];

            /**
             * Constructor
             *
             * @param string $appendMethod Name of append method to simulate
             */
            public function __construct(protected string $appendMethod)
            {
            }

            /**
             * Return the collected data.
             *
             * @return string
             */
            public function __invoke()
            {
                $str = implode("\n", $this->data);
                $this->data = [];
                return $str;
            }

            /**
             * Magic method to simulate appending.
             *
             * @param string $method Method name
             * @param array  $args   Arguments sent to method
             *
             * @return void
             * @throws Exception
             */
            public function __call($method, $args)
            {
                if ($method !== $this->appendMethod) {
                    throw new Exception("Unexpected method call: $method");
                }
                $this->data[] = implode(
                    '/',
                    array_map(fn ($data) => is_array($data) ? implode('|', $data) : $data, $args)
                );
            }
        };
        return $mockHelper;
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
        $pipeline->method('process')->willReturnCallback(function ($styles, $type) {
            $this->assertEquals('css', $type);
            return $styles;
        });
        $manager = $this->getMockBuilder(AssetManager::class)
            ->setConstructorArgs([$themeInfo, $pipeline])
            ->onlyMethods(['outputScriptAssets'])
            ->getMock();
        $manager->method('outputScriptAssets')->willReturn('');
        $helpers = [
            'headLink' => $this->getMockStyleHelper('appendStylesheet'),
            'headStyle' => $this->getMockStyleHelper('appendStyle'),
        ];
        $manager->setView($this->getPhpRenderer($helpers));
        $manager->appendStyleString('foo')
            ->appendStyleLink('foo.css')
            ->forcePrependStyleLink('bar.css');
        $this->assertEquals("bar.css/screen//\nfoo.css/screen//\nfoo/", trim($manager->outputHeaderAssets()));
        $manager->clearStyleList()
            ->appendStyleLink('xyzzy.css', 'print', 'cond', ['a', 'b'])
            ->appendStyleString('baz', ['c', 'd'])
            ->forcePrependStyleLink('pre.css', 'odd', 'oop', ['z']);
        $this->assertEquals(
            "pre.css/odd/oop/z\nxyzzy.css/print/cond/a|b\nbaz/c|d",
            trim($manager->outputHeaderAssets())
        );
    }
}
