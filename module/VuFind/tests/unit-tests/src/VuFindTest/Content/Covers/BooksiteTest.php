<?php

/**
 * Unit tests for Booksite cover loader.
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

use VuFind\Content\Covers\Booksite;
use VuFindCode\ISBN;

/**
 * Unit tests for Booksite cover loader.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class BooksiteTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test cover loading
     *
     * @return void
     */
    public function testValidCoverLoading(): void
    {
        $loader = new Booksite('http://base', 'mykey');
        $this->assertEquals(
            'http://base/poca/content_img?apikey=mykey&ean=9780739313121',
            $loader->getUrl(
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
        $loader = new Booksite('http://base', 'mykey');
        $this->assertFalse($loader->getUrl('mykey', 'small', []));
    }
}
