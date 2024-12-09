<?php

/**
 * Unit tests for Primo query builder
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

namespace VuFindTest\Backend\Primo;

use PHPUnit\Framework\TestCase;
use VuFindSearch\Backend\Primo\QueryBuilder;
use VuFindSearch\Query\Query;
use VuFindSearch\Query\QueryGroup;

/**
 * Unit tests for Primo query builder
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
        $q = new Query('query1', 'handler1');
        $qb = new QueryBuilder();
        $expected = [['lookfor' => 'query1', 'index' => 'handler1']];
        $result = $qb->build($q)->get('query');
        $this->assertEquals($expected, $result);
    }

    /**
     * Test advanced query parsing
     *
     * @return void
     */
    public function testAdvanced()
    {
        // The query builder expects a very strict structure -- a single query group
        // inside another query group containing a collection of queries.
        $q1 = new Query('query1', 'handler1', 'OR');
        $q2 = new Query('query2', 'handler2', 'AND');
        $qsub = new QueryGroup('AND', [$q1, $q2]);
        $q = new QueryGroup('AND', [$qsub]);

        $qb = new QueryBuilder();
        $expected = [
            ['lookfor' => 'query1', 'index' => 'handler1', 'op' => 'OR'],
            ['lookfor' => 'query2', 'index' => 'handler2', 'op' => 'AND'],
        ];
        $result = $qb->build($q)->get('query');
        $this->assertEquals($expected, $result);
    }
}
