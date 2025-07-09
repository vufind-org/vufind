<?php

/**
 * Holds logic test
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
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

use VuFind\Auth\ILSAuthenticator;
use VuFind\Config\Config;
use VuFind\Crypt\HMAC;
use VuFind\ILS\Connection;
use VuFind\ILS\Logic\Holds;

/**
 * Holds logic test
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class HoldsTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Get a Holds object for testing.
     *
     * @param ?\VuFind\Auth\ILSAuthenticator $ilsAuth ILS authenticator (null for mock)
     * @param ?ILSConnection                 $catalog A catalog connection (null for mock)
     * @param ?\VuFind\Crypt\HMAC            $hmac    HMAC generator (null for mock)
     * @param ?array                         $config  Configuration array (empty by default)
     *
     * @return Holds
     */
    protected function getHoldsLogic(
        ?ILSAuthenticator $ilsAuth = null,
        ?Connection $catalog = null,
        ?HMAC $hmac = null,
        array $config = []
    ): Holds {
        return new Holds(
            $ilsAuth ?? $this->createMock(ILSAuthenticator::class),
            $catalog ?? $this->createMock(Connection::class),
            $hmac ?? $this->createMock(HMAC::class),
            new Config($config)
        );
    }

    /**
     * Data provider for testGetSuppressedLocations().
     *
     * @return array
     */
    public static function suppressedLocationsProvider(): array
    {
        return [
            'default' => [[], []],
            'non-empty list' => [['Record' => ['hide_holdings' => ['a', 'b', 'c']]], ['a', 'b', 'c']],
        ];
    }

    /**
     * Test getSuppressedLocations().
     *
     * @param array $configArray  Configuration array
     * @param array $expectedList Expected suppressed locations list
     *
     * @return void
     *
     * @dataProvider suppressedLocationsProvider
     */
    public function testGetSuppressedLocations(array $configArray, array $expectedList): void
    {
        $logic = $this->getHoldsLogic(config: $configArray);
        $this->assertEquals($expectedList, $logic->getSuppressedLocations());
    }
}
