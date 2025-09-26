<?php

/**
 * SierraRest test class
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace FinnaTest\ILS\Driver;

use Finna\ILS\Driver\SierraRest;
use Generator;
use PHPUnit\Framework\MockObject\MockObject;
use VuFind\Service\CurrencyFormatter;
use VuFindTest\Feature\FixtureTrait;
use VuFindTest\Feature\ReflectionTrait;

/**
 * SierraRest test class
 *
 * @category VuFind
 * @package  Tests
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SierraRestTest extends \PHPUnit\Framework\TestCase
{
    use FixtureTrait;
    use ReflectionTrait;

    /**
     * Local cache
     *
     * @var array
     */
    protected array $cache = [];

    /**
     * Create connector
     *
     * @param array $requestMap Request map
     * @param array $config     Driver config
     *
     * @return MockObject&SierraRest
     */
    public function createConnector(
        array $requestMap = [],
        array $config = [],
    ): MockObject&SierraRest {
        // Clear the cache
        $this->cache = [];
        $connector = $this->getMockBuilder(SierraRest::class)->setConstructorArgs(
            [
                new \VuFind\Date\Converter(),
                fn ($namespace) => null,
                $this->getMockBuilder(CurrencyFormatter::class)->disableOriginalConstructor()->getMock(),
            ]
        )->onlyMethods(['makeRequest', 'debug'])
            ->getMock();
        $connector->expects($this->any())->method('makeRequest')->willReturnMap($requestMap);
        $connector->setConfig($config);
        return $connector;
    }

    /**
     * Data provider for testGetMyProfile
     *
     * @return Generator
     */
    public static function getTestGetMyProfileData(): Generator
    {
        $defaultExpected = [
            'self_service_library' => null,
            'expired' => true,
            'expiration_soon' => false,
            'messages' => [
                [
                    'message' => 'Test message 1',
                ],
                [
                    'message' => 'Test message 2',
                ],
            ],
            'smsnumber' => '0123456789',
            'messagingServices' => [],
            'loan_history' => null,
            'email' => '',
            'firstname' => 'Test',
            'lastname' => 'Tester',
            'birthdate' => '',
            'address1' => 'Street address 1',
            'address2' => null,
            'city' => 'Test city',
            'country' => null,
            'zip' => '010101',
            'phone' => '01010101',
            'mobile_phone' => null,
            'expiration_date' => '12-21-2019',
            'group' => null,
            'home_library' => '121',
        ];
        yield 'profile all values set' => [
            'sierrarest/profile.json',
            $defaultExpected,
            [
                'updateMessagingSettings' => [
                    'method' => 'driver',
                ],
            ],
        ];
        yield 'profile some values missing' => [
            'sierrarest/profile_partial.json',
            [
                'self_service_library' => null,
                'expired' => false,
                'expiration_soon' => false,
                'messages' => [],
                'smsnumber' => '',
                'messagingServices' => [],
                'loan_history' => null,
                'email' => '',
                'firstname' => '',
                'lastname' => 'Test',
                'birthdate' => '',
                'address1' => '',
                'address2' => null,
                'city' => '',
                'country' => null,
                'zip' => '',
                'phone' => '',
                'mobile_phone' => null,
                'expiration_date' => null,
                'group' => null,
                'home_library' => '',
            ],
        ];
    }

    /**
     * Test getMyProfile
     *
     * @param string $fixtureKey Request fixture path
     * @param array  $expected   Expected results for the test
     * @param array  $config     Driver specific configuration
     *
     * @return       void
     * @dataProvider getTestGetMyProfileData
     */
    public function testGetMyProfile(string $fixtureKey, array $expected, array $config = []): void
    {
        $patron = [
            'cat_username' => '1111',
            'cat_password' => '2222',
            'id' => '5',
        ];
        $requestMap = [
            [
                ['v6', 'patrons', $patron['id']],
                [
                    'fields' => 'default,names,emails,phones,addresses,message,homeLibraryCode,fixedFields',
                ],
                'GET',
                $patron,
                $this->getJsonFixture($fixtureKey, 'Finna'),
            ],
            [
                [
                    'v6', 'patrons', $patron['id'], 'checkouts', 'history',
                    'activationStatus',
                ],
                [],
                'GET',
                $patron,
                ['readingHistoryActivation', false],
            ],
        ];
        $connector = $this->createConnector(requestMap: $requestMap, config: $config);
        $profile = $connector->getMyProfile($patron);
        $this->assertEquals($expected, $profile);
    }

    /**
     * Data provider for testPatronLogin
     *
     * @return Generator
     */
    public static function getTestPatronLoginData(): Generator
    {
        yield 'patron all values set' => [
            'sierrarest/patron.json',
            [
                'id' => '1122',
                'email' => 'test@email.fi',
                'firstname' => 'Test',
                'lastname' => 'Tester',
                'cat_username' => '1111',
                'cat_password' => '2222',
                'major' => null,
                'college' => null,
                'home_library' => '121',
            ],
        ];
        yield 'patron values missing' => [
            'sierrarest/patron_partial.json',
            [
                'id' => '1122',
                'email' => '',
                'firstname' => '',
                'lastname' => 'Test',
                'cat_username' => '1111',
                'cat_password' => '2222',
                'major' => null,
                'college' => null,
                'home_library' => '',
            ],
        ];
    }

    /**
     * Test patronLogin
     *
     * @param string $fixtureKey Response fixture
     * @param array  $expected   Expected results for the test
     *
     * @return       void
     * @dataProvider getTestPatronLoginData
     */
    public function testPatronLogin(string $fixtureKey, array $expected): void
    {
        $patronResponse = $this->getJsonFixture($fixtureKey, 'Finna');
        $requestMap = [
            [
                ['v6', 'patrons', 'auth'],
                json_encode([
                    'authMethod' => 'native',
                    'patronId' => '1111',
                    'patronSecret' => '2222',
                ]),
                'POST',
                $patronResponse,
            ],
            [
                ['v6', 'patrons', $patronResponse],
                ['fields' => 'names,emails,homeLibraryCode'],
                $patronResponse,
            ],
        ];
        $connector = $this->createConnector(requestMap: $requestMap);
        $patron = $connector->patronLogin('1111', '2222');
        $this->assertEquals($expected, $patron);
    }
}
