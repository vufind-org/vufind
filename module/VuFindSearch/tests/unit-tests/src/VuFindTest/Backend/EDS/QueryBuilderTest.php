<?php

/**
 * Unit tests for EDS query builder
 *
 * PHP version 5
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
namespace VuFindTest\Backend\EDS;

use VuFindSearch\Backend\EDS\QueryBuilder;
use PHPUnit_Framework_TestCase;

/**
 * Unit tests for EDS query builder
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class QueryBuilderTest extends PHPUnit_Framework_TestCase
{
    /**
     * Test query parsing.
     *
     * @return void
     */
    public function testParsing()
    {
        // Set up an array of expected inputs (serialized objects) and outputs
        // (queries):
        // @codingStandardsIgnoreStart
        $tests = [
            ['advanced', ['AND,cheese', 'AND,SU:test']]
        ];
        // @codingStandardsIgnoreEnd

        $qb = new QueryBuilder();
        foreach ($tests as $test) {
            list($input, $output) = $test;
            $q = unserialize(
                file_get_contents(
                    PHPUNIT_SEARCH_FIXTURES . '/eds/query/' . $input
                )
            );
            $response = $qb->build($q);
            $this->assertEquals($output, $response->get('query'));
        }
    }
}
