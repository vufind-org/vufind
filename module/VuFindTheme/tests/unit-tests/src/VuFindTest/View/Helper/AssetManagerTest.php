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

use Laminas\View\Helper\InlineScript;
use VuFindTest\Feature\ViewTrait;

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
     *
     * @dataProvider outputInlineScriptProvider
     */
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
        $this->assertEquals('output', $assetManager->outputInlineScriptLink($script, $attrs, $arbitrary));
    }

    /**
     * Test that outputInlineScriptString() behaves as expected.
     *
     * @param array  $attrs        Attributes array
     * @param bool   $arbitrary    Value for arbitrary attributes flag
     * @param string $expectedType The type we expect to pass to the helper
     *
     * @return void
     *
     * @dataProvider outputInlineScriptProvider
     */
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
        $this->assertEquals('output', $assetManager->outputInlineScriptString($script, $attrs, $arbitrary));
    }
}
