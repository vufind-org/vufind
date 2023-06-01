<?php

/**
 * Class KohaILSDITest
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2021.
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
 * @package  VuFindTest\ILS\Driver
 * @author   Josef Moravec <josef.moravec@gmail.com>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://knihovny.cz Main Page
 */

declare(strict_types=1);

namespace VuFindTest\ILS\Driver;

use VuFind\ILS\Driver\KohaILSDI;

/**
 * Class KohaILSDITest
 *
 * @category VuFind
 * @package  VuFindTest\ILS\Driver
 * @author   Josef Moravec <josef.moravec@gmail.com>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://knihovny.cz Main Page
 */
class KohaILSDITest extends \VuFindTest\Unit\ILSDriverTestCase
{
    /**
     * Standard setup method.
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->driver = new KohaILSDI(new \VuFind\Date\Converter());
    }

    /**
     * Test toKohaDate method
     *
     * @return void
     * @throws \ReflectionException
     */
    public function testToKohaDate(): void
    {
        $method = new \ReflectionMethod('\VuFind\ILS\Driver\KohaILSDI', 'toKohaDate');
        $method->setAccessible(true);
        $this->assertEquals('1982-10-22', $method->invokeArgs($this->driver, ['10-22-1982']));
        $this->assertEquals(null, $method->invokeArgs($this->driver, ['']));
        $this->assertEquals(null, $method->invokeArgs($this->driver, [null]));
        $this->expectException(\VuFind\Date\DateException::class);
        $method->invokeArgs($this->driver, ['1222-96-9696']);
    }
}
