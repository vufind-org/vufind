<?php

/**
 * KohaRestSuomi test class
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

use Finna\ILS\Driver\KohaRestSuomi;
use Generator;
use PHPUnit\Framework\MockObject\MockObject;
use VuFind\Service\CurrencyFormatter;
use VuFindTest\Feature\FixtureTrait;
use VuFindTest\Feature\ReflectionTrait;

/**
 * KohaRestSuomi test class
 *
 * @category VuFind
 * @package  Tests
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class KohaRestSuomiTest extends \PHPUnit\Framework\TestCase
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
     * @return MockObject&KohaRestSuomi
     */
    public function createConnector(
        array $requestMap = [],
        array $config = [],
    ): MockObject&KohaRestSuomi {
        // Clear the cache
        $this->cache = [];
        $connector = $this->getMockBuilder(KohaRestSuomi::class)->setConstructorArgs(
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
            'messagingServices' => [
                'Item_Due' => [
                    'type' => 'dueDateNotice',
                    'settings' => [
                        'transport_types' => [
                            'type' => 'multiselect',
                            'options' => [
                                'sms' => [
                                    'active' => false,
                                ],
                                'letter' => [
                                    'active' => true,
                                ],
                            ],
                        ],
                        'digest' => [
                            'type' => 'boolean',
                            'active' => true,
                            'readonly' => true,
                        ],
                    ],
                ],
                'Advance_Notice' => [
                    'type' => 'dueDateAlert',
                    'settings' => [
                        'transport_types' => [
                            'type' => 'multiselect',
                            'options' => [
                                'sms' => [
                                    'active' => false,
                                ],
                                'letter' => [
                                    'active' => true,
                                ],
                            ],
                        ],
                        'days_in_advance' => [
                            'type' => 'select',
                            'value' => 5,
                            'options' => [
                                0 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
                                ],
                                1 => [
                                    'name' => 'messaging_settings_num_of_days',
                                    'active' => false,
                                ],
                                2 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
                                ],
                                3 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
                                ],
                                4 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
                                ],
                                5 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => true,
                                ],
                                6 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
                                ],
                                7 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
                                ],
                                8 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
                                ],
                                9 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
                                ],
                                10 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
                                ],
                                11 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
                                ],
                                12 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
                                ],
                                13 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
                                ],
                                14 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
                                ],
                                15 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
                                ],
                                16 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
                                ],
                                17 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
                                ],
                                18 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
                                ],
                                19 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
                                ],
                                20 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
                                ],
                                21 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
                                ],
                                22 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
                                ],
                                23 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
                                ],
                                24 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
                                ],
                                25 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
                                ],
                                26 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
                                ],
                                27 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
                                ],
                                28 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
                                ],
                                29 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
                                ],
                                30 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
                                ],
                            ],
                            'readonly' => false,
                        ],
                        'digest' => [
                            'type' => 'boolean',
                            'active' => true,
                            'readonly' => true,
                        ],
                    ],
                ],
                'Hold_Filled' => [
                    'type' => 'pickUpNotice',
                    'settings' => [
                        'transport_types' => [
                            'type' => 'multiselect',
                            'options' => [
                                'sms' => [
                                    'active' => false,
                                ],
                                'letter' => [
                                    'active' => true,
                                ],
                            ],
                        ],
                        'digest' => [
                            'type' => 'boolean',
                            'active' => true,
                            'readonly' => true,
                        ],
                    ],
                ],
            ],
            'loan_history' => 'true',
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
            'hold_identifier' => 'Other name',
            'guarantees' => [
                [
                    'firstname' => 'Guarantor 1',
                    'lastname' => 'Surname 1',
                ],
                [
                    'firstname' => 'Guarantor 2',
                    'lastname' => 'Surname 2',
                ],
            ],
            'guarantor' => [],
            'notes' => 'Test opac notes',
            'full_data' => [],
            'smsnumber' => '0123456789',
            'category' => '',
        ];
        yield 'profile all values set' => [
            'koharestsuomi/profile.json',
            'koharestsuomi/guarantors.json',
            'koharestsuomi/messaging_prefs.json',
            $defaultExpected,
            [
                'updateMessagingSettings' => [
                    'method' => 'driver',
                ],
            ],
        ];
        yield 'profile some values missing' => [
            'koharestsuomi/profile_partial.json',
            'koharestsuomi/guarantors_partial.json',
            'koharestsuomi/messaging_prefs_partial.json',
            [
                'category' => '',
                'hold_identifier' => '',
                'guarantor' => [],
                'guarantees' => [],
                'smsnumber' => '0101010101',
                'notes' => '',
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
     * @param string $fixtureKey  Request fixture path
     * @param string $fixtureKey2 Request 2 fixture path
     * @param string $fixtureKey3 Request 3 fixture path
     * @param array  $expected    Expected results for the test
     * @param array  $config      Driver specific configuration
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestGetMyProfileData')]
    public function testGetMyProfile(
        string $fixtureKey,
        string $fixtureKey2,
        string $fixtureKey3,
        array $expected,
        array $config = []
    ): void {
        $patron = [
            'id' => '5',
            'cat_username' => '1111',
            'cat_password' => '2222',
        ];
        $profileResponse = $this->getJsonFixture($fixtureKey, 'Finna');
        $expected['full_data'] = $profileResponse;
        $requestMap = [
            [
                ['v1', 'patrons', $patron['id']],
                false,
                'GET',
                $patron,
                $profileResponse,
            ],
            [
                ['v1', 'patrons'],
                ['guarantorid' => $patron['id']],
                'GET',
                $patron,
                $this->getJsonFixture($fixtureKey2, 'Finna'),
            ],
            [
                ['v1', 'messaging_preferences'],
                ['borrowernumber' => $patron['id']],
                'GET',
                $patron,
                true,
                $this->getJsonFixture($fixtureKey3, 'Finna'),
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
            'koharestsuomi/patron.json',
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
            'koharestsuomi/patron_partial.json',
            [
                'home_library' => '',
                'id' => '123123',
                'email' => '',
                'firstname' => '',
                'lastname' => '',
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
