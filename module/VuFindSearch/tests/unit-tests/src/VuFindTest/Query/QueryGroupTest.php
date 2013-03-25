<?php

/**
 * Unit tests for QueryGroup class.
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

namespace VuFindTest\Query;

use VuFindSearch\Query\Query;
use VuFindSearch\Query\QueryGroup;
use PHPUnit_Framework_TestCase;

/**
 * Unit tests for QueryGroup class.
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class QueryGroupTest extends PHPUnit_Framework_TestCase
{
    /**
     * Test containsTerm() method
     *
     * @return void
     */
    public function testContainsTerm()
    {
        $q1 = new Query('test');
        $q2 = new Query('query');
        $q3 = new Query('multi word query');
        $q = new QueryGroup('OR', array($q1, $q2, $q3));

        // Should report true for actual contained terms:
        $this->assertTrue($q->containsTerm('test'));
        $this->assertTrue($q->containsTerm('word'));
        $this->assertTrue($q->containsTerm('query'));

        // Should not contain a non-present term:
        $this->assertFalse($q->containsTerm('garbage'));

        // Should not contain a partial term (matches on word boundaries):
        $this->assertFalse($q->containsTerm('tes'));
    }

    /**
     * Test getAllTerms() method
     *
     * @return void
     */
    public function testGetAllTerms()
    {
        $q1 = new Query('test');
        $q2 = new Query('query');
        $q3 = new Query('multi word query');
        $q = new QueryGroup('OR', array($q1, $q2, $q3));
        $this->assertEquals('test query multi word query', $q->getAllTerms());
    }
}
