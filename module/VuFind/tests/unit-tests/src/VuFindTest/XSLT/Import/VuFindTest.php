<?php
/**
 * XSLT helper tests.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2019.
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
namespace VuFindTest\XSLT\Import;

use VuFind\XSLT\Import\VuFind;

/**
 * XSLT helper tests.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class VuFindTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Support method -- set up a mock container for testing the class.
     *
     * @return \VuFindTest\Container\MockContainer
     */
    protected function getMockContainer()
    {
        $container = new \VuFindTest\Container\MockContainer($this);
        $tableManager = new \VuFindTest\Container\MockDbTablePluginManager($this);
        $tableManager->set(
            'ChangeTracker',
            $tableManager->get(\VuFind\Db\Table\ChangeTracker::class)
        );
        $container->set(\VuFind\Db\Table\PluginManager::class, $tableManager);
        return $container;
    }

    /**
     * Test the getChangeTracker helper.
     *
     * @return void
     */
    public function testGetChangeTracker()
    {
        VuFind::setServiceLocator($this->getMockContainer());
        $this->assertTrue(
            VuFind::getChangeTracker() instanceof \VuFind\Db\Table\ChangeTracker
        );
    }

    /**
     * Test the getConfig helper.
     *
     * @return void
     */
    public function testGetConfig()
    {
        $container = $this->getMockContainer();
        $container->set(
            \VuFind\Config\PathResolver::class,
            new \VuFind\Config\PathResolver(APPLICATION_PATH, [])
        );
        $config = new \Laminas\Config\Config([]);
        $container->get(\VuFind\Config\PluginManager::class)->expects($this->once())
            ->method('get')->with('config')->will($this->returnValue($config));
        VuFind::setServiceLocator($container);
        $this->assertEquals($config, VuFind::getConfig());
    }

    /**
     * Test the harvestTextFile helper.
     *
     * @return void
     */
    public function testHarvestTextFile()
    {
        $this->assertEquals(
            file_get_contents(__FILE__),
            VuFind::harvestTextFile('file://' . __FILE__)
        );
    }

    /**
     * Test the stripBadChars helper.
     *
     * @return void
     */
    public function testStripBadChars()
    {
        $this->assertEquals('f oo', VuFind::stripBadChars('f' . chr(8) . 'oo'));
    }

    /**
     * Test the mapString helper.
     *
     * @return void
     */
    public function testMapString()
    {
        $this->assertEquals(
            'CD',
            VuFind::mapString('SoundDisc', 'format_map.properties')
        );
    }

    /**
     * Test the stripArticles helper.
     *
     * @return void
     */
    public function testStripArticles()
    {
        $this->assertEquals('title', VuFind::stripArticles('The Title'));
        $this->assertEquals('title', VuFind::stripArticles('A Title'));
        $this->assertEquals('odd title', VuFind::stripArticles('An Odd Title'));
    }

    /**
     * Test the xmlAsText helper.
     *
     * @return void
     */
    public function testXmlAsText()
    {
        $doc = new \DOMDocument('1.0');
        $node = new \DOMElement('bar', 'foo');
        $doc->appendChild($node);
        $expected = '<?xml version="1.0"?>' . "\n<bar>foo</bar>\n";
        $this->assertEquals($expected, VuFind::xmlAsText([$node]));
    }

    /**
     * Test the removeTagAndReturnXMLasText helper.
     *
     * @return void
     */
    public function testRemoveTagAndReturnXMLasText()
    {
        $doc = new \DOMDocument('1.0');
        $node = new \DOMElement('bar', 'foo');
        $doc->appendChild($node);
        $node->appendChild(new \DOMElement('xyzzy', 'baz'));
        $expected = '<?xml version="1.0"?>' . "\n<bar>foo</bar>\n";
        $this->assertEquals(
            $expected,
            VuFind::removeTagAndReturnXMLasText([$node], 'xyzzy')
        );
    }

    /**
     * Test the explode helper.
     *
     * @return void
     */
    public function testExplode()
    {
        $expected = '<?xml version="1.0" encoding="utf-8"?>'
            . "\n<part>a</part>\n<part>b</part>\n";
        $this->assertEquals(
            $expected,
            simplexml_import_dom(VuFind::explode(',', 'a,b'))->asXml()
        );
    }

    /**
     * Test the implode helper.
     *
     * @return void
     */
    public function testImplode()
    {
        $domify = function ($input): \DOMElement {
            return new \DOMElement('foo', $input);
        };
        $this->assertEquals(
            'a.b.c',
            VuFind::implode('.', array_map($domify, ['a', 'b', 'c']))
        );
    }

    /**
     * Test the extractBestDateOrRange helper.
     *
     * @return void
     */
    public function testExtractBestDateOrRange()
    {
        $data = [
            '1990' => ['foo', 'bar', '1990'],
            '1990-1991' => ['foo', '1990-1991', '1992'],
            'foo' => ['foo', 'bar', 'baz'],
        ];
        $domify = function ($input): \DOMElement {
            return new \DOMElement('foo', $input);
        };
        foreach ($data as $output => $input) {
            $this->assertEquals(
                $output,
                VuFind::extractBestDateOrRange(
                    array_map($domify, $input)
                )
            );
        }
    }

    /**
     * Test the extractEarliestYear helper.
     *
     * @return void
     */
    public function testExtractEarliestYear()
    {
        $data = [
            'October 9, 1990 (approx)' => '1990',
            'the year 0' => '0',
            'published 1927-1929' => '1927',
            '2005-1999' => '1999',
            'there is no year to be found here' => '',
        ];
        foreach ($data as $input => $output) {
            $this->assertEquals(
                $output,
                VuFind::extractEarliestYear(
                    [new \DOMElement('foo', $input)]
                )
            );
        }
    }

    /**
     * DataProvider for name-related tests
     *
     * @return array
     */
    public function nameProvider(): array
    {
        return [
            'single name' => ['foo', 'foo'],
            'two-part name' => ['foo bar', 'bar, foo'],
            'long name' => ['foo bar baz xyzzy', 'xyzzy, foo bar baz'],
        ];
    }

    /**
     * DataProvider for testIsInvertedName().
     *
     * @return array
     */
    public function isInvertedNameProvider(): array
    {
        return [
            ['foo bar', false],
            ['foo bar, jr.', false],
            ['bar, foo', true],
            ['bar, foo, jr.', true],
        ];
    }

    /**
     * Test the isInvertedName helper.
     *
     * @param string $input  Input to test
     * @param bool   $output Expected output of test
     *
     * @return void
     *
     * @dataProvider isInvertedNameProvider
     */
    public function testIsInvertedName(string $input, bool $output): void
    {
        $this->assertEquals($output, VuFind::isInvertedName($input));
    }

    /**
     * Test the invertName helper.
     *
     * @param string $input  Input to test
     * @param string $output Expected output of test
     *
     * @return void
     *
     * @dataProvider nameProvider
     */
    public function testInvertName(string $input, string $output): void
    {
        $this->assertEquals($output, VuFind::invertName($input));
    }

    /**
     * Test the invertNames helper.
     *
     * @return void
     */
    public function testInvertNames(): void
    {
        $input = [];
        $output = new \DOMDocument('1.0', 'utf-8');
        // Leverage the data provider to create an array of input elements and
        // an expected output document to compare against real output:
        foreach ($this->nameProvider() as $current) {
            $input[] = new \DOMElement('name', $current[0]);
            $output->appendChild(new \DOMElement('name', $current[1]));
        }
        $this->assertEquals(
            $output->saveXML(),
            VuFind::invertNames($input)->saveXML()
        );
    }
}
