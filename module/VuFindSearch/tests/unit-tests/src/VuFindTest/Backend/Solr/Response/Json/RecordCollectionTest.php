<?php

/**
 * Unit tests for simple JSON-based record collection.
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

namespace VuFindTest\Backend\Solr\Json\Response;

use VuFindSearch\Backend\Solr\Response\Json\RecordCollection;
use PHPUnit_Framework_TestCase;

/**
 * Unit tests for simple JSON-based record collection.
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class RecordCollectionTest extends PHPUnit_Framework_TestCase
{
    /**
     * Test that the object returns appropriate defaults for missing elements.
     *
     * @return void
     */
    public function testDefaults()
    {
        $coll = new RecordCollection(array());
        $this->assertEquals(
            'VuFindSearch\Backend\Solr\Response\Json\Spellcheck',
            get_class($coll->getSpellcheck())
        );
        $this->assertEquals(0, $coll->getTotal());
        $this->assertEquals(
            'VuFindSearch\Backend\Solr\Response\Json\Facets',
            get_class($coll->getFacets())
        );
        $this->assertEquals(array(), $coll->getGroups());
        $this->assertEquals(array(), $coll->getHighlighting());
        $this->assertEquals(0, $coll->getOffset());
    }
}