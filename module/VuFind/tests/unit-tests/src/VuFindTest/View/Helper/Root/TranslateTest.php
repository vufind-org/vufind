<?php

/**
 * Translate view helper Test Class (and by extension, the TranslatorAwareTrait)
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

namespace VuFindTest\View\Helper\Root;

use VuFind\I18n\TranslatableString;
use VuFind\View\Helper\Root\Translate;
use VuFindTest\Feature\TranslatorTrait;

/**
 * Translate view helper Test Class (and by extension, the TranslatorAwareTrait)
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class TranslateTest extends \PHPUnit\Framework\TestCase
{
    use TranslatorTrait;

    /**
     * Test translation without a loaded translator
     *
     * @return void
     */
    public function testTranslateWithoutTranslator()
    {
        $translate = new Translate();
        // Simple case that tests default values and tokens in a single pass:
        $this->assertEquals(
            'baz',
            $translate(
                'foo',
                ['%%token%%' => 'baz'],
                '%%token%%'
            )
        );
    }

    /**
     * Test invalid translation array
     *
     * @return void
     */
    public function testTranslateWithEmptyArray()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unexpected value sent to translator!');

        $translate = new Translate();
        $translate([]);
    }

    /**
     * Test invalid translation array
     *
     * @return void
     */
    public function testTranslateWithOverfilledArray()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unexpected value sent to translator!');

        $translate = new Translate();
        $translate([1, 2, 3]);
    }

    /**
     * Test translation with a loaded translator
     *
     * @return void
     */
    public function testTranslateWithTranslator()
    {
        $translate = new Translate();
        $translate->setTranslator(
            $this->getMockTranslator(['default' => ['foo' => '%%token%%']])
        );

        // Simple case that tests default values and tokens in a single pass:
        $this->assertEquals(
            'baz',
            $translate(
                'foo',
                ['%%token%%' => 'baz'],
                'failure'
            )
        );
        // Test namespace syntax:
        $this->assertEquals(
            'baz',
            $translate(
                'default::foo',
                ['%%token%%' => 'baz'],
                'failure'
            )
        );
        // Test array syntax:
        $this->assertEquals(
            'baz',
            $translate(
                ['foo'],
                ['%%token%%' => 'baz'],
                'failure'
            )
        );
        $this->assertEquals(
            'baz',
            $translate(
                [null, 'foo'],
                ['%%token%%' => 'baz'],
                'failure'
            )
        );
        $this->assertEquals(
            'baz',
            $translate(
                ['default', 'foo'],
                ['%%token%%' => 'baz'],
                'failure'
            )
        );
    }

    /**
     * Test TranslatableString default values.
     *
     * @return void
     */
    public function testTranslateTranslatableStringDefaultValues()
    {
        $translate = new Translate();
        $translate->setTranslator(
            $this->getMockTranslator(['default' => []])
        );

        $s = new TranslatableString('foo', 'bar');
        $this->assertEquals('bar', $translate($s));

        $s = new TranslatableString('foo', new TranslatableString('bar', 'baz'));
        $this->assertEquals('baz', $translate($s));
    }

    /**
     * Test translation of a TranslatableString object with a loaded translator
     *
     * @return void
     */
    public function testTranslateTranslatableStringWithTranslator()
    {
        $translate = new Translate();
        $translate->setTranslator(
            $this->getMockTranslator(
                [
                    'default' => ['foo' => '%%token%%'],
                    'other' => ['foo' => 'Foo', 'bar' => 'Bar']
                ]
            )
        );

        // Test a TranslatableString with a translation.
        $str1 = new TranslatableString('foo', 'bar');
        // Simple case that tests default values and tokens in a single pass:
        $this->assertEquals(
            'baz',
            $translate(
                $str1,
                ['%%token%%' => 'baz'],
                'failure'
            )
        );

        // Test a TranslatableString with a fallback.
        $str2 = new TranslatableString('bar', 'foo');
        // Simple case that tests default values and tokens in a single pass:
        $this->assertEquals(
            'baz',
            $translate(
                $str2,
                ['%%token%%' => 'baz'],
                'failure'
            )
        );

        // Test a TranslatableString with no fallback.
        $str3 = new TranslatableString('xyzzy', 'bar');
        // Simple case that tests default values and tokens in a single pass:
        $this->assertEquals(
            'failure',
            $translate(
                $str3,
                ['%%token%%' => 'baz'],
                'failure'
            )
        );

        // Test a TranslatableString with another TranslatableString as a fallback.
        $str4 = new TranslatableString(
            'xyzzy',
            new TranslatableString('bar', 'baz')
        );
        $this->assertEquals('baz', $translate($str4));
        $str5 = new TranslatableString(
            'xyzzy',
            new TranslatableString('foo', 'baz')
        );
        $this->assertEquals('%%token%%', $translate($str5));

        // Test a TranslatableString with translation forbidden
        $str6 = new TranslatableString('foo', 'bar', false);
        $this->assertEquals('bar', $translate($str6));
        $str7 = new TranslatableString('foo', '', false);
        $this->assertEquals('', $translate($str7));
    }

    /**
     * Test translation of a TranslatableString object using text domains with a
     * loaded translator
     *
     * @return void
     */
    public function testTranslateTranslatableStringAndTextDomainsWithTranslator()
    {
        $translate = new Translate();
        $translate->setTranslator(
            $this->getMockTranslator(
                [
                    'd1' => ['f1' => 'str1'],
                    'd2' => ['f2' => 'str2'],
                ]
            )
        );

        // Primary string translatable
        $str1 = new TranslatableString('d1::f1', 'd2::f2');
        $this->assertEquals('str1', $translate($str1));
        // Secondary string translatable
        $str2 = new TranslatableString('d1::f2', 'd2::f2');
        $this->assertEquals('str2', $translate($str2));
        // No string translatable
        $str3 = new TranslatableString('d1::f2', 'd2::f1');
        $this->assertEquals('failure', $translate($str3, [], 'failure'));

        // Secondary string a translatable TranslatableString
        $str4 = new TranslatableString(
            'd1::f2',
            new TranslatableString('d2::f2', 'd3::f3')
        );
        $this->assertEquals('str2', $translate($str4));
        // Secondary string a TranslatableString with no translation
        $str5 = new TranslatableString(
            'd1::f2',
            new TranslatableString('d2::f1', 'failure')
        );
        $this->assertEquals('failure', $translate($str5));
        // Secondary string a non-translatable TranslatableString
        $str6 = new TranslatableString(
            'd1::f2',
            new TranslatableString('d2::f2', 'failure', false)
        );
        $this->assertEquals('failure', $translate($str6));

        // Three levels of TranslatableString with the last one translatable
        $str7 = new TranslatableString(
            'd1::f2',
            new TranslatableString(
                'd3::f3',
                new TranslatableString('d2::f2', 'failure')
            )
        );
        $this->assertEquals('str2', $translate($str7));

        // Three levels of TranslatableString with no translation
        $str8 = new TranslatableString(
            'd1::f2',
            new TranslatableString(
                'd3::f3',
                new TranslatableString('d3::f2', 'failure')
            )
        );
        $this->assertEquals('failure', $translate($str8));
    }

    /**
     * Test translation with a loaded translator and a text domain
     *
     * @return void
     */
    public function testTranslateTextDomainWithTranslator()
    {
        $translate = new Translate();
        $translate->setTranslator(
            $this->getMockTranslator(['zap' => ['foo' => '%%token%%']])
        );

        // This one will work -- TextDomain defined above
        $this->assertEquals(
            'baz',
            $translate(
                'zap::foo',
                ['%%token%%' => 'baz'],
                'failure'
            )
        );

        // This one will use incoming string -- TextDomain undefined
        $this->assertEquals(
            'failure',
            $translate(
                'undefined::foo',
                ['%%token%%' => 'baz'],
                'failure'
            )
        );
    }

    /**
     * Test nested translation with potential text domain conflict
     *
     * @return void
     */
    public function testTranslateNestedTextDomainWithConflict()
    {
        $translations = [
            'd1' => ['foo' => 'bar', 'failure' => 'success'],
            'd2' => ['baz' => 'xyzzy', 'failure' => 'mediocrity'],
        ];
        $translate = new Translate();
        $translate->setTranslator(
            $this->getMockTranslator($translations)
        );
        $str = new TranslatableString(
            'd1::baz',
            new TranslatableString('d2::foo', 'failure')
        );
        $this->assertEquals('failure', $translate($str));
    }

    /**
     * Test locale retrieval without a loaded translator
     *
     * @return void
     */
    public function testLocaleWithoutTranslator()
    {
        $translate = new Translate();
        $this->assertEquals('foo', $translate->getTranslatorLocale('foo'));
    }

    /**
     * Test locale retrieval without a loaded translator
     *
     * @return void
     */
    public function testLocaleWithTranslator()
    {
        $translate = new Translate();
        $translator = $this->createMock(\Laminas\I18n\Translator\Translator::class);
        $translator->expects($this->once())->method('getLocale')
            ->will($this->returnValue('foo'));
        $translate->setTranslator($translator);
        $this->assertEquals('foo', $translate->getTranslatorLocale());
    }

    /**
     * Test translator retrieval.
     *
     * @return void
     */
    public function testGetTranslator()
    {
        $translate = new Translate();
        $translator = $this->createMock(\Laminas\I18n\Translator\TranslatorInterface::class);
        $translate->setTranslator($translator);
        $this->assertEquals($translator, $translate->getTranslator());
    }
}
