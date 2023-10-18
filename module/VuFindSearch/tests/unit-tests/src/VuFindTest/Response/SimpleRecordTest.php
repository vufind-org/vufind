<?php

/**
 * Unit tests for SimpleRecord class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2020.
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

namespace VuFindTest\Response;

use PHPUnit\Framework\TestCase;
use VuFindSearch\Response\SimpleRecord;

/**
 * Unit tests for SimpleRecord class.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class SimpleRecordTest extends TestCase
{
    /**
     * Test for expected default source ID.
     *
     * @return void
     */
    public function testDefaultSourceId()
    {
        $record = new SimpleRecord([]);
        $this->assertEquals(DEFAULT_SEARCH_BACKEND, $record->getSourceIdentifier());
    }

    /**
     * Test that we can change default source ID.
     *
     * @return void
     */
    public function testSetSourceId()
    {
        $record = new SimpleRecord([]);
        $record->setSourceIdentifier('foo');
        $this->assertEquals('foo', $record->getSourceIdentifier());
    }

    /**
     * Test retrieving data fields.
     *
     * @return void
     */
    public function testGetFields()
    {
        $record = new SimpleRecord(['foo' => 'bar']);
        $this->assertEquals('bar', $record->get('foo'));
    }
}
