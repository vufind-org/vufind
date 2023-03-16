<?php

/**
 * Unit tests for SOLR raw CSV document class.
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
use VuFindSearch\Backend\Solr\Document\RawCSVDocument;

/**
 * Unit tests for SOLR raw CSV document class.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class RawCSVDocumentTest extends TestCase
{
    /**
     * Test creation of CSV document.
     *
     * @return void
     */
    public function testBasicBehavior()
    {
        $document = new RawCSVDocument('a,b,c');
        $this->assertEquals(
            'text/csv',
            $document->getContentType()
        );
        $this->assertEquals('a,b,c', $document->getContent());
    }
}
