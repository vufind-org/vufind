<?php

/**
 * Unit tests for facet information.
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

use VuFindSearch\Backend\Solr\Response\Json\Facets;
use PHPUnit_Framework_TestCase as TestCase;

/**
 * Unit tests for facet information.
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class FacetsTest extends TestCase
{
    /**
     * Test facets
     *
     * @return void
     */
    public function testFacets()
    {
        $facets = new Facets(
            [
                'facet_fields' => [
                    'field1' => [['a', 1], ['b', 2]]
                ],
                'facet_queries' => [],
            ]
        );
        $this->assertCount(0, $facets->getQueryFacets());
        $fieldFacets = $facets->getFieldFacets();
        $this->assertCount(2, $fieldFacets['field1']);
    }
}