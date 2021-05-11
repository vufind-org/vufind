<?php

/**
 * Unit tests for SOLR raw JSON document class.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2021.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
namespace VuFindTest\Backend\Solr\Document;

use PHPUnit\Framework\TestCase;

use VuFindSearch\Backend\Solr\Document\RawJSONDocument;

/**
 * Unit tests for SOLR raw JSON document class.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class RawJSONDocumentTest extends TestCase
{
    /**
     * Test creation of JSON document.
     *
     * @return void
     */
    public function testAsJSON()
    {
        $document = new RawJSONDocument('{}');
        $this->assertEquals('{}', $document->asJSON());
    }

    /**
     * Confirm that CSV is unsupported.
     *
     * @return void
     */
    public function testAsCSV()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('CSV not supported');
        $document = new RawJSONDocument('{}');
        $document->asCSV();
    }

    /**
     * Confirm that XML is unsupported.
     *
     * @return void
     */
    public function testAsXML()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('XML not supported');
        $document = new RawJSONDocument('{}');
        $document->asXML();
    }
}
