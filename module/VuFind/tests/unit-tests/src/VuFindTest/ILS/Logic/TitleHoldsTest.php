<?php

/**
 * Title holds logic test
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
use VuFind\ILS\Logic\TitleHolds;
use VuFindTest\Feature\ReflectionTrait;

/**
 * Title holds logic test
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class TitleHoldsTest extends \PHPUnit\Framework\TestCase
{
    use ReflectionTrait;

    /**
     * Get a TitleHolds object for testing.
     *
     * @param ?\VuFind\Auth\ILSAuthenticator $ilsAuth ILS authenticator (null for mock)
     * @param ?ILSConnection                 $catalog A catalog connection (null for mock)
     * @param ?\VuFind\Crypt\HMAC            $hmac    HMAC generator (null for mock)
     * @param ?array                         $config  Configuration array (empty by default)
     *
     * @return Holds
     */
    protected function getTitleHoldsLogic(
        ?ILSAuthenticator $ilsAuth = null,
        ?Connection $catalog = null,
        ?HMAC $hmac = null,
        array $config = []
    ): TitleHolds {
        return new TitleHolds(
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
     * Test that the hide_holdings setting is processed correctly.
     *
     * @param array $configArray  Configuration array
     * @param array $expectedList Expected suppressed locations list
     *
     * @return void
     *
     * @dataProvider suppressedLocationsProvider
     */
    public function testHideHoldingsBehavior(array $configArray, array $expectedList): void
    {
        $logic = $this->getTitleHoldsLogic(config: $configArray);
        $this->assertEquals($expectedList, $this->getProperty($logic, 'hideHoldings'));
    }

    /**
     * Test that title holds can be disabled.
     *
     * @return void
     */
    public function testDisabledMode(): void
    {
        $catalog = $this->createMock(Connection::class);
        $catalog->expects($this->once())->method('getTitleHoldsMode')->willReturn('disabled');
        $logic = $this->getTitleHoldsLogic(catalog: $catalog);
        $this->assertFalse($logic->getHold('foo'));
    }

    /**
     * Test a failed catalog login
     *
     * @return void
     */
    public function testFailedCatalogLogin(): void
    {
        $catalog = $this->createMock(Connection::class);
        $catalog->expects($this->once())->method('getTitleHoldsMode')->willReturn('driver');
        $ilsAuth = $this->createMock(ILSAuthenticator::class);
        $ilsAuth->expects($this->once())->method('storedCatalogLogin')->willReturn(false);
        $logic = $this->getTitleHoldsLogic(catalog: $catalog, ilsAuth: $ilsAuth);
        $this->assertFalse($logic->getHold('foo'));
    }
}
