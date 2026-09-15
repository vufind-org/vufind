<?php

/**
 * Unit tests for ProQuestFSG query builder
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Search
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindTest\Backend\ProQuestFSG;

use PHPUnit\Framework\TestCase;
use VuFindSearch\Backend\ProQuestFSG\QueryBuilder;

/**
 * Unit tests for EDS query builder
 *
 * @category VuFind
 * @package  Search
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class QueryBuilderTest extends TestCase
{
    /**
     * Data provider describing simple queries.
     *
     * @return array[]
     */
    public static function simpleQueryBuilderProvider(): array
    {
        return [
            // Different handler
            ['(title = "ice hockey")', 'ice hockey', 'title'],

            // Quotes to 'adj' phrases
            ['(cql.serverChoice = "ice hockey")', 'ice hockey'],
            ['(cql.serverChoice adj "ice hockey")', '"ice hockey"'],

            // AND to strip when not in a phrase
            ['(cql.serverChoice = "ice hockey")', 'ice AND hockey'],
            ['(cql.serverChoice = "ice hockey")', 'ice and hockey'],
            ['(cql.serverChoice adj "ice AND hockey")', '"ice AND hockey"'],

            // OR to convert to the 'any' operator
            ['(cql.serverChoice any "ice hockey")', 'ice OR hockey'],

            // Booleans between phrases
            ['(cql.serverChoice adj "ice hockey") AND (cql.serverChoice = "skate")', '"ice hockey" and skate'],
            ['(cql.serverChoice adj "ice hockey") OR (cql.serverChoice any "skate")', '"ice hockey" or skate'],
        ];
    }

    /**
     * Test simple queries.
     *
     * @param string $expected Expected query string output
     * @param string $input    Query input
     * @param string $handler  Query handler
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('simpleQueryBuilderProvider')]
    public function testSimpleQueryBuilder(string $expected, string $input, string $handler = 'cql.serverChoice')
    {
        $qb = new QueryBuilder();
        $params = $qb->build(new \VuFindSearch\Query\Query($input, $handler));
        $queryString = $params->get('query')[0];
        $this->assertEquals($expected, $queryString);
    }
}
