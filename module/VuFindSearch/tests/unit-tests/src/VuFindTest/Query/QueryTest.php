<?php

/**
 * Unit tests for Query class.
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
namespace VuFindTest\Query;

use VuFindSearch\Query\Query;
use PHPUnit_Framework_TestCase;

/**
 * Unit tests for Query class.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class QueryTest extends PHPUnit_Framework_TestCase
{
    /**
     * Test containsTerm() method
     *
     * @return void
     */
    public function testContainsTerm()
    {
        $q = new Query('test query we<(ird and/or');

        // Should contain all actual terms (even those containing regex chars):
        $this->assertTrue($q->containsTerm('test'));
        $this->assertTrue($q->containsTerm('query'));
        $this->assertTrue($q->containsTerm('we<(ird'));
        // A slash can be a word boundary but also a single term
        $this->assertTrue($q->containsTerm('and'));
        $this->assertTrue($q->containsTerm('or'));
        $this->assertTrue($q->containsTerm('and/or'));

        // Should not contain a non-present term:
        $this->assertFalse($q->containsTerm('garbage'));

        // Should not contain a partial term (matches on word boundaries):
        $this->assertFalse($q->containsTerm('tes'));
    }

    /**
     * Test replaceTerm() method
     *
     * @return void
     */
    public function testReplaceTerm()
    {
        $q = new Query('test query we<(ird and/or');
        $q->replaceTerm('we<(ird', 'we>(ird');
        $q->replaceTerm('and/or', 'and-or');
        $this->assertEquals('test query we>(ird and-or', $q->getString());

        $q = new Query('test query we<(ird and/or');
        $q->replaceTerm('and', 'not');
        $this->assertEquals('test query we<(ird not/or', $q->getString());
    }

    /**
     * Test setHandler() method
     *
     * @return void
     */
    public function testSetHandler()
    {
        $q = new Query('foo', 'bar');
        $q->setHandler('baz');
        $this->assertEquals('baz', $q->getHandler());
    }

    /**
     * Test setOperator() method
     *
     * @return void
     */
    public function testSetOperator()
    {
        $q = new Query('foo', 'bar');
        $q->setOperator('baz');
        $this->assertEquals('baz', $q->getOperator());
    }
}
