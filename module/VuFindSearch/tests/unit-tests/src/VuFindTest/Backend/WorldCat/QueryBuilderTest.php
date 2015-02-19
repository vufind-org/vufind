<?php

/**
 * Unit tests for WorldCat query builder
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
namespace VuFindTest\Backend\WorldCat;

use VuFindSearch\Backend\WorldCat\QueryBuilder;
use PHPUnit_Framework_TestCase;

/**
 * Unit tests for WorldCat query builder
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
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
            ['basic', '(srw.au all "john smith" OR srw.pn all "john smith" OR srw.cn all "john smith")'],
            ['advanced', '((srw.ti all "bananas" OR srw.se all "bananas")) AND ((srw.su all "oranges") OR (srw.su all "apples")) NOT (((srw.se all "pears")))']
        ];
        // @codingStandardsIgnoreEnd

        $qb = new QueryBuilder();
        foreach ($tests as $test) {
            list($input, $output) = $test;
            $q = unserialize(
                file_get_contents(
                    PHPUNIT_SEARCH_FIXTURES . '/worldcat/query/' . $input
                )
            );
            $response = $qb->build($q);
            $processedQ = $response->get('query');
            $this->assertEquals($output, $processedQ[0]);
        }
    }

    /**
     * Test the "exclude code" feature.
     *
     * @return void
     */
    public function testExclude()
    {
        $qb = new QueryBuilder('TEST');
        $q = unserialize(
            file_get_contents(PHPUNIT_SEARCH_FIXTURES . '/worldcat/query/basic')
        );
        $response = $qb->build($q);
        $processedQ = $response->get('query');
        $output = '(srw.au all "john smith" OR srw.pn all "john smith"'
            . ' OR srw.cn all "john smith") not srw.li all "TEST"';
        $this->assertEquals($output, $processedQ[0]);
    }
}