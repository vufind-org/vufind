<?php

/**
 * KohaRestSuomiVuFind test class
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

use Finna\ILS\Driver\KohaRestSuomiVuFind;
use Generator;
use PHPUnit\Framework\MockObject\MockObject;
use VuFind\Service\CurrencyFormatter;
use VuFindTest\Feature\FixtureTrait;
use VuFindTest\Feature\ReflectionTrait;

/**
 * KohaRestSuomiVufind test class
 *
 * @category VuFind
 * @package  Tests
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class KohaRestSuomiVuFindTest extends \PHPUnit\Framework\TestCase
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
     * @return MockObject&KohaRestSuomiVuFind
     */
    public function createConnector(
        array $requestMap = [],
        array $config = [],
    ): MockObject&KohaRestSuomiVuFind {
        // Clear the cache
        $this->cache = [];
        $connector = $this->getMockBuilder(KohaRestSuomiVuFind::class)->setConstructorArgs(
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
            'email' => 'test@email.fi',
            'messagingServices' => [],
            'loan_history' => null,
            'firstname' => 'Test',
            'lastname' => 'Tester',
            'address1' => 'Address 1',
            'address2' => 'Address 2',
            'city' => 'Test city',
            'country' => 'Test country',
            'zip' => '010101',
            'phone' => '0101010101',
            'mobile_phone' => null,
            'expiration_date' => '',
            'group' => null,
            'home_library' => null,
            'birthdate' => '',
        ];
        yield 'profile all values set' => [
            'koharestsuomivufind/profile.json',
            $defaultExpected,
            [
                'updateMessagingSettings' => [
                    'method' => 'driver',
                ],
            ],
        ];
        yield 'profile some values missing' => [
            'koharestsuomivufind/profile_partial.json',
            [
                'messagingServices' => [],
                'loan_history' => null,
                'email' => '',
                'firstname' => '',
                'lastname' => 'Tester',
                'birthdate' => '',
                'address1' => '',
                'address2' => 'Address 2',
                'city' => '',
                'country' => '',
                'zip' => '',
                'phone' => '',
                'mobile_phone' => null,
                'expiration_date' => '',
                'group' => null,
                'home_library' => null,
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
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestGetMyProfileData')]
    public function testGetMyProfile(string $fixtureKey, array $expected, array $config = []): void
    {
        $patron = [
            'id' => '5',
            'cat_username' => '1111',
            'cat_password' => '2222',
        ];
        $requestMap = [
            [
                ['v1', 'patrons', $patron['id']],
                false,
                'GET',
                $patron,
                $this->getJsonFixture($fixtureKey, 'Finna'),
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
            'koharestsuomivufind/patron.json',
            [
                'id' => '123123',
                'email' => 'test@email.fi',
                'firstname' => 'Test',
                'lastname' => 'Tester',
                'cat_username' => '1111',
                'cat_password' => '2222',
                'major' => null,
                'college' => null,
                'home_library' => '12',
            ],
        ];
        yield 'patron some values missing' => [
            'koharestsuomivufind/patron_partial.json',
            [
                'email' => '',
                'firstname' => '',
                'lastname' => '',
                'home_library' => '',
                'id' => '123123',
                'major' => null,
                'college' => null,
                'cat_username' => '1111',
                'cat_password' => '2222',
            ],
        ];
    }

    /**
     * Test patronLogin
     *
     * @param string $fixtureKey Response fixture
     * @param array  $expected   Expected results for the test
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestPatronLoginData')]
    public function testPatronLogin(string $fixtureKey, array $expected): void
    {
        $patron = ['cat_username' => '1111', 'cat_password' => '2222'];
        $requestMap = [
            [
                ['v1', 'patrons', '5'],
                false,
                'GET',
                $patron,
                true,
                $this->getJsonFixture($fixtureKey, 'Finna'),
            ],
        ];
        $connector = $this->createConnector(requestMap: $requestMap);
        $mockSessionCache = $this->getMockBuilder(\Laminas\Session\Container::class)
            ->disableOriginalConstructor()->getMock();
        $mockSessionCache->expects($this->any())->method('__get')->willReturnMap(
            [
                ['patron', '1111'],
                ['patronId', '5'],
            ]
        );
        $this->setProperty($connector, 'sessionCache', $mockSessionCache);
        $patron = $connector->patronLogin('1111', '2222');
        $this->assertEquals($expected, $patron);
    }
}
