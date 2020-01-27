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
class VuFindTest extends \VuFindTest\Unit\DbTestCase
{
    /**
     * Test the getChangeTracker helper.
     *
     * @return void
     */
    public function testGetChangeTracker()
    {
        VuFind::setServiceLocator($this->getServiceManager());
        $this->assertEquals(
            \VuFind\Db\Table\ChangeTracker::class,
            get_class(VuFind::getChangeTracker())
        );
    }

    /**
     * Test the getConfig helper.
     *
     * @return void
     */
    public function testGetConfig()
    {
        VuFind::setServiceLocator($this->getServiceManager());
        $this->assertEquals(
            \Zend\Config\Config::class, get_class(VuFind::getConfig())
        );
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
            'CD', VuFind::mapString('SoundDisc', 'format_map.properties')
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
            $expected, VuFind::removeTagAndReturnXMLasText([$node], 'xyzzy')
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
            $expected, simplexml_import_dom(VuFind::explode(',', 'a,b'))->asXml()
        );
    }
}
