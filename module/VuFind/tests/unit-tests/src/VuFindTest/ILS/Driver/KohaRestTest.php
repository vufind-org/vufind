<?php

/**
 * KohaRest ILS driver test
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2023.
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
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\ILS\Driver;

use VuFind\ILS\Driver\KohaRest;

/**
 * KohaRest ILS driver test
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class KohaRestTest extends \VuFindTest\Unit\ILSDriverTestCase
{
    /**
     * Standard setup method.
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->driver = new KohaRest(
            new \VuFind\Date\Converter(),
            function () {
            },
            new \VuFind\Service\CurrencyFormatter()
        );
    }

    /**
     * Test getUrlsForRecord.
     *
     * @return void
     */
    public function testGetUrlsForRecord(): void
    {
        // Default: no links
        $this->assertEmpty($this->driver->getUrlsForRecord(1234));
        // OPAC url with placeholder:
        $this->driver->setConfig(['Catalog' => ['opacURL' => 'http://foo?id=%%id%%']]);
        $this->assertEquals(
            [
                [
                    'url' => 'http://foo?id=1234',
                    'desc' => 'view_in_opac',
                ],
            ],
            $this->driver->getUrlsForRecord(1234)
        );
        // OPAC url without placeholder:
        $this->driver->setConfig(['Catalog' => ['opacURL' => 'http://foo?id=']]);
        $this->assertEquals(
            [
                [
                    'url' => 'http://foo?id=1234',
                    'desc' => 'view_in_opac',
                ],
            ],
            $this->driver->getUrlsForRecord(1234)
        );
    }
}
