<?php

/**
 * Unit tests for SOLR delete document class.
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
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindTest\Backend\Solr\Document;

use PHPUnit\Framework\TestCase;
use VuFindSearch\Backend\Solr\Document\DeleteDocument;

/**
 * Unit tests for SOLR delete document class.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class DeleteDocumentTest extends TestCase
{
    /**
     * Test creation of XML document.
     *
     * @return void
     */
    public function testAsXML()
    {
        $document = new DeleteDocument();
        $document->addKey('foobar');
        $document->addQuery('timestamp:[* TO NOW-12HOUR]');
        $this->assertEquals(
            'text/xml; charset=UTF-8',
            $document->getContentType()
        );
        $xml = $document->getContent();
        $this->assertXmlStringEqualsXmlString(
            '<delete><id>foobar</id><query>timestamp:[* TO NOW-12HOUR]</query></delete>',
            $xml
        );
    }

    /**
     * Test creation of XML document with multiple keys.
     *
     * @return void
     */
    public function testAsXMLMultiKey()
    {
        $document = new DeleteDocument();
        $document->addKeys(['foo', 'bar']);
        $xml = $document->getContent();
        $this->assertXmlStringEqualsXmlString(
            '<delete><id>foo</id><id>bar</id></delete>',
            $xml
        );
    }
}
