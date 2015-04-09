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
     * Test translation with a loaded translator
     *
     * @return void
     */
    public function testTranslateWithTranslator()
    {
        $translate = new Translate();
        $translator = $this->getMock('Zend\I18n\Translator\TranslatorInterface');
        $translator->expects($this->once())->method('translate')
            ->with($this->equalTo('foo'))->will($this->returnValue('%%token%%'));
        $translate->setTranslator($translator);

        // Simple case that tests default values and tokens in a single pass:
        $this->assertEquals('baz', $translate->__invoke(
            'foo', ['%%token%%' => 'baz'], 'failure')
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
}