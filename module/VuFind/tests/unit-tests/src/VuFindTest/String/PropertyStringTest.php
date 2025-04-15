<?php

/**
 * PropertyString Test Class
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\String;

use VuFind\String\PropertyString;

/**
 * PropertyString Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class PropertyStringTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test getters and setters
     *
     * @return void
     */
    public function testBasicFunctionality(): void
    {
        $str = new PropertyString('');
        $str
            ->setString('Foo')
            ->setHtml('<p>Foo</p>')
            ->setIds(['id_foo', 'id_bar']);
        $str['attr'] = 'bonus';
        $str['attr2'] = 'bonus2';

        $this->assertEquals('Foo', (string)$str);
        $this->assertEquals('Foo', $str->getString());
        $this->assertEquals('<p>Foo</p>', $str->getHtml());
        $this->assertEquals(['id_foo', 'id_bar'], $str->getIds());
        $this->assertEquals('bonus', $str['attr']);
        $this->assertEquals('bonus2', $str['attr2']);
        $this->assertTrue(isset($str['attr']));
        $this->assertFalse(isset($str['nattr']));

        unset($str['attr']);
        unset($str['nattr']);
        $this->assertFalse(isset($str['attr']));

        $str->addId('id_baz');
        $this->assertEquals(['id_foo', 'id_bar', 'id_baz'], $str->getIds());

        $this->assertNull($str->isHtmlTrusted());
        $str->setHtmlTrusted(true);
        $this->assertTrue($str->isHtmlTrusted());
    }

    /**
     * Data provider for testFromHtml
     *
     * @return array
     */
    public static function fromHtmlProvider(): array
    {
        return [
            'plain string, no attributes' => [
                'Plain string',
                [],
                'Plain string',
                'Plain string',
                [],
            ],
            'plain string, attributes' => [
                'Plain string',
                ['foo' => 'bar', 'bar' => 'baz'],
                'Plain string',
                'Plain string',
                ['foo' => 'bar', 'bar' => 'baz'],
            ],
            'HTML string, array attributes' => [
                '<strong>HTML</strong> string',
                ['foo' => ['bar', 'baz']],
                'HTML string',
                '<strong>HTML</strong> string',
                ['foo' => ['bar', 'baz']],
            ],
            'HTML string, reserved array attributes' => [
                '<strong>HTML</strong> string',
                ['__html' => ['bar', 'baz']],
                'HTML string',
                '<strong>HTML</strong> string',
                ['__html' => '<strong>HTML</strong> string'],
            ],
            'HTML string containing entities' => [
                '<i>Dungeons &amp; Dragons</i>',
                [],
                'Dungeons & Dragons',
                '<i>Dungeons &amp; Dragons</i>',
                ['__html' => '<i>Dungeons &amp; Dragons</i>'],
            ],
        ];
    }

    /**
     * Test the fromHtml static constructor
     *
     * @param string $html          Input HTML
     * @param array  $attrs         Additional attributes
     * @param string $expectedPlain Expected plain text result
     * @param string $expectedHtml  Expected HTML result
     * @param array  $expectedAttrs Expected attributes
     *
     * @return void
     *
     * @dataProvider fromHtmlProvider
     */
    public function testFromHtml(
        string $html,
        array $attrs,
        string $expectedPlain,
        string $expectedHtml,
        array $expectedAttrs
    ): void {
        $str = PropertyString::fromHtml($html, $attrs);
        $this->assertEquals($expectedPlain, (string)$str);
        $this->assertEquals($expectedHtml, $str->getHtml());
        foreach ($expectedAttrs as $key => $value) {
            $this->assertEquals($value, $str[$key]);
        }
    }
}
