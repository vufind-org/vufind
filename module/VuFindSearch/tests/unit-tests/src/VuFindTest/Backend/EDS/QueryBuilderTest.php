<?php

/**
 * Unit tests for EDS query builder
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

namespace VuFindTest\Backend\EDS;

use PHPUnit\Framework\TestCase;
use VuFindSearch\Backend\EDS\QueryBuilder;

/**
 * Unit tests for EDS query builder
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class QueryBuilderTest extends TestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    /**
     * Given a response, decode the JSON query objects for easier reading.
     *
     * @param array $response Raw response
     *
     * @return array
     */
    protected function decodeResponse($response)
    {
        foreach ($response as $i => $raw) {
            $response[$i] = json_decode($raw, true);
        }
        return $response;
    }

    /**
     * Test special case for blank queries.
     *
     * @return void
     */
    public function testBlankSearch()
    {
        $qb = new QueryBuilder();
        $params = $qb->build(new \VuFindSearch\Query\Query());
        $response = $params->getArrayCopy();
        $response['query'] = $this->decodeResponse($response['query']);
        $this->assertEquals(
            [
                'query' => [
                    [
                        'term' => '(FT yes) OR (FT no)',
                        'field' => null,
                        'bool' => 'AND',
                    ],
                ],
            ],
            $response
        );
    }

    /**
     * Test query parsing.
     *
     * @return void
     */
    public function testParsing()
    {
        // Set up an array of expected inputs (serialized objects) and outputs
        // (queries):
        $tests = [
            [
                'advanced',
                [
                    [
                        'term' => 'cheese',
                        'field' => null,
                        'bool' => 'AND',
                    ],
                    [
                        'term' => 'test',
                        'field' => 'SU',
                        'bool' => 'AND',
                    ],
                ],
            ],
        ];

        $qb = new QueryBuilder();
        foreach ($tests as $test) {
            [$input, $output] = $test;
            $q = unserialize($this->getFixture("eds/query/$input", 'VuFindSearch'));
            $response = $qb->build($q);
            $this->assertEquals(
                $output,
                $this->decodeResponse($response->get('query'))
            );
        }
    }
}
