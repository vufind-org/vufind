<?php

/**
 * Unit tests for LibGuides query builder
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2013.
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

namespace VuFindTest\Backend\LibGuides;

use PHPUnit\Framework\TestCase;
use VuFindSearch\Backend\LibGuides\QueryBuilder;
use VuFindSearch\Query\Query;
use VuFindSearch\Query\QueryGroup;

/**
 * Unit tests for LibGuides query builder
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class QueryBuilderTest extends TestCase
{
    /**
     * Test basic query parsing
     *
     * @return void
     */
    public function testBasic()
    {
        $q = new Query('query1');
        $qb = new QueryBuilder();
        $expected = ['query1'];
        $result = $qb->build($q)->get('search');
        $this->assertEquals($expected, $result);
    }

    /**
     * Test advanced query parsing (not currently supported)
     *
     * @return void
     */
    public function testAdvanced()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Advanced search not supported.');

        $qb = new QueryBuilder();
        $qb->build(new QueryGroup('AND', []));
    }
}
