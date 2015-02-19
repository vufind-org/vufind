<?php

/**
 * Unit tests for Primo record collection
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
namespace VuFindTest\Backend\Primo\Response;

use VuFindSearch\Backend\Primo\Response\RecordCollection;
use PHPUnit_Framework_TestCase;

/**
 * Unit tests for Primo record collection
 *
 * @category VuFind2
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class RecordCollectionTest extends PHPUnit_Framework_TestCase
{
    /**
     * Test defaults when given empty data.
     *
     * @return void
     */
    public function testDefaults()
    {
        $rc = new RecordCollection([]);
        $this->assertEquals(0, $rc->getTotal());
        $this->assertEquals(0, $rc->getOffset());
        $this->assertEquals([], $rc->getFacets());
    }
}