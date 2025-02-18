<?php

/**
 * ExtendedIni translation loader Test Class
 *
 * PHP version 8
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

namespace VuFindTest\I18n\Translator\Loader;

use VuFind\I18n\Translator\Loader\ExtendedIni;

/**
 * ExtendedIni translation loader Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ExtendedIniTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    /**
     * Test translations.
     *
     * @return void
     */
    public function testTranslations(): void
    {
        $pathStack = [
            realpath($this->getFixtureDir() . 'language/base'),
            realpath($this->getFixtureDir() . 'language/overrides'),
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
    public function testFallback(): void
    {
        $pathStack = [
            realpath($this->getFixtureDir() . 'language/base'),
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
    public function testFallbackToSelf(): void
    {
        $pathStack = [
            realpath($this->getFixtureDir() . 'language/base'),
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
    public function testSelfAsParent(): void
    {
        $pathStack = [
            realpath($this->getFixtureDir() . 'language/base'),
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
    public function testParentChain(): void
    {
        $pathStack = [
            realpath($this->getFixtureDir() . 'language/base'),
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
     */
    public function testMissingPathStack(): void
    {
        $this->expectException(\Laminas\I18n\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Ini file \'en.ini\' not found');

        $loader = new ExtendedIni();
        $loader->load('en', null);
    }

    /**
     * Test alias behavior in default domain.
     *
     * @return void
     */
    public function testAliasingInDefaultDomain(): void
    {
        $pathStack = [
            realpath($this->getFixtureDir() . 'language/aliases'),
        ];
        $loader = new ExtendedIni($pathStack, 'en');
        $result = $loader->load('en', null);
        $this->assertEquals(
            [
                'bar' => 'Translation',
                'baz' => 'Domain Translation',
                'foo' => 'Translation',
                'xyzzy' => 'Domain Translation',
                'foofoo' => 'Translation',
            ],
            (array)$result
        );
    }

    /**
     * Test alias behavior in non-default domain.
     *
     * @return void
     */
    public function testAliasingInNonDefaultDomain(): void
    {
        $pathStack = [
            realpath($this->getFixtureDir() . 'language/aliases'),
        ];
        $loader = new ExtendedIni($pathStack, 'en');
        $result = $loader->load('en', 'Domain');
        $this->assertEquals(
            [
                'bar' => 'Domain Translation',
                'foofoo' => 'Translation',
            ],
            (array)$result
        );
    }

    /**
     * Test circular alias infinite loop prevention.
     *
     * @return void
     */
    public function testCircularAliasSafety(): void
    {
        $pathStack = [
            realpath($this->getFixtureDir() . 'language/circularaliases'),
        ];
        $loader = new ExtendedIni($pathStack, 'en');
        $this->expectExceptionMessage('Circular alias detected resolving Domain::baz');
        $loader->load('en', null);
    }

    /**
     * Test inheriting aliases from a parent file.
     *
     * @return void
     */
    public function testInheritedAliasing(): void
    {
        $pathStack = [
            realpath($this->getFixtureDir() . 'language/aliases'),
        ];
        $loader = new ExtendedIni($pathStack, 'en');
        $result = $loader->load('en-gb', null);
        $this->assertEquals(
            [
                'bar' => 'Translation',
                'baz' => 'Domain Translation',
                'foo' => 'Translation',
                'xyzzy' => 'Child Overriding Alias',
                '@parent_ini' => 'en.ini',
                'foofoo' => 'Translation',
            ],
            (array)$result
        );
    }

    /**
     * Test that alias behavior can be disabled.
     *
     * @return void
     */
    public function testDisabledAliasing(): void
    {
        $pathStack = [
            realpath($this->getFixtureDir() . 'language/aliases'),
        ];
        $loader = new ExtendedIni($pathStack, 'en');
        $loader->disableAliases();
        $result = $loader->load('en', null);
        $this->assertEquals(
            [
                'bar' => 'Translation',
            ],
            (array)$result
        );
        $result = $loader->load('en-gb', null);
        $this->assertEquals(
            [
                'bar' => 'Translation',
                'xyzzy' => 'Child Overriding Alias',
                '@parent_ini' => 'en.ini',
            ],
            (array)$result
        );
    }
}
