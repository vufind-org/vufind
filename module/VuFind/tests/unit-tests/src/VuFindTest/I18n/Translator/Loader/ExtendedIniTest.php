<?php
/**
 * ExtendedIni translation loader Test Class
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
namespace VuFindTest\I18n\Translator\Loader;
use VuFind\I18n\Translator\Loader\ExtendedIni;

/**
 * ExtendedIni translation loader Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class ExtendedIniTest extends \VuFindTest\Unit\TestCase
{
    /**
     * Test translations.
     *
     * @return void
     */
    public function testTranslations()
    {
        $pathStack = [
            realpath(__DIR__ . '/../../../../../../fixtures/language/base'),
            realpath(__DIR__ . '/../../../../../../fixtures/language/overrides')
        ];
        $loader = new ExtendedIni($pathStack);
        $result = $loader->load('en', null);
        $this->assertEquals(
            [
                'blank_line' =>
                    html_entity_decode('&#x200C;', ENT_NOQUOTES, 'UTF-8'),
                'test1' => 'test one',
                'test2' => 'test two - override',
            ],
            (array)$result
        );
    }

    /**
     * Test fallback to a different language.
     *
     * @return void
     */
    public function testFallback()
    {
        $pathStack = [
            realpath(__DIR__ . '/../../../../../../fixtures/language/base'),
        ];
        $loader = new ExtendedIni($pathStack, 'en');
        $result = $loader->load('fake', null);
        $this->assertEquals(
            [
                'blank_line' =>
                    html_entity_decode('&#x200C;', ENT_NOQUOTES, 'UTF-8'),
                'test1' => 'test one',
                'test2' => 'test two',
                'test3' => 'test three',
            ],
            (array)$result
        );
    }

    /**
     * Test fallback to the same language.
     *
     * @return void
     */
    public function testFallbackToSelf()
    {
        $pathStack = [
            realpath(__DIR__ . '/../../../../../../fixtures/language/base'),
        ];
        $loader = new ExtendedIni($pathStack, 'fake');
        $result = $loader->load('fake', null);
        $this->assertEquals(
            [
                'test3' => 'test three',
            ],
            (array)$result
        );
    }

    /**
     * Test file with self as parent.
     *
     * @return void
     */
    public function testSelfAsParent()
    {
        $pathStack = [
            realpath(__DIR__ . '/../../../../../../fixtures/language/base'),
        ];
        $loader = new ExtendedIni($pathStack);
        $result = $loader->load('self-parent', null);
        $this->assertEquals(
            [
                '@parent_ini' => 'self-parent.ini',
                'string' => 'bad',
            ],
            (array)$result
        );
    }

    /**
     * Test file with a chain of parents.
     *
     * @return void
     */
    public function testParentChain()
    {
        $pathStack = [
            realpath(__DIR__ . '/../../../../../../fixtures/language/base'),
        ];
        $loader = new ExtendedIni($pathStack);
        $result = $loader->load('child2', null);
        $this->assertEquals(
            [
                '@parent_ini' => 'child1.ini',
                'test1' => 'test 1',
                'test2' => 'test 2',
                'test3' => 'test three',
            ],
            (array)$result
        );
    }

    /**
     * Test missing path stack.
     *
     * @return void
     *
     * @expectedException        Zend\I18n\Exception\InvalidArgumentException
     * @expectedExceptionMessage Ini file 'en.ini' not found
     */
    public function testMissingPathStack()
    {
        $loader = new ExtendedIni();
        $loader->load('en', null);
    }
}