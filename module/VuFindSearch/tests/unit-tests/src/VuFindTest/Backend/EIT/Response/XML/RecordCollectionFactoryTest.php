<?php

/**
 * Unit tests for EIT record collection factory.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
namespace VuFindTest\Backend\EIT\Response\XML;

use VuFindSearch\Backend\EIT\Response\XML\RecordCollectionFactory;
use PHPUnit_Framework_TestCase;

/**
 * Unit tests for EIT record collection factory.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class RecordCollectionFactoryTest extends PHPUnit_Framework_TestCase
{
    /**
     * Test constructor exception.
     *
     * @return void
     *
     * @expectedException        VuFindSearch\Exception\InvalidArgumentException
     * @expectedExceptionMessage Record factory must be callable.
     */
    public function testConstructorRequiresValidFactoryFunction()
    {
        $factory = new RecordCollectionFactory(12345);
    }

    /**
     * Test invalid input.
     *
     * @return void
     *
     * @expectedException        VuFindSearch\Exception\InvalidArgumentException
     * @expectedExceptionMessage Unexpected type of value: Expected array, got string
     */
    public function testInvalidInput()
    {
        $fact = new RecordCollectionFactory(
            function () {

            }
        );
        $coll = $fact->factory('garbage');
    }
}
