<?php

/**
 * Unit tests for SOLR update document class.
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

use VuFindSearch\Backend\Solr\Document\UpdateDocument;

use PHPUnit_Framework_TestCase;

/**
 * Unit tests for SOLR update document class.
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class UpdateDocumentTest extends PHPUnit_Framework_TestCase
{
    /**
     * Test creation of XML document.
     *
     * @return void
     */
    public function testAsXML()
    {
        $record = $this->getMockForAbstractClass('VuFindSearch\Backend\Solr\Record\SerializableRecordInterface');
        $record->expects($this->once())
            ->method('getFields')
            ->will($this->returnValue(['id' => 'ID', 'field' => 'FIELD']));
        $document = new UpdateDocument();
        $document->addRecord($record, ['boost' => '2.0']);
        $xml = $document->asXML();
        $this->assertXmlStringEqualsXmlString(
            '<add><doc boost="2.0"><field name="id">ID</field><field name="field">FIELD</field></doc></add>',
            $xml
        );
    }
}