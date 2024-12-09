<?php

/**
 * ILS driver test
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2011.
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

use VuFind\ILS\Driver\Unicorn;

/**
 * ILS driver test
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class UnicornTest extends \VuFindTest\Unit\ILSDriverTestCase
{
    use \VuFindTest\Feature\FixtureTrait;
    use \VuFindTest\Feature\ReflectionTrait;

    /**
     * Standard setup method.
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->driver = new Unicorn(new \VuFind\Date\Converter());
    }

    /**
     * Test date formatting.
     *
     * @return void
     */
    public function testDateFormatting(): void
    {
        $time = 1649275905;
        $this->assertEquals(
            '04-06-2022',
            $this->callMethod($this->driver, 'formatDateTime', [$time])
        );
    }

    /**
     * Test MARC holdings parsing.
     *
     * @return void
     */
    public function testMarcParsing(): void
    {
        $marc = $this->getFixture('marc/unicornholdings.mrc');
        // The 'marc852' element contains an object and is not used in existing code.
        // Let's just make sure that the element is present, then remove it from the
        // array to simplify subsequent comparison assertions.
        $checkAndRemove852 = function ($result) {
            $this->assertTrue(isset($result['marc852']));
            unset($result['marc852']);
            return $result;
        };
        $results = array_map(
            $checkAndRemove852,
            $this->callMethod($this->driver, 'getMarcHoldings', [$marc])
        );

        $this->assertEquals(
            [
                [
                    'library_code' => 'library',
                    'library' => 'library',
                    'location_code' => 'location',
                    'location' => 'location',
                    'notes' => [
                        'note',
                    ],
                    'summary' => [
                        1234000000000 => '863field linked note',
                    ],
                ],
                [
                    'library_code' => 'library2',
                    'library' => 'library2',
                    'location_code' => 'location2',
                    'location' => 'location2',
                    'notes' => [
                        'note2a',
                        'note2b',
                    ],
                    'summary' => [
                        1234000000000 => '863field linked note',
                    ],
                ],
            ],
            $results
        );
    }
}
