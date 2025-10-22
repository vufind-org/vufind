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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
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
use VuFind\Exception\ILS as ILSException;
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
    use \VuFindTest\Feature\FixtureTrait;
    use \VuFindTest\Feature\ReflectionTrait;

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

    /**
     * Test formatHoldings method
     *
     * @return void
     */
    public function testFormatHoldings(): void
    {
        $catalog = $this->createMock(Connection::class);
        $catalog->method('getHoldingsTextFieldNames')->willReturn(['notes', 'summary']);

        $logic = $this->getHoldsLogic(catalog: $catalog);

        // Example holdings data
        // note that this is a simplified example; actual data may vary and include an availability object
        $holdings = $this->getJsonFixture('ils/holdings_example.json');

        // Use callMethod instead of manual reflection
        $result = $this->callMethod($logic, 'formatHoldings', [$holdings]);

        // assert various properties of the result
        $this->assertArrayHasKey('location1_id|Main Library', $result);
        $this->assertEquals('Main Library', $result['location1_id|Main Library']['location']);
        $this->assertCount(2, $result['location1_id|Main Library']['items']);
        $this->assertEquals('02.09.2025', $result['location1_id|Main Library']['items'][0]['duedate']);
        $this->assertEquals('Circulating', $result['location1_id|Main Library']['items'][0]['loan_type_name']);
        $this->assertEquals(true, $result['location1_id|Main Library']['items'][0]['is_holdable']);
        $this->assertEquals(
            'item_id_1',
            $result['location1_id|Main Library']['items'][0]['item_id']
        );
        $this->assertEquals(
            'holdings_id_1',
            $result['location1_id|Main Library']['items'][0]['holdings_id']
        );
        $this->assertEquals(
            'item_id=item_id_1&holdings_id=holdings_id_1&status=Checked+out&hashKey=sample_hash_key',
            $result['location1_id|Main Library']['items'][0]['link']['query']
        );
    }

    /**
     * Create an availability status for testing
     *
     * @param bool   $available   Whether the item is available
     * @param string $description Status description
     *
     * @return AvailabilityStatus
     */
    protected function createAvailabilityStatus($available = true, $description = 'Available')
    {
        // Use the real AvailabilityStatus class
        $availability = $available ?
            \VuFind\ILS\Logic\AvailabilityStatusInterface::STATUS_AVAILABLE :
            \VuFind\ILS\Logic\AvailabilityStatusInterface::STATUS_UNAVAILABLE;

        return new \VuFind\ILS\Logic\AvailabilityStatus($availability, $description);
    }

    /**
     * Test generateHoldings method with different hold types
     *
     * @return void
     */
    public function testGenerateHoldings(): void
    {
        $hmac = $this->createMock(HMAC::class);
        $hmac->method('generate')->willReturn('test-hash');

        $logic = $this->getHoldsLogic(hmac: $hmac);

        // Example holdings data; lacks 'availability' field
        $result = $this->getJsonFixture('ils/holdings_formatted_example.json');
        $availabilityStatus = [
            [false, 'Checked Out'],
            [true, 'Available'],
            [true, 'Available'],
        ];
        foreach ($result['holdings'] as $i => &$holding) {
            // Add 'availability' field using the real class
            $holding['availability'] = $this->createAvailabilityStatus(
                $availabilityStatus[$i][0],
                $availabilityStatus[$i][1]
            );
        }

        $holdConfig = [
            'function' => 'placeHold',
            'HMACKeys' => ['id', 'location'],
        ];

        // Rest of the test remains the same...
        // Test 'all' mode (all items get a link)
        $holdings = $this->callMethod($logic, 'generateHoldings', [$result, 'all', $holdConfig]);
        $this->assertArrayHasKey('link', $holdings['holdings_id_1|Main Library'][0]);
        $this->assertArrayHasKey('link', $holdings['holdings_id_2|Secondary Library'][0]);

        // Test 'holds' mode (only available items get a link)
        $holdings = $this->callMethod($logic, 'generateHoldings', [$result, 'holds', $holdConfig]);
        $this->assertArrayNotHasKey('link', $holdings['holdings_id_1|Main Library'][0]);
        $this->assertArrayHasKey('link', $holdings['holdings_id_2|Secondary Library'][0]);

        // Test 'recalls' mode (only unavailable items get a link)
        $holdings = $this->callMethod($logic, 'generateHoldings', [$result, 'recalls', $holdConfig]);
        $this->assertArrayHasKey('link', $holdings['holdings_id_1|Main Library'][0]);
        $this->assertArrayNotHasKey('link', $holdings['holdings_id_2|Secondary Library'][0]);
    }

    /**
     * Test getRequestDetails method
     *
     * @return void
     */
    public function testGetRequestDetails(): void
    {
        $hmac = $this->createMock(HMAC::class);
        $hmac->method('generate')->willReturn('test-hash-key');

        $logic = $this->getHoldsLogic(hmac: $hmac);

        $details = [
            'id' => 'test123',
            'location' => 'Main Library',
            'source' => 'Solr',
        ];

        $hmacKeys = ['id', 'location'];
        $action = 'Hold';

        $result = $this->callMethod($logic, 'getRequestDetails', [$details, $hmacKeys, $action]);

        $this->assertEquals('Hold', $result['action']);
        $this->assertEquals('test123', $result['record']);
        $this->assertEquals('Solr', $result['source']);
        $this->assertStringContainsString('hashKey=test-hash-key', $result['query']);
        $this->assertEquals('#tabnav', $result['anchor']);

        // Test with link overrides
        $linkOverrides = ['id' => 'override123', 'source' => 'Override'];
        $result = $this->callMethod($logic, 'getRequestDetails', [$details, $hmacKeys, $action, $linkOverrides]);

        $this->assertEquals('override123', $result['record']);
        $this->assertEquals('Override', $result['source']);
    }

    /**
     * Test getHoldingsGroupKey method
     *
     * @return void
     */
    public function testGetHoldingsGroupKey(): void
    {
        // Test default grouping
        $logic = $this->getHoldsLogic();

        $copy = [
            'holdings_id' => 'holdings_id_1',
            'location' => 'Main Library',
            'call_number' => 'c123456',
        ];

        $result = $this->callMethod($logic, 'getHoldingsGroupKey', [$copy]);
        $this->assertEquals('holdings_id_1|Main Library', $result);

        // Test custom grouping
        $config['Catalog']['holdings_grouping'] = 'location,call_number';
        $logic = $this->getHoldsLogic(config: $config);

        $result = $this->callMethod($logic, 'getHoldingsGroupKey', [$copy]);
        $this->assertEquals('Main Library|c123456', $result);

        // Test legacy location_name
        $config['Catalog']['holdings_grouping'] = 'location_name';
        $logic = $this->getHoldsLogic(config: $config);

        $result = $this->callMethod($logic, 'getHoldingsGroupKey', [$copy]);
        $this->assertEquals('Main Library', $result);
    }

    /**
     * Test getHoldings with ILS exception
     *
     * @return void
     */
    public function testGetHoldingsWithILSException(): void
    {
        $ilsAuth = $this->createMock(ILSAuthenticator::class);
        $ilsAuth->method('storedCatalogLogin')->willThrowException(new ILSException('Login failed'));

        $catalog = $this->createMock(Connection::class);
        $catalog->method('getHoldsMode')->willReturn('disabled');
        $catalog->method('getHolding')->willReturn([
            'total' => 0,
            'holdings' => [],
        ]);
        $catalog->method('getHoldingsTextFieldNames')->willReturn([]);

        $logic = $this->getHoldsLogic(ilsAuth: $ilsAuth, catalog: $catalog);

        $result = $logic->getHoldings('test123');

        $this->assertArrayHasKey('holdings', $result);
        $this->assertArrayHasKey('blocks', $result);
        $this->assertFalse($result['blocks']);
    }

    /**
     * Test getHoldings with other mode
     *
     * @return void
     */
    public function testGetHoldingsOther(): void
    {
        $ilsAuth = $this->createMock(ILSAuthenticator::class);
        $ilsAuth->method('storedCatalogLogin')->willThrowException(new ILSException('Login failed'));

        $catalog = $this->createMock(Connection::class);
        $catalog->method('getHoldsMode')->willReturn('other');
        $catalog->method('getHolding')->willReturn([
            'total' => 1,
            'holdings' => [
                [
                    'callnumber_prefix' => '',
                    'callnumber' => 'c123456-1',
                    'is_holdable' => true,
                    'holdings_notes' => null,
                    'summary' => [],
                    'supplements' => [],
                    'indexes' => [],
                    'location' => 'Main Library',
                    'location_code' => 'MAIN',
                    'folio_location_is_active' => true,
                    'id' => 'instance_id',
                    'item_id' => 'item_id_1',
                    'holdings_id' => 'holdings_id_1',
                    'number' =>  1,
                    'enumchron' => '',
                    'barcode' => '468109755',
                    'duedate' => '02.09.2025',
                    'availability' => $this->createAvailabilityStatus(false, 'Checked Out'),
                    'bound_with_records' => [],
                    'loan_type_id' => 'loan_type_id',
                    'loan_type_name' => 'Circulating',
                ],
            ],
        ]);
        $catalog->method('getHoldingsTextFieldNames')->willReturn(['holdings_notes', 'summary']);

        $logic = $this->getHoldsLogic(ilsAuth: $ilsAuth, catalog: $catalog);

        $result = $logic->getHoldings('test123');

        $this->assertArrayHasKey('holdings', $result);
        $this->assertArrayHasKey('blocks', $result);
        $this->assertArrayHasKey('holdings_id_1|Main Library', $result['holdings']);
        $this->assertCount(1, $result['holdings']['holdings_id_1|Main Library']['items']);
        $this->assertEquals('Main Library', $result['holdings']['holdings_id_1|Main Library']['location']);
        $this->assertFalse($result['blocks']);
    }
}
