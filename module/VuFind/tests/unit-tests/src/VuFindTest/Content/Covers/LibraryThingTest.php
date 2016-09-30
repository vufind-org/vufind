<?php

/**
 * Unit tests for LibraryThing cover loader.
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
namespace VuFindTest\Content\Covers;
use VuFindCode\ISBN, VuFind\Content\Covers\LibraryThing;

/**
 * Unit tests for LibraryThing cover loader.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class LibraryThingTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test cover loading
     *
     * @return void
     */
    public function testValidCoverLoading()
    {
        $loader = new LibraryThing();
        $this->assertEquals(
            'http://covers.librarything.com/devkey/mykey/small/isbn/9780739313121',
            $loader->getUrl(
                'mykey', 'small', ['isbn' => new ISBN('0739313126')]
            )
        );
    }

    /**
     * Test missing ISBN
     *
     * @return void
     */
    public function testMissingIsbn()
    {
        $loader = new LibraryThing();
        $this->assertEquals(false, $loader->getUrl('mykey', 'small', []));
    }
}
