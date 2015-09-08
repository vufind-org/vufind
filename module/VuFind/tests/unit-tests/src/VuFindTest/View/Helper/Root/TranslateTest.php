<?php
/**
 * Translate view helper Test Class (and by extension, the TranslatorAwareTrait)
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
namespace VuFindTest\View\Helper\Root;
use VuFind\View\Helper\Root\Translate;
use VuFind\I18n\TranslatableString;

/**
 * Translate view helper Test Class (and by extension, the TranslatorAwareTrait)
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class TranslateTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test translation without a loaded translator
     *
     * @return void
     */
    public function testTranslateWithoutTranslator()
    {
        $translate = new Translate();
        // Simple case that tests default values and tokens in a single pass:
        $this->assertEquals('baz', $translate->__invoke(
            'foo', ['%%token%%' => 'baz'], '%%token%%')
        );
    }

    /**
     * Test invalid translation array
     *
     * @return void
     *
     * @expectedException        Exception
     * @expectedExceptionMessage Unexpected value sent to translator!
     */
    public function testTranslateWithEmptyArray()
    {
        $translate = new Translate();
        $translate->__invoke([]);
    }

    /**
     * Test invalid translation array
     *
     * @return void
     *
     * @expectedException        Exception
     * @expectedExceptionMessage Unexpected value sent to translator!
     */
    public function testTranslateWithOverfilledArray()
    {
        $translate = new Translate();
        $translate->__invoke([1, 2, 3]);
    }

    /**
     * Test invalid translation string
     *
     * @return void
     *
     * @expectedException        Exception
     * @expectedExceptionMessage Unexpected value sent to translator!
     */
    public function testTranslateWithDoubleTextDomainArray()
    {
        $translate = new Translate();
        $translate->__invoke('a::b::c');
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
        $this->assertEquals('baz', $translate->__invoke(
            'foo', ['%%token%%' => 'baz'], 'failure')
        );
        // Test namespace syntax:
        $this->assertEquals('baz', $translate->__invoke(
            'default::foo', ['%%token%%' => 'baz'], 'failure')
        );
        // Test array syntax:
        $this->assertEquals('baz', $translate->__invoke(
            ['foo'], ['%%token%%' => 'baz'], 'failure')
        );
        $this->assertEquals('baz', $translate->__invoke(
            [null, 'foo'], ['%%token%%' => 'baz'], 'failure')
        );
        $this->assertEquals('baz', $translate->__invoke(
            ['default', 'foo'], ['%%token%%' => 'baz'], 'failure')
        );
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
            $this->getMockTranslator(['default' => ['foo' => '%%token%%']])
        );

        // Test a TranslatableString with a translation.
        $str1 = new TranslatableString('foo', 'bar');
        // Simple case that tests default values and tokens in a single pass:
        $this->assertEquals('baz', $translate->__invoke(
            $str1, ['%%token%%' => 'baz'], 'failure')
        );

        // Test a TranslatableString with a fallback.
        $str2 = new TranslatableString('bar', 'foo');
        // Simple case that tests default values and tokens in a single pass:
        $this->assertEquals('baz', $translate->__invoke(
            $str2, ['%%token%%' => 'baz'], 'failure')
        );

        // Test a TranslatableString with no fallback.
        $str3 = new TranslatableString('xyzzy', 'bar');
        // Simple case that tests default values and tokens in a single pass:
        $this->assertEquals('failure', $translate->__invoke(
            $str3, ['%%token%%' => 'baz'], 'failure')
        );
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
        $this->assertEquals('str1', $translate->__invoke($str1));
        // Secondary string translatable
        $str2 = new TranslatableString('d1::f2', 'd2::f2');
        $this->assertEquals('str2', $translate->__invoke($str2));
        // No string translatable
        $str3 = new TranslatableString('d1::f2', 'd2::f1');
        $this->assertEquals('failure', $translate->__invoke($str3, [], 'failure'));
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
        $this->assertEquals('baz', $translate->__invoke(
            'zap::foo', ['%%token%%' => 'baz'], 'failure')
        );

        // This one will use incoming string -- TextDomain undefined
        $this->assertEquals('failure', $translate->__invoke(
            'undefined::foo', ['%%token%%' => 'baz'], 'failure')
        );
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
        $translator = $this->getMock('Zend\I18n\Translator\Translator');
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
        $translator = $this->getMock('Zend\I18n\Translator\TranslatorInterface');
        $translate->setTranslator($translator);
        $this->assertEquals($translator, $translate->getTranslator());
    }

    /**
     * Get mock translator.
     *
     * @param array $translations Key => value translation map.
     *
     * @return \Zend\I18n\Translator\TranslatorInterface
     */
    protected function getMockTranslator($translations)
    {
        $callback = function ($str, $domain) use ($translations) {
            return isset($translations[$domain][$str])
                ? $translations[$domain][$str] : $str;
        };
        $translator = $this->getMock('Zend\I18n\Translator\TranslatorInterface');
        $translator->expects($this->any())->method('translate')
            ->will($this->returnCallback($callback));
        return $translator;
    }
}