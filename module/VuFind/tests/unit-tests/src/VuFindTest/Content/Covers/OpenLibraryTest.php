<?php

/**
 * Unit tests for OpenLibrary cover loader.
 *
 * PHP version 8
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
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindTest\Content\Covers;

use VuFind\Content\Covers\OpenLibrary;
use VuFindCode\ISBN;

/**
 * Unit tests for OpenLibrary cover loader.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class OpenLibraryTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test cover loading
     *
     * @return void
     */
    public function testValidCoverLoading(): void
    {
        $ol = new OpenLibrary();
        $this->assertEquals(
            'http://covers.openlibrary.org/b/isbn/9780739313121-S.jpg?default=false',
            $ol->getUrl(
                'mykey',
                'small',
                ['isbn' => new ISBN('0739313126')]
            )
        );
    }

    /**
     * Test missing ISBN
     *
     * @return void
     */
    public function testMissingIsbn(): void
    {
        $ol = new OpenLibrary();
        $this->assertFalse($ol->getUrl('mykey', 'small', []));
    }
}
