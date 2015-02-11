<?php

/**
 * Unit tests for SOLR delete document class.
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
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
namespace VuFindTest\Backend\Solr\Document;

use VuFindSearch\Backend\Solr\Document\DeleteDocument;

use PHPUnit_Framework_TestCase;

/**
 * Unit tests for SOLR delete document class.
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class DeleteDocumentTest extends PHPUnit_Framework_TestCase
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
        $xml = $document->asXML();
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
        $document->addKeys(array('foo', 'bar'));
        $xml = $document->asXML();
        $this->assertXmlStringEqualsXmlString(
            '<delete><id>foo</id><id>bar</id></delete>',
            $xml
        );
    }
}