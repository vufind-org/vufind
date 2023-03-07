<?php

/**
 * Unit tests for WorldCat backend.
 *
 * PHP version 7
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

namespace VuFindTest\Backend\WorldCat\Response\XML;

use VuFindSearch\Backend\WorldCat\Response\XML\RecordCollectionFactory;

/**
 * Unit tests for WorldCat backend.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class RecordCollectionFactoryTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test bad callback.
     *
     * @return void
     */
    public function testBadCallback()
    {
        $this->expectException(\VuFindSearch\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Record factory must be callable.');

        $x = new RecordCollectionFactory('bad');
    }

    /**
     * Test bad factory input.
     *
     * @return void
     */
    public function testBadFactoryInput()
    {
        $this->expectException(\VuFindSearch\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unexpected type of value: Expected array, got string');

        $x = new RecordCollectionFactory();
        $x->factory('bad');
    }
}
