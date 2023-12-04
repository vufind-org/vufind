<?php

/**
 * "None" URL shortener test.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2019.
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
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\UrlShortener;

use VuFind\UrlShortener\None;

/**
 * "None" URL shortener test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class NoneTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test that the shortener does nothing.
     *
     * @return void
     */
    public function testShortener()
    {
        $none = new None();
        $url = 'http://foo';
        $this->assertEquals($url, $none->shorten($url));
    }

    /**
     * Test that resolve is not supported.
     *
     * @return void
     */
    public function testNoResolution()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('UrlShortener None is unable to resolve shortlinks.');

        $none = new None();
        $none->resolve('foo');
    }
}
