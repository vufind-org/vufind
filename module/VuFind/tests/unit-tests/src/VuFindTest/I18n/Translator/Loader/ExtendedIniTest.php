<?php
/**
 * ExtendedIni translation loader Test Class
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
namespace VuFindTest\I18n\Translator\Loader;

use VuFind\I18n\Translator\Loader\ExtendedIniType;

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
class ExtendedIniTest extends \VuFindTest\Unit\TestCase
{
    /**
     * @var string
     */
    protected $path = __DIR__ . '/../../../../../../fixtures/language';

    public function setUp()
    {
        $this->path = realpath($this->path);
    }

    /**
     * Test directory precendence.
     *
     * @return void
     */
    public function testDirectoryPrecedence()
    {
        $loader = new ExtendedIniType();
        $loader->setDirs(["$this->path/overrides", "$this->path/base"]);
        $result = $loader->load('en');

        $this->assertArraySubset([
            'list' => [
                "$this->path/overrides/en.ini",
                "$this->path/base/en.ini"
            ]
        ], $result[ExtendedIniType::KEY_INFO]);

        $this->assertArraySubset([
            'blank_line' =>
                html_entity_decode('&#x200C;', ENT_NOQUOTES, 'UTF-8'),
            'test1'      => 'test one',
            'test2'      => 'test two - override',
        ], $result);
    }

    /**
     * Test file with a chain of parents.
     *
     * @return void
     */
    public function testExtendsDirective()
    {
        $loader = new ExtendedIniType();
        $loader->setDirs(["$this->path/base"]);
        $result = $loader->load('child2');

        $this->assertArraySubset([
            'list' => [
                "$this->path/base/child2.ini",
                "$this->path/base/child1.ini",
                "$this->path/base/fake.ini",
            ]
        ], $result[ExtendedIniType::KEY_INFO]);

        $this->assertArraySubset([
            'test1' => 'test 1',
            'test2' => 'test 2',
            'test3' => 'test three',
        ], $result);
    }

    /**
     * Test fallback chain.
     */
    public function testFallbacks()
    {
        $loader = new ExtendedIniType();
        $loader->setDirs(["$this->path/base"]);
        $loader->setFallbacks(['fb1' => 'fb2', '*' => 'fb3']);
        $result = $loader->load('fb1');
        $this->assertArraySubset([
            'key1' => 'val1', // fb1.ini
            'key2' => 'val2', // fb2.ini
            'key3' => 'val3', // fb3.ini
        ], $result);
    }

    /**
     * Test exception on circular load chain induced by extensions.
     *
     * @return void
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Circular chain of loaded language files.
     */
    public function testExceptionOnCircularExtensionChain()
    {
        $loader = new ExtendedIniType();
        $loader->setDirs(["$this->path/base"]);
        $loader->load('circ1');
    }

    /**
     * Test exception on circular load chain induced by fallbacks.
     *
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Circular chain of loaded language files.
     */
    public function testExceptionOnCircularFallbackChain()
    {
        $loader = new ExtendedIniType();
        $loader->setDirs(["$this->path/base"]);
        $loader->setFallbacks(['fb2' => 'fb3', '*' => 'fb2']);
        $loader->load('fb2');
    }

    /**
     * Test missing language file.
     *
     * @return void
     */
    public function testMissingLanguageFile()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("File 'xyz.ini' not found.");
        $loader = new ExtendedIniType();
        $loader->load('xyz');
    }
}
