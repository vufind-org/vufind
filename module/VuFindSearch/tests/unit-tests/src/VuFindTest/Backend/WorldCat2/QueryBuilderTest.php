<?php

/**
 * Unit tests for WorldCat2 query builder
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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

namespace VuFindTest\Backend\WorldCat2;

use PHPUnit\Framework\TestCase;
use VuFindSearch\Backend\WorldCat2\QueryBuilder;
use VuFindSearch\Query\Query;
use VuFindSearch\Query\QueryGroup;

/**
 * Unit tests for WorldCat2 query builder
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
     * Test advanced query building.
     *
     * @return void
     */
    public function testAdvancedBuild(): void
    {
        $part1 = new Query('john smith', 'au');
        $part2 = new Query('bananas', 'ti');
        $group1 = new QueryGroup('OR', [$part1, $part2]);
        $group2 = new QueryGroup('NOT', [new Query('nonsense')]);
        $top = new QueryGroup('AND', [$group1, $group2]);
        $qb = new QueryBuilder();
        $response = $qb->build($top);
        $processedQ = $response->get('q');
        $this->assertEquals('(au:(john smith) OR ti:(bananas)) NOT ((kw:(nonsense)))', $processedQ[0]);
    }

    /**
     * Test the "exclude code" feature.
     *
     * @return void
     */
    public function testExclude(): void
    {
        $qb = new QueryBuilder('TEST');
        $q = new Query('test');
        $response = $qb->build($q);
        $processedQ = $response->get('q');
        $this->assertEquals('(kw:(test)) NOT li:TEST', $processedQ[0]);
    }
}
