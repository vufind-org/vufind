<?php

/**
 * Unit tests for Summon query builder
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
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindTest\Backend\Summon;

use PHPUnit\Framework\TestCase;
use VuFindSearch\Backend\Summon\QueryBuilder;

/**
 * Unit tests for Summon query builder
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class QueryBuilderTest extends TestCase
{
    use \VuFindTest\Feature\FixtureTrait;

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
            ['basic', 'Author:(john smith)'],
            [
                'advanced',
                '(Title:(bananas)) AND (SubjectTerms:(oranges) OR apples) NOT ((PublicationSeriesTitle:(pears)))',
            ],
        ];

        $qb = new QueryBuilder();
        foreach ($tests as $test) {
            [$input, $output] = $test;
            $q = unserialize(
                $this->getFixture("summon/query/$input", 'VuFindSearch')
            );
            $response = $qb->build($q);
            $processedQ = $response->get('query');
            $this->assertEquals($output, $processedQ[0]);
        }
    }
}
