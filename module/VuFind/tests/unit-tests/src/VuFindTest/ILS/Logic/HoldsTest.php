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
    use \VuFindTest\Feature\FixtureTrait;

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
        
        // Use reflection to access the protected formatHoldings method
        $reflection = new \ReflectionClass($logic);
        $method = $reflection->getMethod('formatHoldings');
        $method->setAccessible(true);
        
        // Example holdings data
        // note that this is a simplified example; actual data may vary and include an availability object
        $holdings = $this->getJsonFixture('ils/holdings_example.json');
        
        $result = $method->invoke($logic, $holdings);

        // assert various properties of the result
        $this->assertArrayHasKey('location1_id|Main Library', $result);
        $this->assertEquals('Main Library', $result['location1_id|Main Library']['location']);
        $this->assertCount(2, $result['location1_id|Main Library']['items']);
        $this->assertEquals('02.09.2025', $result['location1_id|Main Library']['items'][0]['duedate']);
        $this->assertEquals('Circulating', $result['location1_id|Main Library']['items'][0]['loan_type_name']);
        $this->assertEquals(true, $result['location1_id|Main Library']['items'][0]['is_holdable']);
        $this->assertEquals('ac4cf292-ffca-40e8-a91a-5bb349532fe0', $result['location1_id|Main Library']['items'][0]['item_id']);
        $this->assertEquals('228bcdc1-4c16-488d-88d4-57374f412c6e', $result['location1_id|Main Library']['items'][0]['holdings_id']);
        $this->assertEquals(
            'item_id=ac4cf292-ffca-40e8-a91a-5bb349532fe0&holdings_id=228bcdc1-4c16-488d-88d4-57374f412c6e&status=Checked+out&hashKey=fb00698a17b30097c95d55a79f658d9d',
            $result['location1_id|Main Library']['items'][0]['link']['query']
        );
    }

     /**
     * Create a mock availability status for testing
     *
     * @param bool $available Whether the item is available
     * @param string $description Status description
     *
     * @return object
     */
    protected function createMockAvailabilityStatus($available = true, $description = 'Available')
    {
        // Create an anonymous class that implements the required methods
        // generated with Claude Sonnet 4 - but there must be a better way to do this,
        // i.e. by hooking into the AvailabilityStatusInterface directly.
        return new class($available, $description) {
            private $available;
            private $description;
            
            public function __construct($available, $description)
            {
                $this->available = $available;
                $this->description = $description;
            }
            
            public function isAvailable(): bool
            {
                return $this->available;
            }
            
            public function getStatusDescription(): string
            {
                return $this->description;
            }
        };
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
        
        $reflection = new \ReflectionClass($logic);
        $method = $reflection->getMethod('generateHoldings');
        $method->setAccessible(true);
        
        // Example holdings data; lacks 'availability' field
        $result = $this->getJsonFixture('ils/holdings_formatted_example.json');
        $availabilityStatus = array(
            array(false, 'Checked Out'),
            array(true, 'Available'),
            array(true, 'Available')
        );
        $i = 0;
        foreach ($result['holdings'] as &$holding) {
            // Add 'availability' field to simulate different hold types
            $holding['availability'] = $this->createMockAvailabilityStatus(
                $availabilityStatus[$i][0], $availabilityStatus[$i][1]
            );
            $i++;
        }
        $holdConfig = [
            'function' => 'placeHold',
            'HMACKeys' => ['id', 'location']
        ];
        
        // Test 'all' mode (all items get a link)
        $holdings = $method->invoke($logic, $result, 'all', $holdConfig);
        $this->assertArrayHasKey('link', $holdings['holdings_id_1|Main Library'][0]);
        $this->assertArrayHasKey('link', $holdings['holdings_id_2|Secondary Library'][0]);
        
        // Test 'holds' mode (only available items get a link)
        $holdings = $method->invoke($logic, $result, 'holds', $holdConfig);
        $this->assertArrayNotHasKey('link', $holdings['holdings_id_1|Main Library'][0]);
        $this->assertArrayHasKey('link', $holdings['holdings_id_2|Secondary Library'][0]);
        
        // // Test 'recalls' mode (only unavailable items get a link)
        $holdings = $method->invoke($logic, $result, 'recalls', $holdConfig);
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
        
        $reflection = new \ReflectionClass($logic);
        $method = $reflection->getMethod('getRequestDetails');
        $method->setAccessible(true);
        
        $details = [
            'id' => 'test123',
            'location' => 'Main Library',
            'source' => 'Solr'
        ];
        
        $hmacKeys = ['id', 'location'];
        $action = 'Hold';
        
        $result = $method->invoke($logic, $details, $hmacKeys, $action);
        
        $this->assertEquals('Hold', $result['action']);
        $this->assertEquals('test123', $result['record']);
        $this->assertEquals('Solr', $result['source']);
        $this->assertStringContainsString('hashKey=test-hash-key', $result['query']);
        $this->assertEquals('#tabnav', $result['anchor']);
        
        // Test with link overrides
        $linkOverrides = ['id' => 'override123', 'source' => 'Override'];
        $result = $method->invoke($logic, $details, $hmacKeys, $action, $linkOverrides);
        
        $this->assertEquals('override123', $result['record']);
        $this->assertEquals('Override', $result['source']);
    }
}
