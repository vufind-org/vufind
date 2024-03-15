<?php

/**
 * Unit tests for EDS record collection factory
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

namespace VuFindTest\Backend\EDS\Response;

use PHPUnit\Framework\TestCase;
use VuFindSearch\Backend\EDS\Response\RecordCollectionFactory;

/**
 * Unit tests for EDS record collection factory
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class RecordCollectionFactoryTest extends TestCase
{
    /**
     * Test constructor exceptions.
     *
     * @return void
     */
    public function testConstructorRequiresFactoryFunction()
    {
        $this->expectException(\VuFindSearch\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Record factory must be callable.');

        new RecordCollectionFactory(null);
    }

    /**
     * Test constructor exceptions.
     *
     * @return void
     */
    public function testFactoryRequiresArray()
    {
        $this->expectException(\VuFindSearch\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unexpected type of value: Expected array, got string');

        $factory = new RecordCollectionFactory(
            function () {
            }
        );
        $factory->factory('bad');
    }
}
